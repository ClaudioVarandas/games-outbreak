# Indie Games Rework Specification

## Overview
Rework the indie games system to follow the highlights pattern: games are marked as indie in source lists (monthly, seasoned, etc.) and synced to yearly indie lists. The public page is rebuilt with genre-based tabs and TBA/month organization.

---

## Summary of Changes

### What's Being Removed
- `/releases/indie-games` route and page
- Monthly indie game lists paradigm (no more individual monthly indie lists)

### What's Being Kept
- `INDIE_GAMES` enum case in `ListTypeEnum` (label changed to "Indies")
- Admin system lists management for indie lists
- Existing admin CRUD functionality for indie game lists

### What's Being Added
- `is_indie` boolean field on `game_list_game` pivot table
- `indie_genre` string field on pivot for genre tab assignment
- Indie toggle button (pixelated gem icon) in admin game management
- Genre selection modal when toggling indie
- Sync mechanism to yearly indie lists (like highlights)
- New `/indie-games` public page with genre tabs
- Config for allowed genre tabs
- Artisan command: `system-list:create indie {year}`
- Search functionality on indie page

---

## Database Changes

### Migration: Add indie fields to game_list_game pivot
```php
Schema::table('game_list_game', function (Blueprint $table) {
    $table->boolean('is_indie')->default(false)->after('is_highlight');
    $table->string('indie_genre')->nullable()->after('is_indie');
});
```

**Files to modify:**
- Create migration: `database/migrations/YYYY_MM_DD_add_indie_fields_to_game_list_game_table.php`

---

## Model Changes

### GameList Model (`app/Models/GameList.php`)
- Update `games()` relationship to include new pivot fields: `is_indie`, `indie_genre`
- Add `canMarkAsIndie(): bool` method - returns true for all system list types except INDIE_GAMES and HIGHLIGHTS
- Add `isIndies(): bool` method (rename from `isIndieGames()` if exists)

### ListTypeEnum (`app/Enums/ListTypeEnum.php`)
- Change INDIE_GAMES label from "Indie Games" to "Indies"
- Keep all other properties (color, slug, etc.)

---

## Configuration

### File: `config/system-lists.php`
Add `indies` key with genre configuration:

```php
'indies' => [
    'genres' => [
        'metroidvania',
        'roguelike',
        'platformer',
        'puzzle',
        'adventure',
        // ... other configured genres
    ],
],
```

**Note:** Games with genres not in this list appear in "Other" tab.

---

## Admin Changes

### Toggle Button in Game Grid
**File:** `resources/views/components/admin/system-lists/game-grid.blade.php`

Add indie toggle button (pixelated gem/diamond icon) next to highlight star:
- Only visible for lists where `canMarkAsIndie()` returns true
- Orange/amber color scheme (to differentiate from highlight yellow star)
- On click: open genre selection modal

### Genre Selection Modal
When toggling indie on:
1. Show modal with:
   - Title: "Mark as Indie"
   - Dropdown: populated with game's genres (from `$game->genres`)
   - Confirm/Cancel buttons
2. On confirm:
   - Update pivot: `is_indie = true`, `indie_genre = selected_genre`
   - Sync to yearly indie list

### AdminListController Changes
**File:** `app/Http/Controllers/AdminListController.php`

Add methods:
- `toggleGameIndie(Request $request, string $type, string $slug, Game $game)` - toggles is_indie and syncs
- `syncGameToYearlyIndies(GameList $sourceList, Game $game, bool $adding, ?string $indieGenre = null)` - syncs to yearly indie list

Logic mirrors `toggleGameHighlight()` and `syncGameToYearlyHighlights()`.

### Routes
**File:** `routes/web.php`

Add admin route:
```php
Route::patch('/admin/system-lists/{type}/{slug}/games/{game:id}/indie',
    [AdminListController::class, 'toggleGameIndie'])
    ->name('admin.system-lists.games.toggle-indie');
```

---

## Artisan Command

### Create: `system-list:create indie {year}`
**File:** `app/Console/Commands/CreateSystemListCommand.php` (or new file)

Pattern: `php artisan system-list:create indie 2026`

Creates:
- GameList with `list_type = INDIE_GAMES`
- Name: "Indies 2026"
- Slug: "indies-2026"
- `is_system = true`, `is_active = true`, `is_public = true`
- `start_at` = Jan 1 of year, `end_at` = Dec 31 of year

---

## Frontend: New Indie Games Page

### Route
**File:** `routes/web.php`

```php
Route::get('/indie-games', [IndieGamesController::class, 'index'])->name('indie-games');
```

Remove:
```php
Route::get('/releases/{type}', ...)->where('type', 'monthly|indie-games|seasoned');
// Change to:
Route::get('/releases/{type}', ...)->where('type', 'monthly|seasoned');
```

### Controller
**File:** `app/Http/Controllers/IndieGamesController.php` (new)

```php
public function index(Request $request): View
{
    $year = $request->get('year', now()->year);

    // Get yearly indie list for selected year
    $indieList = GameList::where('list_type', ListTypeEnum::INDIE_GAMES)
        ->whereYear('start_at', $year)
        ->where('is_active', true)
        ->where('is_public', true)
        ->with(['games.genres', 'games.platforms'])
        ->first();

    // Get available years for dropdown
    $availableYears = GameList::where('list_type', ListTypeEnum::INDIE_GAMES)
        ->where('is_active', true)
        ->where('is_public', true)
        ->selectRaw('YEAR(start_at) as year')
        ->distinct()
        ->orderByDesc('year')
        ->pluck('year');

    // Get configured genres
    $configuredGenres = config('system-lists.indies.genres', []);

    // Group games by genre, then by TBA/month
    $gamesByGenre = $this->groupGamesByGenre($indieList, $configuredGenres);

    return view('indie-games.index', [
        'indieList' => $indieList,
        'selectedYear' => $year,
        'availableYears' => $availableYears,
        'configuredGenres' => $configuredGenres,
        'gamesByGenre' => $gamesByGenre,
    ]);
}

private function groupGamesByGenre(?GameList $list, array $configuredGenres): array
{
    // Group games: configured genres + "other"
    // Within each: TBA section first, then by month
    // ...
}
```

