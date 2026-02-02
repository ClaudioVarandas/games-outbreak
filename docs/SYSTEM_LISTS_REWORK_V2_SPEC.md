# System Game Lists Rework V2 - Specification

## Overview

This rework merges the previously separate **monthly**, **highlights**, and **indie game** lists into a **single yearly list** per year. Highlight and indie designations become pivot flags (`is_highlight`, `is_indie`) on the `game_list_game` pivot table, used for client-side filtering. Seasoned and events list types remain unchanged.

The primary user-facing change is a new unified releases page at `/releases/{year}/{month?}` that replaces the old `/releases/monthly`, `/highlights`, and `/indie-games` routes.

---

## 1. Data Model Changes

### 1.1 ListTypeEnum (`app/Enums/ListTypeEnum.php`)

**Before:**
- `REGULAR`, `BACKLOG`, `WISHLIST`, `MONTHLY`, `SEASONED`, `EVENTS`, `HIGHLIGHTS`, `INDIE_GAMES`

**After:**
- `REGULAR`, `BACKLOG`, `WISHLIST`, `YEARLY`, `SEASONED`, `EVENTS`

Changes:
- Renamed `MONTHLY = 'monthly'` to `YEARLY = 'yearly'`
- Removed `HIGHLIGHTS = 'highlights'`
- Removed `INDIE_GAMES = 'indie-games'`
- Updated all enum methods: `label()`, `colorClass()`, `isUniquePerUser()`, `isSystemListType()`, `fromValue()`, `toSlug()`, `fromSlug()`

### 1.2 GameList Model (`app/Models/GameList.php`)

Scope changes:
- Renamed `scopeMonthly()` to `scopeYearly()`
- Removed `scopeIndieGames()` and `scopeHighlights()`

Method changes:
- Replaced `isMonthly()` with `isYearly()`
- Removed `isIndieGames()` and `isHighlights()`
- `canHaveHighlights()` returns `true` for `YEARLY` only
- `canMarkAsIndie()` returns `true` for `YEARLY` and `SEASONED`

Yearly lists use `start_at` (Jan 1) and `end_at` (Dec 31) to define the year range.

### 1.3 Database Migration

Migration: `2026_02_02_124152_merge_monthly_highlights_indie_to_yearly_lists.php`

Steps:
1. Collects all years that have monthly, highlights, or indie-games lists
2. For each year:
   - Creates one yearly list: `name = "Game Releases {year}"`, `slug = "{year}"`, `list_type = "yearly"`, `start_at = {year}-01-01`, `end_at = {year}-12-31`
   - Merges all games from monthly lists, deduplicating by `game_id`:
     - Keeps latest `release_date`
     - Unions `platforms` arrays
     - Preserves `is_highlight`/`is_indie` if any entry has it set to `true`
     - Merges `genre_ids`, keeps latest `primary_genre_id`
   - Absorbs games from highlights lists (sets `is_highlight = true`)
   - Absorbs games from indie lists (sets `is_indie = true`)
   - Auto-sets `platform_group` via `PlatformGroupEnum::suggestFromPlatforms()`
   - Inserts in batches of 500
3. Deletes all old monthly, highlights, and indie-games lists and their pivot data

The migration is SQLite-compatible (for test environments) by detecting the database driver and using `strftime('%Y', start_at)` instead of MySQL's `YEAR()`.

---

## 2. Routes

### Public Routes (`routes/web.php`)

**Added:**
| Route | Controller | Name |
|-------|-----------|------|
| `GET /releases` | Redirect to `/releases/{currentYear}` | `releases` |
| `GET /releases/{year}` | `ReleasesController@index` | `releases.year` |
| `GET /releases/{year}/{month}` | `ReleasesController@index` | `releases.year.month` |

Route constraints: `year` matches `[0-9]{4}`, `month` matches `[0-9]{1,2}`.

`GET /releases/seasoned` is defined before `{year}` routes to avoid conflict.

