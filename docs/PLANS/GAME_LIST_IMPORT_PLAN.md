# Game List Import — Claude Skill + API Endpoint

## Context

User periodically gets raw text lists of game releases (month headers + comma-separated game names, e.g. second-half-of-year release roundups). Today, getting these into the site means manually searching/adding each game in the admin lists UI. Goal: a "smart" ingestion flow — paste the list to a Claude Code skill, the agent researches each game (IGDB match, release date + platforms verified across sources, already-on-site check), then POSTs verified rows to a new token-authenticated API endpoint on **production**, which attaches games to the target yearly list.

Decisions made during brainstorming:
- **Shape B**: Claude skill does the research; new API endpoint on prod receives results.
- **Target env**: production (Forge). Base URL + token configurable via env.
- **Auth**: static bearer token in `.env` + tiny middleware (no Sanctum).
- **Approval**: post everything with confidence flags; low-confidence rows fixed afterwards in the existing admin edit modal. Skill prints a final summary table pointing at rows to review.
- **No IGDB match**: skip + report ("not importable yet"). IGDB-only invariant stays intact.
- **Date rule**: IGDB date + 1 agreeing source (Steam store page, publisher/news via web search) → confirmed month bucket. Only IGDB → still used, flagged "single-source". No date anywhere → attach with `is_tba = true` (existing TBA bucket).

## Existing building blocks (reuse, don't rebuild)

