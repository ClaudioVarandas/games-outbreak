# Game List Import — Spec

**Date:** 2026-07-20
**Status:** Implemented

## Problem

Raw text lists of game releases (month headers + comma-separated names, e.g.
second-half-of-year roundups) had to be entered game-by-game through the admin
lists UI. We want a "smart" ingestion flow: paste the list to a Claude Code
skill, let the agent research each game, then push verified rows to production.

## Decisions

- **Shape:** Claude Code skill (`/import-game-list`) does the research; a new
  token-authenticated API on production receives the results.
- **Target:** production (Forge). Base URL + token configurable via env
  (`IMPORT_API_BASE_URL`, `IMPORT_API_TOKEN`).
- **Auth:** static bearer token compared in `EnsureImportToken` middleware
  against `config('services.import.token')`. 401 on mismatch, 503 when unset.
  No Sanctum.
- **Approval flow (staging quarantine):** imports never touch the live lists.
  Each target list gets a hidden staging list (`{target-slug}-import`,
  `list_type = import`, private + inactive, linked via
  `game_lists.import_target_list_id`); items attach there. Admin reviews on the
  staging list's edit page (fix pivot data, Remove = reject), then **promotes**
  per game or all. Promote reuses `EventYearlySyncService::apply()`: each game
  routes to the yearly list matching its release-date year (or TBA
  `release_year`), auto-creating missing year lists and filling only-empty
  fields for duplicates; promoted games are detached from staging.
  The `import` list type exists because a hidden *yearly* staging list would
  still leak via `/releases/{year}` (no `is_active`/`is_public` filter there)
  and collide with the real year list.
- **No IGDB match:** skip + report ("not importable yet"). The IGDB-only game
  creation invariant stays intact.
- **Date rule:** IGDB date + 1 agreeing source (Steam store, publisher/news via
  web search) → confirmed month bucket (`confidence: high`). Only IGDB →
  used with `confidence: medium` + "single-source" note. Conflict → newest
  evidence, `confidence: low`. Nothing → `is_tba: true` (+ `release_year`).

## Components

### API (production)

- `routes/api.php` (registered in `bootstrap/app.php`), group `api/v1/import`,
  middleware `App\Http\Middleware\EnsureImportToken`.
- `POST /api/v1/import/check` — items `[{name, igdb_id?}]` → per item: exists
  (match by `igdb_id`, fallback exact name), system-list membership,
  `on_target_list` when `list_slug` supplied. Max 100 items.
- `POST /api/v1/import/list-items` — `list_slug` (yearly/seasoned system list
  only) + items `[{igdb_id, release_date?, is_tba?, release_year?, platforms?,
  confidence?, sources?, note?}]`. Max 10 items per request (sync processing,
  each may hit rate-limited IGDB). Items attach to the target's **staging
  list** (auto-created on first use via
  `GameListImportService::stagingListFor()`). Per-item statuses: `attached`,
  `already_on_list`, `game_not_found`, `failed`. Response includes
  `staging_list_slug` + `review_url`. `check` additionally reports
  `on_staging_list` per item.
- Controller: `App\Http\Controllers\Api\GameListImportController`.
  Form Requests: `App\Http\Requests\Api\ImportCheckRequest`,
  `App\Http\Requests\Api\ImportListItemsRequest`.

### Attach service (shared with admin UI)

`App\Services\GameListImportService::attachGame(GameList, igdbId, attributes)`
— extracted from `AdminListController::addGame()`, which now delegates to it.
Fetches the game from IGDB when missing (`Game::fetchFromIgdbIfMissing`),
dedupes, resolves release date/platform defaults, suggests `platform_group`
for yearly lists, enforces the Early Access/TBA invariants
(`GameListImportService::guardReleaseState`), writes the pivot row.
Returns `App\DTOs\GameListAttachResult` with `App\Enums\GameListAttachStatusEnum`.

### IGDB name search (local research tooling)

- `IgdbService::searchGames(term, limit)` — native `search` clause on
  `/v4/games`, falls back to word-by-word `name ~` matching.
- `php artisan games:igdb-search {name} {--limit=5} {--year=}` — prints JSON
  candidates: igdb_id, name, slug, game_type label, first_release_date,
  release_year, platforms (igdb ids + names), human release dates,
  steam_app_id, summary excerpt. `--year` ranks matching years first.

### Skill

`.claude/skills/import-game-list/SKILL.md` — parse rules (month headers as
date hints, parenthetical alt-names), check → research → verify → batch POST →
summary table pointing medium/low-confidence rows at the admin edit modal.

### Staging & promote (admin)

- `ListTypeEnum::IMPORT` (`'import'`, label "Import Staging") — system-only
  type, never publicly rendered; `GameList::isImport()` helper.
- `GameListImportService::promoteFromStaging(GameList $staging, array $gameIds,
  EventYearlySyncService $sync)` — filters ids to staging membership, delegates
  to `apply()`, detaches every non-error game, returns result + `detached`.
- Route `POST /admin/system-lists/{type}/{slug}/games/promote`
  (`admin.system-lists.games.promote`) → `AdminListController::promoteGames()`
  (import lists only; `game_ids[]` or `all: true`).
- Admin index: "Import Staging" section (pending count, target name,
  Review & Promote link). Staging edit page: banner + "Promote all" header
  button; per-game Promote/Reject buttons in the game grid.

### Review UX (staging edit page)

- Import review metadata is persisted on the `game_list_game` pivot:
  `import_confidence` (`ImportConfidenceEnum`: high/medium/low, with
  `label()`/`badgeClass()`), `import_sources` (json array), `import_note`.
  Written by the import API via `GameListImportService::attachGame()`; null for
  games added through the normal admin flow.
- Staging lists are **list-view only**: `editSystemList()` forces
  `viewMode = 'list'` and the Grid/List toggle is hidden.
- Each staged row shows: confidence pill with coloured dot
  (`ImportConfidenceEnum::dotClass()`), one coloured pill per source
  (`ImportSourceEnum::labelFor()/badgeClassFor()`, gray fallback for unknown
  sources), the note, and a single amber `Already on: {list} ({date}), …` line
  listing every other system list the game belongs to
  (`AdminListController::stagedGameMemberships()`).
- Bulk actions: header has Select all / Promote all / Reject all; selecting
  rows raises a floating bottom bar (`N selected · Promote · Reject · Clear`).
  Reject detaches from staging only (`GameListImportService::rejectFromStaging()`,
  route `admin.system-lists.games.reject`), never touching real lists.

## Out of scope

Trailers (existing admin modal), queued/async import, non-IGDB game creation,
admin UI for import logs, auto-deleting emptied staging lists.

## Tests

- `tests/Feature/Api/GameListImportApiTest.php` — token auth (401/503), check
  matching + membership + `on_staging_list`, list validation, batch cap,
  staging attach + auto-creation, pivot assertions
  (date/platforms/TBA/release_year), dedupe, IGDB fetch-on-missing,
  `game_not_found`.
- `tests/Feature/Admin/PromoteImportListTest.php` — promote per-year routing
  (incl. auto-created year lists), fillMissing for duplicates, detach from
  staging, non-import 422, non-admin 403, public invisibility, admin UI
  sections/buttons.
- `tests/Feature/Commands/SearchIgdbGamesCommandTest.php` — candidate JSON,
  fallback query, year ranking, blank input.
- Existing `AdminSystemListAddGameTest`, `EditPivotDataTest`,
  `AdminListEarlyAccessTest`, `AdminListReleaseYearTest`, `AdminListVideoUrlTest`
  cover the refactored admin path.
