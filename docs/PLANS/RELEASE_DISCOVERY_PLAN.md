# Release Discovery (month / week / date range) — Media Research Skill (increment 6, corrected)

> Scope update: not month-only. The skill and command take an arbitrary date window;
> `YYYY-MM` is a shorthand.

## Context

User manually researches monthly game releases by browsing YouTube (roundups, trailers), press sites, and X — watching clips, reading news — then curates and adds games to the site. This increment automates that routine as an **agent research skill**: multi-source media sweep for a given month, curation by *editorial presence* (which games the press/creators actually cover — not IGDB hype numbers; an earlier IGDB-query-centric design was rejected), resolution to IGDB ids, and staging through the existing import pipeline. Curation finishes on the staging page (checkboxes, bulk reject, promote).

## Existing building blocks (reuse — no new API, no schema changes)

- Import API (`POST /api/v1/import/check`, `POST /api/v1/import/list-items`) + staging list + review UI — used as-is.
- `games:igdb-search` artisan (name → igdb_id resolution) — used as-is.
- `IgdbService::fetchUpcomingGames()` query pattern — mirrored for the cross-check command.
- `ImportSourceEnum` (`app/Enums/ImportSourceEnum.php`) — extend with new source pills.
- `.claude/skills/import-game-list/SKILL.md` — sibling skill conventions (env reading, batching, summary format).
- Tailwind already scans `app/Enums/*.php` (increment 5) — new pill classes just work after `npm run build`.

## Changes

### 0. Source registry — DB-backed (BUILT, supersedes the config file)

> Superseded: the originally planned static `config/discovery.php` was replaced by the
> **discovery source registry** (increment 7, already implemented): `discovery_sources`
> table + admin page `/admin/discovery-sources` + token API. The four approved packs
> (calendars, press, YouTube, X — ~25 sources) are seeded by `DiscoverySourceSeeder`.

The skill reads its sources from the API instead of a config file:

```bash
curl -s "$BASE_URL/api/v1/sources?purpose=releases" -H "Authorization: Bearer $TOKEN"
# → { sources: [{ id, name, kind: calendar|press|youtube|x, purpose, url, locator, score }] }
```

Existing `JinaReaderService` (`app/Services/JinaReaderService.php`) reused for clean
article extraction; `YoutubeDataService::recentChannelVideos()` reused for channel walks
(cheap: 1 unit / 50 videos).

**Self-improving loop:** when the run's fallback searches surface a notable game that no
registry source covered, the skill POSTs the missing outlet to
`/api/v1/sources/propose` with `evidence` — it lands as a *pending* source the admin
confirms on `/admin/discovery-sources` (no config edits, no user interruption mid-run).

**Yield feedback:** after the sweep, the skill POSTs per-source stats to
`/api/v1/sources/run-report` (`{id, items_found, fetch_ok}`), and every staged item
carries `source_ids` so admin promote/reject decisions feed each source's score.

### 1. `ImportSourceEnum` — new cases

Add `Youtube = 'youtube'` (label `YouTube`, red pill: `bg-red-100 text-red-800 border-red-300 dark:bg-red-500/10 dark:text-red-300 dark:border-red-500/30`) and `X = 'x'` (label `X`, neutral zinc pill). Update `tests/Unit/ImportSourceEnumTest.php`. Full literal class strings (JIT rule).

### 2. Cross-check command `games:release-window` (`app/Console/Commands/ListReleaseWindow.php`)

Small safety net, NOT the primary source: `games:release-window {window : YYYY-MM, or YYYY-MM-DD..YYYY-MM-DD} {--platforms=} {--limit=200}` — parses the window argument (month shorthand expands to first→last day; explicit `from..to` otherwise; reject spans > 92 days). New lean `IgdbService::fetchReleaseWindowCandidates(CarbonInterface $from, CarbonInterface $to, array $platformIds = [])` (window on `first_release_date`, fields `id, name, slug, first_release_date, game_type, hypes, platforms.id, platforms.name, external_games.*`, 500-chunk pagination; do not touch `fetchUpcomingGames`). Output ranked-by-hypes JSON mirroring `games:igdb-search` shape + `hypes`. Purpose in the skill: catch notable titles the press sweep missed and confirm dates. Pest feature test (Http::fake): month shorthand + range parsing, window filtering, pagination, invalid/oversized window, JSON shape.

### 3. The skill — `.claude/skills/discover-releases/SKILL.md`