**Legacy redirects (301):**
- `/monthly-releases` -> `/releases`
- `/releases/monthly` -> `/releases`
- `/releases/indie-games` -> `/releases`
- `/indie-games` -> `/releases`
- `/highlights` -> `/releases`

**Removed:**
- `GET /releases/{type}` for monthly/indie-games handling
- `GET /indie-games`
- `GET /highlights`

### Admin Routes

Unchanged structurally. The `{type}` parameter in admin routes now accepts `yearly` instead of `monthly`. Highlights and indie-games are no longer standalone types.

---

## 3. Backend

### 3.1 ReleasesController (`app/Http/Controllers/ReleasesController.php`) - NEW

**`index(int $year, ?int $month = null)`**:
- Validates year (2020-2100) and month (1-12)
- Queries the yearly list for the given year: `GameList::yearly()->where('is_system', true)->whereYear('start_at', $year)`
- Eager loads games with genres, platforms, gameModes, playerPerspectives
- Orders games by `COALESCE(game_list_game.release_date, games.first_release_date) ASC`
- Groups games by month via `groupGamesByMonth()`:
  - TBA games (pivot `is_tba` or no release date) grouped under key `'tba'`
  - Non-TBA games grouped by `YYYY-MM` key
  - When viewing a single month, TBA games are excluded
  - Sorted: TBA first, then chronological
- Computes available years for prev/next navigation
- Loads genres for the filter dropdown
- Passes `allGamesJson` via `$yearlyList->getGamesForFiltering()` for Alpine.js
- Returns view `releases.yearly`

### 3.2 Deleted Controllers

- `HighlightsController` - removed entirely
- `IndieGamesController` - removed entirely

### 3.3 AdminListController Updates

**`systemLists()` method:**
- Queries yearly lists with `withCount('games')` plus sub-queries for `highlights_count` and `indie_count`
- Returns 3 view variables: `$yearlyLists`, `$seasonedLists`, `$eventsLists`

**`toggleGameHighlight()` method:**
- For YEARLY lists: toggles `is_highlight` on pivot directly (no cross-list sync)

**`toggleGameIndie()` method:**
- For YEARLY lists: toggles `is_indie` on pivot directly
- For SEASONED lists: toggles `is_indie` and syncs to the yearly list for that year

### 3.4 HomepageController Updates

**`index()` method:**
- Removed `getActiveMonthlyList()` and featured games glassmorphism section
- Added "this week's releases" query: yearly list for current year, filtered to games releasing within 7 days
- Passes `$thisWeekGames` and release URL to view

**`releases()` method:**
- Simplified to handle only `seasoned` type

### 3.5 GameListController Updates

- `$allowedTypes` in `showBySlug()` changed from `['monthly', 'indie', 'seasoned', 'events']` to `['yearly', 'seasoned', 'events']`

### 3.6 Request Validation

- `StoreGameListRequest`: `list_type` validation updated to `'in:regular,yearly,seasoned,events'`

### 3.7 Deleted Console Commands

- `CreateMonthlyGameLists` - no longer needed
- `CreateYearlyHighlightsList` - no longer needed
- `SyncHighlightsGames` - no longer needed

### 3.8 Updated Console Command

**`CreateSystemList`** (`app/Console/Commands/CreateSystemList.php`):
- Signature: `system-list:create {type} {year}` where type is `yearly`, `seasoned`, or `events`
- Currently implements `yearly` type creation only

---

## 4. Frontend

### 4.1 Releases Page (`resources/views/releases/yearly.blade.php`) - NEW

Layout structure:
```
[releases-nav: Releases | Seasoned]
[Page Title: "Game Releases {year}" or "{Month} {year}"]
[Year Navigation: <- prevYear | year | nextYear ->]
[Back to full year link (when viewing single month)]
[Filter Bar: All | Highlights | Indies | Platform | Genre | Search | Hide TBA]
[Month Dropdown (when viewing full year)]
[TBA Section (full year only, hideable)]
[Month 1 Section: header + game grid]
[Month 2 Section: header + game grid]
...
```

