# Sync games from an events list into the yearly lists (CLI command)

Date: 2026-06-03
Status: Approved design — pending spec review, then implementation plan.

## Overview

Add an artisan command that copies games from a **system events list** into the relevant **yearly** ("year") lists, carrying each game's release date, platforms, genres, release flags, and the per-game **YouTube trailer (`video_url`)**.

**Use case:** during a showcase an admin populates the event list live with announced releases and their trailers; afterwards the admin runs the command to push those games — all of them, or a hand-picked subset — into the year list(s) so they appear in the yearly releases view, trailer included.

This generalises the pattern the codebase already uses for `seasoned → yearly` indie sync (`AdminListController::syncIndieToYearlyList`), and reuses the yearly-list creation logic from `CreateSystemList`.

## Prerequisite

Depends on the per-game `video_url` pivot column and `GameList::games()` `withPivot('video_url')` from the `feat/events-list-trailers` work. That must be present (merged or branched from) before implementing this.

## Locked decisions

| Decision | Choice |
|---|---|
| Target year per game | **Per-game release year** — a game routes to the yearly list matching its own `release_date` year |
| Game selection | **Interactive Laravel Prompts `multiselect`** (all preselected); `--all` flag skips the prompt |
| Conflict (game already in the year list) | **Fill-missing only** — never overwrite curated data; only set empty fields |
| TBA / no-date games | Route to the **event's year** yearly list (lands in that year's TBA bucket) |
| Missing target year list | **Auto-create** a minimal system yearly list for that year, then sync into it |
| Surface | **CLI only** (no admin UI in this scope) |

## Command

```
php artisan events:sync-to-yearly {event} {--all}
```

### Argument: `event` (required) — the source events list

The **primary form is the list slug** — the same identifier used in the public URL
`/list/events/<slug>` and the admin URL `/admin/system-lists/events/<slug>/edit`
(e.g. `nacon-connect-2026`). The slug is what an admin already knows from the browser, so it
is the documented, expected input.

As a convenience, a value made up **entirely of digits** is treated as the list **id** instead.

Resolution rule (exact):
- `ctype_digit($event)` → `GameList::find((int) $event)`
- otherwise → `GameList::events()->where('slug', $event)->first()`

The resolved record must have `list_type === EVENTS`. If it is missing, or is found but is not an
events list, the command prints a clear error (e.g. `No events list found for "<event>".` /
`List "<name>" is a yearly list, not an events list.`) and returns `Command::FAILURE`.

### Option: `--all`

Sync **every** eligible game without showing the picker — fully non-interactive (for scripting or
the scheduler). Omit it to get the interactive Laravel Prompts `multiselect` (all games preselected)
so the admin can deselect and validate the subset before applying.

### Definition

```php
protected $signature = 'events:sync-to-yearly
                        {event : Source events list — slug (e.g. nacon-connect-2026) or numeric id}
                        {--all : Sync every eligible game, skipping the interactive picker}';

protected $description = 'Copy games (release date, platforms, genres, flags, YouTube trailer) from a system events list into the matching yearly list(s), routed per game by release year.';
```

### Examples

```bash
# Interactive — review and pick which games to push from the NACON Connect 2026 event
php artisan events:sync-to-yearly nacon-connect-2026

# Non-interactive — push everything (e.g. from a scheduled task)
php artisan events:sync-to-yearly nacon-connect-2026 --all

# By numeric id instead of slug
php artisan events:sync-to-yearly 42 --all
```

### Flow

1. Resolve + validate the source events list (rule above).
2. Load its games with pivot. If none are eligible, warn and exit success.
3. Build the **plan** (per game: target year + intended action `insert` / `fill` / `skip`).
4. Selection: `--all` selects every eligible game; otherwise show the `multiselect` (all preselected).
   Each label: `"{name} — {date|TBA} → {year}{ 🎬 when it has a trailer}"`.
5. **Apply** the selected games inside a DB transaction; collect per-game outcomes.
6. Print a summary: year lists created, games inserted, rows filled (and which fields), skipped-as-complete,
   per-year counts, and any per-game errors.

### Exit codes

- `0` (`SUCCESS`) — sync ran, including the "nothing to do / no eligible games" case.
- non-zero (`FAILURE`) — the `event` argument did not resolve to an events list.

## Target year resolution (per game)

- **Dated game:** `year = (pivot.release_date ?? game.first_release_date)->year`.
- **TBA / no date** (`pivot.is_tba` or no resolvable date): `year = event.start_at?->year ?? now()->year`; the inserted row keeps `is_tba = true` so the yearly view shows it under TBA.
- Locate the year list with `GameList::yearly()->where('is_system', true)->whereYear('start_at', $year)->first()`; auto-create when absent.

## Field-copy semantics

**Insert** (game not yet in the target year list) — attach with:
- `order` = `max(order)+1` in the target list
- `release_date` = pivot date (null for TBA)
- `platforms` = pivot platforms, falling back to the game's active platforms when empty (mirrors `addGame`)
- `platform_group` = `PlatformGroupEnum::suggestFromPlatforms($platformIds)->value`
- `is_tba`, `is_early_access` = from the event pivot
- `genre_ids`, `primary_genre_id` = from the event pivot
- `video_url` = from the event pivot
- `is_indie = false`, `is_highlight = false` (yearly-curation flags, not implied by an event)