### View Structure
**File:** `resources/views/indie-games/index.blade.php`

Layout:
```
┌─────────────────────────────────────────────────────────┐
│ Header: "Indie Games" title + Year Dropdown + Search    │
├─────────────────────────────────────────────────────────┤
│ Genre Tabs: [Metroidvania] [Roguelike] [Platformer] ... [Other] │
├─────────────────────────────────────────────────────────┤
│ Content (within selected tab):                          │
│   ┌─────────────────────────────────────────────────┐   │
│   │ TBA Section (at top)                             │   │
│   │   [Game Card] [Game Card] [Game Card]            │   │
│   ├─────────────────────────────────────────────────┤   │
│   │ January 2026                                     │   │
│   │   [Game Card] [Game Card]                        │   │
│   ├─────────────────────────────────────────────────┤   │
│   │ February 2026                                    │   │
│   │   [Game Card] [Game Card] [Game Card]            │   │
│   └─────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────┘
```

Features:
- **Year dropdown**: Shows available years, current year default
- **Search box**: Searches all tabs, shows combined results
- **Genre tabs**: From config, plus "Other" tab
- **Game sections**: TBA at top, then months chronologically
- **Game cards**: Standard cards (same as other list views)

Alpine.js behaviors:
- Tab switching with URL hash support (e.g., `/indie-games#metroidvania`)
- Search filters games client-side across all tabs
- Year change triggers page reload with `?year=XXXX`

---

## Sync Behavior

### When Marking Game as Indie
1. Admin toggles indie button on a game in source list (monthly, seasoned, etc.)
2. Modal appears with genre dropdown (game's genres)
3. Admin selects genre and confirms
4. System updates pivot: `is_indie = true`, `indie_genre = selected_genre`
5. System finds yearly indie list for game's release year
6. If list exists, game is attached with:
   - `indie_genre` = selected genre
   - `release_date` = copied from source pivot (or null)
   - `platforms` = copied from source pivot (or null)
   - `is_tba` = copied from source pivot (or false)

### When Unmarking Game as Indie
1. Admin toggles off indie button
2. System updates pivot: `is_indie = false`, `indie_genre = null`
3. Game is **NOT** removed from yearly indie list (stays once synced)

### Game Removal from Source List
- Game is removed from source list
- Game **stays** in yearly indie list (no cascade removal)

---

## Files to Create/Modify

### New Files
1. `database/migrations/YYYY_MM_DD_add_indie_fields_to_game_list_game_table.php`
2. `app/Http/Controllers/IndieGamesController.php`
3. `resources/views/indie-games/index.blade.php`
4. `resources/views/components/indie/genre-tabs.blade.php` (optional component)
5. `app/Console/Commands/CreateSystemListCommand.php` (if not extending existing)
6. `tests/Feature/IndieGamesReworkTest.php`

### Modified Files
1. `app/Models/GameList.php` - add pivot fields, `canMarkAsIndie()` method
2. `app/Enums/ListTypeEnum.php` - change label to "Indies"
3. `config/system-lists.php` - add `indies.genres` config
4. `routes/web.php` - add `/indie-games` route, remove from `/releases/{type}`
5. `app/Http/Controllers/AdminListController.php` - add `toggleGameIndie()`, `syncGameToYearlyIndies()`
6. `resources/views/components/admin/system-lists/game-grid.blade.php` - add indie toggle button + modal

---

## Testing Requirements

### Unit Tests
- `canMarkAsIndie()` returns correct values for each list type
- Genre grouping logic correctly handles configured vs "other" genres
- TBA/month organization works correctly

### Feature Tests
- Indie toggle route works and syncs to yearly list
- Genre modal data is passed correctly
- `/indie-games` page loads with correct data
- Year navigation works
- Search filters correctly
- Games appear in correct genre tabs
- "Other" tab shows unconfigured genres
- Artisan command creates yearly indie list correctly

---

## Implementation Order

1. **Database**: Add migration for `is_indie` and `indie_genre` pivot fields
2. **Model**: Update GameList model with new pivot fields and methods
3. **Enum**: Update ListTypeEnum label
4. **Config**: Add indie genres config
5. **Artisan**: Create `system-list:create indie` command
6. **Admin Backend**: Add toggle route and sync logic in AdminListController
7. **Admin Frontend**: Add indie toggle button and genre modal to game grid
8. **Route Changes**: Add `/indie-games`, remove from `/releases/{type}`
9. **Controller**: Create IndieGamesController with grouping logic
10. **View**: Create indie-games/index.blade.php with tabs and sections
11. **Tests**: Write feature tests for all functionality
12. **Cleanup**: Run pint, verify all tests pass

---

## Verification

1. Create yearly indie list: `php artisan system-list:create indie 2026`
2. Go to admin, open a monthly list
3. Click indie toggle (gem icon) on a game
4. Select genre in modal, confirm
5. Verify game appears in yearly indie list
6. Visit `/indie-games` - verify game appears in correct genre tab
7. Test year dropdown navigation
8. Test search functionality
9. Run: `php artisan test --filter=IndieGames`