Key implementation details:
- **Grid**: `grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-8`
- **Game card**: Uses `<x-game-card>` component with `variant="default"`, `layout="overlay"`, `aspectRatio="3/4"`
- **Month headers**: Clickable (navigates to `/releases/{year}/{month}`), decorated with orange dots
- **Game count**: Shown under each month header
- **Empty state**: Shows available years when no list exists for the requested year

**Alpine.js client-side filtering** (`releasesFilter()`):
- **Type pills**: All / Highlights / Indies - filter by `is_highlight` / `is_indie` pivot flags
- **Platform group**: Dropdown filtering by `platform_group` value (uses `PlatformGroupEnum::orderedCases()`)
- **Genre**: TomSelect multi-select, filters by `primary_genre_id` or `genre_ids` array, includes "Other" option
- **Search**: Case-insensitive substring match on game name
- **Hide TBA**: Checkbox that hides the TBA section (only shown in full year view)
- All filters combine with AND logic

### 4.2 Navigation (`resources/views/components/releases-nav.blade.php`)

Simplified to two links (plus conditional News):
- **Releases** -> `/releases/{currentYear}` (active when `active="releases"`)
- **Seasoned Lists** -> `/releases/seasoned` (active when `active="seasoned"`)

Removed links: Highlights, Monthly Releases, Indie Games.

### 4.3 Seasoned Page (`resources/views/releases/index.blade.php`)

Simplified for seasoned-only use. Shows list tabs and list viewer components.

### 4.4 Admin Views

**System Lists Index** (`resources/views/admin/system-lists/index.blade.php`):
- Replaced monthly accordion + highlights section + indie section with **"Yearly Lists"** card grid
- Each card shows: name, games count, highlights count (star icon), indie count (plus-circle icon), year, active/public status icons, edit/delete buttons
- Delete confirmation modal with list name
- Seasoned Lists and Events Lists sections unchanged

**Create Form** (`resources/views/admin/system-lists/create.blade.php`):
- Type dropdown options: Yearly, Seasoned, Events (removed Monthly, Highlights, Indie Games)

**Game Grid** (`resources/views/components/admin/system-lists/game-grid.blade.php`):
- Replaced `$isHighlights` checks with `$isYearly` for conditional UI (highlight/indie toggles, platform group selector)

**Game Search** (`resources/views/components/admin/system-lists/game-search.blade.php`):
- Replaced `$list->isIndieGames() || $list->isMonthly()` with `$list->isYearly()`

### 4.5 Other Component Updates

**Add to List** (`resources/views/components/add-to-list.blade.php`):
- System list query filters to `YEARLY`, `SEASONED`, `EVENTS` (removed `MONTHLY`, `INDIE_GAMES`, `HIGHLIGHTS`)

### 4.6 Deleted Views

- `resources/views/highlights/index.blade.php`
- `resources/views/indie-games/index.blade.php`

---

## 5. Pivot Table Schema

The `game_list_game` pivot table schema is unchanged. Key columns used by yearly lists:

| Column | Type | Purpose |
|--------|------|---------|
| `release_date` | date, nullable | Game release date within the list |
| `platforms` | json, nullable | Array of IGDB platform IDs |
| `platform_group` | string, nullable | Auto-suggested platform group (e.g., "pc", "playstation") |
| `is_highlight` | boolean | Whether the game is a highlight (was previously a separate list type) |
| `is_indie` | boolean | Whether the game is an indie (was previously a separate list type) |
| `is_tba` | boolean | Whether the release date is TBA |
| `genre_ids` | json, nullable | Array of genre IDs for filtering |
| `primary_genre_id` | integer, nullable | Primary genre for grouping |
| `order` | integer | Display order |

---

## 6. Factory Updates

`GameListFactory`:
- Renamed `monthly()` state to `yearly()` using `ListTypeEnum::YEARLY`
- Removed `indieGames()` state