**Fill-missing** (game already in the target year list) — only set fields that are currently **empty** on the year row, never overwrite:
- `video_url` if the year row's is null/empty
- `release_date` if the year row's is null and the event has one
- `platforms` if the year row's is empty and the event has them

`is_highlight`, `is_indie`, genres, and any non-empty value on the year row are left untouched. If nothing needs filling → counted as **skipped (already complete)**.

## Components (reuse + refactor in the same PR)

Per the project rule to extract duplicated logic to a service and refactor existing callers in the same change:

- **`App\Services\GameListSyncService`** (new):
  - `findYearlyList(int $year): ?GameList` — `whereYear('start_at', $year)` system yearly lookup.
  - `firstOrCreateYearlyList(int $year): GameList` — returns the existing year list or creates a minimal system yearly list using the exact convention from `CreateSystemList::createYearlyList()` (name `"Game Releases {year}"`, unique slug, `start_at`=Jan 1 start-of-day, `end_at`=Dec 31 end-of-day, `is_public/is_system/is_active = true`, `user_id = 1`).
  - `resolvePlatforms(Game $game, mixed $pivotPlatforms): array` — decode pivot platforms or fall back to the game's active platforms.
  - `insertGame(GameList $list, Game $game, array $attrs): void` — builds `order` + `platform_group`, json-encodes platforms, attaches.
  - `fillMissing(GameList $list, Game $game, array $candidate): array` — sets only empty fields; returns the list of filled field names.
- **`App\Services\EventYearlySync\SyncPlan` / `SyncResult`** value objects (or simple typed arrays with array-shape docblocks) describing per-game decisions and outcomes — so the command is pure IO and the logic is unit-testable.
- **`EventYearlySyncService::plan(GameList $eventList): SyncPlan`** and **`apply(GameList $eventList, array $gameIds): SyncResult`** — orchestration; depends on `GameListSyncService`.
- **`App\Console\Commands\SyncEventToYearly`** — thin: argument/flag parsing, the `multiselect` prompt, calling the service, rendering the summary.

**Refactors in the same PR:**
- `CreateSystemList::createYearlyList()` → delegate creation to `GameListSyncService::firstOrCreateYearlyList()` (keeping its "already exists" error message for the manual command).
- `AdminListController::syncIndieToYearlyList()` → use `GameListSyncService::findYearlyList()` + `insertGame()` for its attach block (it keeps its own early-return-when-missing and `is_indie` override).

The command and orchestration service keep one clear responsibility each; the shared primitives live in `GameListSyncService`.

## Errors & robustness

- Source not found / wrong type → printed error + `Command::FAILURE`.
- No eligible games → warning + `Command::SUCCESS`.
- `apply()` runs in a DB transaction; a per-game failure is caught, recorded in `SyncResult`, and reported in the summary without aborting the whole run.
- `--all` makes the command fully non-interactive (safe for scheduling/scripts).

## Testing (TDD — write the failing test first wherever practical)

**Unit / service (Pest, `RefreshDatabase`):**
- `firstOrCreateYearlyList(2028)` creates a list with the exact attributes (name/slug/start_at/end_at/flags) and returns the **existing** one (no duplicate) on a second call / when one already exists.
- `findYearlyList()` returns null when absent.
- `resolvePlatforms()` decodes JSON pivot platforms and falls back to the game's active platforms when empty.
- `insertGame()` writes `order`, `platform_group`, encoded `platforms`, and `video_url`.
- `fillMissing()` sets only empty fields and returns the filled field names; leaves non-empty values untouched.
- `EventYearlySyncService::plan()` routes a 2026-dated game to 2026 and a 2028-dated game to 2028; routes a TBA game to the event year; marks an already-complete game as `skip`.

**Feature / command (Pest, via `$this->artisan('events:sync-to-yearly', ...)->'--all'` for determinism):**
- Per-game routing: games fan out to the correct year lists by their own release year.
- Auto-creates a missing year list and inserts into it.
- Fill-missing: an existing year row with no `video_url` gets the event's trailer, but a year row with a curated `release_date`/`platforms` is **not** overwritten.
- TBA game → event-year list with `is_tba = true`.
- Dedup/no-op: a fully-present game is reported skipped, no duplicate pivot row.
- Source resolution errors (unknown slug, non-events list) exit non-zero with a message.
- Interactive selection is exercised via the `--all` path; if Laravel Prompts' fake (`Prompt::fake([...])`) is available in this version, add one test selecting a subset.

Run with `XDEBUG_MODE=off php artisan test --compact`. Lint with `vendor/bin/pint --dirty --format agent`. Commits omit any `Co-authored-by` trailer.

## Out of scope
- Admin UI / button (CLI only for now).
- An overwrite/force mode (only fill-missing).
- Scheduling (the command can be scheduled later if wanted; not configured here).
- Touching the `addGame` controller flow (its request-driven attach stays; only `syncIndieToYearlyList` is refactored onto the shared primitive).

## File touch-list
- `app/Services/GameListSyncService.php` (new)
- `app/Services/EventYearlySyncService.php` (new) + small `SyncPlan`/`SyncResult` shapes (new)
- `app/Console/Commands/SyncEventToYearly.php` (new)
- `app/Console/Commands/CreateSystemList.php` (refactor to reuse `firstOrCreateYearlyList`)
- `app/Http/Controllers/AdminListController.php` (`syncIndieToYearlyList` reuse `findYearlyList` + `insertGame`)
- Tests: `tests/Unit/GameListSyncServiceTest.php`, `tests/Feature/SyncEventToYearlyCommandTest.php`
