# Year-tagged TBA games on event lists

Date: 2026-06-03
Status: Approved design — pending spec review, then implementation plan.

## Overview

On **events** lists, games announced for a future year with no month/day (e.g. "coming 2027") currently have nowhere to live: a fully-dated out-of-year game is silently skipped from the event page, and the only way to surface such an announcement is to mark it `is_tba` (which drops the date) — landing it in a single undifferentiated **"TBA"** pile that loses the year.

This feature lets an admin tag a TBA game with a **year**, so the event page can show dedicated **"2027" / "2028"** sections inside the TBA area, and the events→yearly sync can route those games into the correct yearly list.

## Locked decisions

| Decision | Choice |
|---|---|
| Data model | New nullable `release_year` (int) on the `game_list_game` pivot; meaningful only when `is_tba` is true |
| Event-page grouping | Per-year buckets **inside the TBA area only**; months, the out-of-year skip, and section ordering are untouched |
| Scope of grouping change | **Events lists only** (`$this->isEvents()`); yearly/seasoned displays unchanged |
| Admin field | A "Year (optional)" field revealed **only when TBA is checked** |
| Sync routing | A TBA game with `release_year` syncs into that year's yearly list |
| CLI command file | **`SyncEventToYearly.php` is NOT changed** — routing lives in `EventYearlySyncService` |

## 1. Data model

- New migration: add `release_year` (nullable unsigned small integer) to `game_list_game`.
- Add `'release_year'` to `GameList::games()->withPivot(...)` so `$game->pivot->release_year` is available.
- Semantics: only set when `is_tba` is true (a game either has a real `release_date`, or is TBA-with-optional-year, or is plain TBA). Server-side guards null it out when `is_tba` is false.

## 2. Admin modal + backend

**`resources/js/components/GameFormModal.vue`** (mirrors the existing trailer-URL field):
- New prop `initialReleaseYear`; `formData.releaseYear`.
- A **"Year (optional)"** input shown only when `formData.isTba` is true (a number input; placeholder e.g. `2027`).
- `onTbaToggle`: when TBA is switched **off**, clear `releaseYear`. `onEarlyAccessToggle` already turns TBA off, so it transitively clears the year.
- `resetForm` initialises `releaseYear` from `props.initialReleaseYear`; `handleSubmit` emits `releaseYear` (null when not TBA).

**`resources/js/components/GameEditModals.vue`**: carry `release_year` in/out — `initialReleaseYear` ref set from the `getGameGenres` response, reset on close, passed as `:initial-release-year`, and sent in the `performEdit` PATCH body.

**`resources/js/components/AddGameToList.vue`**: append `release_year` to the add FormData when set.

**`app/Http/Controllers/AdminListController.php`:**
- `addGame` + `updateGamePivotData`: validate `release_year` as `['nullable', 'integer', 'min:2000', 'max:2100']`; persist it **only when `is_tba`** is true, otherwise store `null` (server-side guard, so a non-TBA game can never carry a stray year).
- `getGameGenres`: return `'release_year' => $pivotData->release_year ?? null` so the edit modal pre-fills.

## 3. Event-page display — `GameList::groupGamesByMonth()`

Change **only the `is_tba` branch**. For an **events** list, a TBA game with a `release_year` gets its own per-year bucket; everything else is identical to today:

- `is_tba` + `$this->isEvents()` + `release_year` set → bucket key `tba-{year}`, label `"{year}"` (e.g. `"2027"`), `month_number = null`.
- `is_tba` otherwise (no year, or not an events list) → the existing `tba` bucket, label `"To Be Announced"`.
- The non-TBA branch, the out-of-year skip (line ~514), and the `$filterMonth` handling are **unchanged**.

**Ordering** (`uksort`): the TBA region stays first (as today). Within it: generic `tba` first, then `tba-{year}` ascending (2027, 2028); month sections (`Y-m`) follow in their current ascending order. Implemented by ranking keys: `tba` → `[0,0]`, `tba-{year}` → `[0, year]`, months → `[1, key]`. This preserves the existing month ordering and the TBA-region-first placement exactly.