---

## 7. Test Coverage

Updated test files:
- `tests/Unit/ListTypeEnumTest.php` - Tests YEARLY enum case
- `tests/Feature/EditPivotDataTest.php` - Uses yearly list type
- `tests/Feature/MultiGenreTest.php` - Uses yearly list type
- `tests/Feature/AddToListComponentTest.php` - Updated enum references
- `tests/Feature/SystemListFilteringTest.php` - Updated type references
- `tests/Feature/HomepageControllerTest.php` - Tests new homepage structure (thisWeekGames)
- `tests/Feature/UrlRestructuringTest.php` - Tests yearly routes
- `tests/Feature/GameListControllerTest.php` - Updated factory/route references
- `tests/Feature/GameListRouteParametersTest.php` - Updated factory/route references
- `tests/Feature/AdminEventsListTest.php` - Updated factory references
- `tests/Feature/AdminSystemListAddGameTest.php` - Updated factory references

Deleted test files:
- `tests/Feature/HighlightsTest.php`
- `tests/Feature/IndieGamesTest.php`
- `tests/Feature/IndieGamesReworkTest.php`

Final result: **463 tests passing, 4 skipped, 0 failures.**

---

## 8. Files Changed Summary

### Created
| File | Purpose |
|------|---------|
| `app/Http/Controllers/ReleasesController.php` | New unified releases controller |
| `resources/views/releases/yearly.blade.php` | New yearly releases page |
| `database/migrations/2026_02_02_..._merge_monthly_highlights_indie_to_yearly_lists.php` | Data migration |

### Modified
| File | Changes |
|------|---------|
| `app/Enums/ListTypeEnum.php` | MONTHLY->YEARLY, removed HIGHLIGHTS/INDIE_GAMES |
| `app/Models/GameList.php` | Updated scopes and methods |
| `app/Http/Controllers/AdminListController.php` | Yearly list queries, simplified sync logic |
| `app/Http/Controllers/HomepageController.php` | This week's releases instead of featured games |
| `app/Http/Controllers/GameListController.php` | Updated allowed types |
| `app/Http/Requests/StoreGameListRequest.php` | Updated validation |
| `app/Console/Commands/CreateSystemList.php` | Rewritten for yearly type |
| `routes/web.php` | New routes, legacy redirects |
| `resources/views/releases/index.blade.php` | Seasoned-only |
| `resources/views/components/releases-nav.blade.php` | Simplified navigation |
| `resources/views/components/add-to-list.blade.php` | Updated type filter |
| `resources/views/components/admin/system-lists/game-grid.blade.php` | isYearly checks |
| `resources/views/components/admin/system-lists/game-search.blade.php` | isYearly check |
| `resources/views/admin/system-lists/index.blade.php` | Yearly cards layout |
| `resources/views/admin/system-lists/create.blade.php` | Updated type options |
| `resources/views/homepage/index.blade.php` | This week's releases section |
| `database/factories/GameListFactory.php` | yearly() state |

### Deleted
| File | Reason |
|------|--------|
| `app/Http/Controllers/HighlightsController.php` | Highlights merged into yearly |
| `app/Http/Controllers/IndieGamesController.php` | Indie games merged into yearly |
| `app/Console/Commands/CreateMonthlyGameLists.php` | Monthly lists no longer exist |
| `app/Console/Commands/CreateYearlyHighlightsList.php` | Highlights list type removed |
| `app/Console/Commands/SyncHighlightsGames.php` | Cross-list sync no longer needed |
| `resources/views/highlights/index.blade.php` | Page replaced by /releases/{year} |
| `resources/views/indie-games/index.blade.php` | Page replaced by /releases/{year} |
| `tests/Feature/HighlightsTest.php` | Feature removed |
| `tests/Feature/IndieGamesTest.php` | Feature removed |
| `tests/Feature/IndieGamesReworkTest.php` | Feature removed |