Invoked `/discover-releases` (args: window — `YYYY-MM`, `YYYY-MM-DD..YYYY-MM-DD`, or natural phrasing like "next week" / "first half of October" which the agent normalizes to a from..to range — plus target list slug). Search phrasing in the sweeps adapts to the window ("games releasing this week", "<Month> releases"); calendar pages are date-organized so any window extracts cleanly. The playbook it instructs the agent to run:

1. **Env check**: `IMPORT_API_BASE_URL` / `IMPORT_API_TOKEN` from `.env`; stop if missing. `GET /api/v1/sources?purpose=releases` — the registry drives every sweep below (no open-web trawling); keep each source's `id` for attribution and the run report.
2. **Calendar sweep**: fetch each `calendars` entry (Jina Reader / WebFetch), extract game name + claimed date + platforms for the target month. These curated pages are the backbone (~90% coverage expected).
3. **Press sweep**: one targeted search per `press` entry (`site:<domain> game releases <Month Year>`), read the best roundup hit per outlet, extract the same fields.
4. **YouTube sweep**: for each `youtube_channels` handle, `YoutubeDataService::recentChannelVideos(handle, monthStart)`-style walk (or targeted `site:youtube.com` search per channel) — read video titles/descriptions in the month window for roundups/trailers/dates. Metadata only, no watching.
5. **X sweep** (best effort): web search per `x_accounts` handle for month-relevant release announcements; skip silently if nothing surfaces.
6. **Corroboration table**: per game — sources that mention it, date each claims. Editorial-presence rule: **2+ independent sources → candidate; 1 source → candidate only if the agent judges it clearly notable** (known franchise/publisher). Conflicting dates → prefer most recent evidence, note the conflict.
7. **IGDB cross-check**: `XDEBUG_MODE=off CACHE_STORE=array php artisan games:release-window <window>` — flag high-hype titles missing from the media sweep (add if judged notable) and sanity-check dates. Fallback targeted web search allowed only for verifying a specific candidate's date.
8. **Site dedupe**: `POST /import/check` with all candidates → drop/report `on_target_list` / `on_staging_list`.
9. **Resolve ids**: `games:igdb-search` per remaining candidate (`--year` from month); no IGDB record → skip + report (site invariant).
10. **Stage**: `POST /import/list-items` batches ≤10 — `release_date` (verified), `confidence` (`high` = 2+ sources agree on date; `medium` = single source), `sources` from the actual evidence (`press`, `youtube`, `x`, `igdb`, `steam`), `source_ids` (registry ids of the sources that surfaced the game — powers yield attribution), `note` naming the outlets (e.g. `"IGN + Gematsu roundups; GameSpot says Oct 12"`).
11. **Run report**: `POST /api/v1/sources/run-report` with per-source `{id, items_found, fetch_ok}` for every registry source swept.
12. **Summary**: table grouped staged (by date) → skipped-already-on → no-IGDB-record → judgment-dropped; then **suggested source additions** (games only found via fallback → `POST /api/v1/sources/propose` with evidence; they land pending on `/admin/discovery-sources`); end with `review_url` + "curate on the staging page (bulk reject / promote selected)".

Rules section: keep total staged ≤ ~40/month; targeted searches only (no exhaustive crawling); never bypass the API; agent judgment is expected — this replaces the user's manual editorial filtering.

### 4. Docs

- `docs/SPEC/GAME_LIST_IMPORT_SPEC.md`: "Monthly discovery (media research)" section — sweep sources, editorial-presence rule, command, skill flow.
- CLAUDE.md import-flow paragraph: one line adding `/discover-month-releases` as the discovery entry point.

## Tests (Pest)

- `tests/Feature/Commands/MonthWindowCommandTest.php` — as in §2.
- `tests/Unit/ImportSourceEnumTest.php` — new cases covered.
- Existing import/staging suites untouched and green.
- Skill itself is a playbook (no automated test); validated by the live run below.

## Order of work

1. Enum cases + unit test; `npm run build` (nvm 24) for the new pill classes.
2. `fetchReleaseWindowCandidates()` + `games:release-window` command + tests.
3. Skill file.
4. Spec + CLAUDE.md line.
5. Pint + targeted test run.

## Verification

- Pest green (new command test, enum test, existing import suites).
- Live loop once: `/discover-month-releases 2026-08 2026` — verify the sweep finds the majors, staging rows carry outlet-citing notes + correct source pills, then bulk reject/promote on the staging page.