- `Game::fetchFromIgdbIfMissing(int $igdbId)` — `app/Models/Game.php:549` — creates game + relations from IGDB, dispatches `FetchGameImages`. The endpoint calls this; the skill never creates games directly.
- `GameList` + `game_list_game` pivot (`release_date`, `platforms` JSON of IGDB ids, `is_tba`, `order`, …) — month grouping via `GameList::groupGamesByMonth()` already keys off pivot `release_date`; TBA bucket already exists.
- `AdminListController::addGame()` — current attach logic (fetch-if-missing + pivot defaults). **Extract to a service** and refactor the controller to use it (per user's reuse-shared-logic rule) rather than duplicating in the API controller.
- `IgdbService` + `Http::igdb()` macro (rate-limited) — has `buildNameMatchClause()` word-matching used for events; reuse the same technique for a new game-by-name search.
- `IgdbService::extractExternalSources()` / `getSteamAppIdFromSources()` — Steam appid from IGDB payload.
- `SteamStoreService` — Steam store appdetails (has release date info) for the +1 date confirmation.
- `PlatformEnum` — platform igdb_id mapping for pivot `platforms`.

## Design

### 1. Local research tooling (used by the skill)

New artisan command **`games:igdb-search {name} {--limit=5} {--year=}`** (`app/Console/Commands/SearchIgdbGames.php`):
- New `IgdbService::searchGames(string $name, ?int $limit, ?int $expectedYear)` using the `search` clause on `/v4/games` (works there, unlike `/v4/events`) with `buildNameMatchClause()` fallback; returns candidates as JSON: `igdb_id, name, first_release_date, platforms (ids+names), release_dates (human/region), external steam appid, game_type, summary excerpt`.
- Skill runs this locally (local `.env` already has IGDB creds) — IGDB data identical regardless of env, and this reuses the rate-limit macro instead of raw curl.

### 2. API endpoints (production)

New `routes/api.php` (register in `bootstrap/app.php` `withRouting`), group `prefix('api/v1/import')` + new middleware `EnsureImportToken` (`app/Http/Middleware/EnsureImportToken.php`): compares `Authorization: Bearer` against `config('services.import.token')` (`IMPORT_API_TOKEN` in env, `services.php` entry); 401 on mismatch; 503 if token unset.

Controller `app/Http/Controllers/Api/GameListImportController.php` (two actions, Form Requests for both):

- **`POST /api/v1/import/check`** — body: `{ items: [{name, igdb_id?}] }`. Returns, per item: game exists on prod? (matched by `igdb_id`, fallback exact-name), which lists it belongs to, whether already on the target list. Skill calls this before researching, so already-imported rows short-circuit (saves tokens + API calls).
- **`POST /api/v1/import/list-items`** — body:
  ```json
  {
    "list_slug": "…", 
    "items": [{
      "igdb_id": 123,
      "release_date": "2026-10-15" | null,
      "is_tba": false,
      "platforms": [6, 167, 169],
      "confidence": "high|medium|low",
      "sources": ["igdb", "steam"],
      "note": "single-source date"
    }]
  }
  ```
  Validates list exists (`yearly`/`seasoned` type), items ≤ ~10 per request (skill batches; sync processing, each item ≈1–3 rate-limited IGDB calls). Per item: `GameListImportService` (see below) fetch-if-missing + attach with pivot `release_date`/`platforms`/`is_tba`/`order`; skips (reports) items already attached. Response: per-item result `{igdb_id, status: created|attached|already_on_list|failed, game_slug, error?}`.

### 3. Service extraction

New `app/Services/GameListImportService.php`:
- `attachGame(GameList $list, int $igdbId, ?CarbonInterface $releaseDate, array $platformIgdbIds, bool $isTba): AttachResult` — the logic currently inline in `AdminListController::addGame()` (fetch-if-missing, duplicate check, next `order`, pivot write). Refactor `AdminListController::addGame()` to call it in the same PR.

### 4. The skill — `.claude/skills/import-game-list/SKILL.md`

Project skill, invoked `/import-game-list` (args: target list slug; list text pasted in chat). Workflow it instructs:

1. **Parse**: split lines/commas; month headers (`July`, `August`, …, `Halloween`→October, `Intro` ignored) become date-hints for following games; strip parentheticals like `(GTA 6)` into alt-names; note year from target list.
2. **Check**: `POST /import/check` with all names → drop/report rows already on target list.
3. **Research** (per remaining game): `php artisan games:igdb-search` → pick best candidate (name similarity + expected year + game_type); no plausible match → try alt-name/web search for official title, retry once; still nothing → **skip + report**.
4. **Verify date**: IGDB `first_release_date`/`release_dates` vs one more source — Steam store via appid when present, else one targeted web search. Agree (same month) → high confidence. IGDB only → medium, note "single-source". Conflict → prefer newest evidence, low confidence + note. Nothing → `is_tba`.
5. **Platforms**: IGDB platform ids (filtered to `PlatformEnum` known ids); Steam presence sanity-check.
6. **POST** in batches of ≤10 to `/import/list-items`.
7. **Summary table** in chat: imported (month), TBA, skipped (no IGDB), low/medium confidence rows → "fix these in admin edit modal", already-present rows.

Config the skill reads: `IMPORT_API_BASE_URL` + `IMPORT_API_TOKEN` from local `.env` (curl with bearer header).

### Out of scope (YAGNI)

- Trailers — existing admin modal + `EventTrailerService` cover it.
- Queued/async import with status polling — sync small batches first; revisit only if Forge timeouts appear.
- Manual (non-IGDB) game creation.
- Admin UI for import logs.

## Implementation steps

1. `IgdbService::searchGames()` + `games:igdb-search` command (+ Pest unit test with `Http::fake`).
2. `GameListImportService::attachGame()` extracted from `AdminListController::addGame`; refactor controller; feature tests for both paths.
3. `EnsureImportToken` middleware + `services.php` config + `.env.example` keys (`IMPORT_API_TOKEN`, and skill-side `IMPORT_API_BASE_URL`). Feature tests: 401/503/200.
4. `routes/api.php` + registration in `bootstrap/app.php`; `GameListImportController` (`check`, `listItems`) + two Form Requests; feature tests: validation, dedupe, TBA path, month-bucket attach (assert pivot `release_date`/`platforms`/`is_tba`), unknown list, non-system list rejected.
5. Skill file `.claude/skills/import-game-list/SKILL.md` with the workflow above + payload contract + example curl.
6. Copy design/spec to `docs/SPEC/GAME_LIST_IMPORT.md` and this plan to `docs/PLANS/GAME_LIST_IMPORT_PLAN.md` (user's docs convention).
7. `vendor/bin/pint --dirty`; run affected tests with `XDEBUG_MODE=off php artisan test --compact --filter=…`.

## Verification

- Pest feature tests above (token auth, check endpoint, import endpoint happy/failure/TBA paths).
- Local end-to-end dry-run: point `IMPORT_API_BASE_URL` at local site, run `/import-game-list` with a 5-game slice of the real list, confirm games land in the local yearly list's correct month buckets + TBA bucket via admin UI.
- Then flip base URL to prod, run full list.