**No Blade change** — `lists/show.blade.php` already renders each `$gamesByMonth` section generically from its `label` + `games`, so a `"2027"` section renders automatically. Yearly/seasoned pages (`releases/yearly.blade.php`, the regular `lists/show` branch) are unaffected because the new behavior is gated on `isEvents()`.

## 4. Sync routing — `EventYearlySyncService`

**The CLI command `app/Console/Commands/SyncEventToYearly.php` is NOT modified.** It only resolves the list, builds the plan, runs the picker, calls `apply()`, and prints the summary — all routing is in the service. The picker label is built from `plan()`, so a year-tagged TBA game **automatically** displays as `"<name> — TBA → 2027"` once the service resolves its target year, with no command change.

Change the target-year resolution in **both** `plan()` and `apply()` from:

```php
$targetYear = ($isTba || ! $date) ? $eventYear : $date->year;
```

to (precedence: real date wins for non-TBA; otherwise a tagged year; otherwise the event's year):

```php
$year = $game->pivot->release_year;
$targetYear = match (true) {
    ! $isTba && $date !== null => $date->year,
    $year !== null            => (int) $year,
    default                   => $eventYear,
};
```

- `GameListSyncService::insertGame()` gains a `release_year` field in its attrs array-shape and `attach(...)` payload (default null).
- `apply()` insert for a TBA-with-year game passes `is_tba = true`, `release_year = <year>`, `release_date = null` — the row lands in that year's yearly TBA bucket. (`fillMissing` is unchanged; `release_year` is not displayed on yearly pages, so back-filling it there is out of scope.)
- `plan()`'s `release_label` stays `"TBA"`; only `target_year` reflects the tagged year.

## 5. Testing (TDD — failing test first where practical)

- **Pivot/model:** `release_year` persists; exposed via the relationship.
- **Admin (feature):** `addGame` stores `release_year` when TBA; `updateGamePivotData` updates it; a non-TBA submit stores `null` even if a year is sent (the only-when-TBA guard); `getGameGenres` returns it; validation rejects a non-integer / out-of-range year.
- **`groupGamesByMonth` (unit/feature):** on an events list, a TBA game with `release_year = 2027` produces a `tba-2027` section labelled `"2027"`, ordered after generic `"TBA"` and before month sections; on a **yearly** list the same game stays in the flat `"TBA"` bucket (proving the `isEvents()` gate); month sections and a plain-TBA game are unaffected.
- **Sync (feature):** `apply()` routes a TBA + `release_year = 2027` game into the 2027 yearly list (auto-created), with `is_tba` true on the inserted row; a plain TBA game (no year) still routes to the event's year.
- **Event page (feature):** GET the event list page → a `"2027"` section renders for a year-tagged TBA game.

Run with `XDEBUG_MODE=off php artisan test --compact`; lint with `vendor/bin/pint --dirty --format agent`; commits omit any `Co-authored-by` trailer.

## Out of scope
- Month-level sections for future years (e.g. "March 2027") — future years are year-buckets only.
- Reordering or restyling existing month/TBA placement.
- Changing yearly/seasoned displays (gated out via `isEvents()`).
- Back-filling `release_year` onto existing yearly rows via `fillMissing`.

## File touch-list
- `database/migrations/<new>_add_release_year_to_game_list_game_table.php` (new)
- `app/Models/GameList.php` — `withPivot('...','release_year')`; `groupGamesByMonth()` `is_tba` branch + `uksort`
- `app/Http/Controllers/AdminListController.php` — `addGame`, `updateGamePivotData`, `getGameGenres`
- `app/Services/GameListSyncService.php` — `insertGame()` adds `release_year`
- `app/Services/EventYearlySyncService.php` — target-year resolution in `plan()` + `apply()`
- `resources/js/components/GameFormModal.vue`, `GameEditModals.vue`, `AddGameToList.vue`
- **Unchanged:** `app/Console/Commands/SyncEventToYearly.php`, `resources/views/lists/show.blade.php` (renders sections generically)
- Tests: `tests/Feature/...` (admin persistence, groupGamesByMonth grouping, sync routing, event-page render)
