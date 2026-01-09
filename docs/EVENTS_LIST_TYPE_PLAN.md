# Plan: Add "Events" List Type for System Lists

## Summary
Add a new `EVENTS` list type for system lists (admin-managed, `is_system = true`), following the existing pattern of `MONTHLY`, `SEASONED`, and `INDIE_GAMES` types. Events will be displayed as a flat list (like Seasoned), ordered by created date (newest first).

## Files to Modify

### 1. `app/Enums/ListTypeEnum.php`
- Add `case EVENTS = 'events';`
- Update all match expressions:
  - `label()` - return `'Events'`
  - `colorClass()` - use cyan/teal colors: `'bg-cyan-100 text-cyan-800 dark:bg-cyan-900 dark:text-cyan-200'`
  - `isUniquePerUser()` - return `false`
  - `isSystemListType()` - return `true` for EVENTS
  - `fromValue()` - handle `'events'`
  - `toSlug()` - return `'events'`
  - `fromSlug()` - handle `'events'`

### 2. `app/Models/GameList.php`
- Add `scopeEvents()` method
- Add `isEvents()` helper method

### 3. `app/Http/Controllers/AdminListController.php`
- Update `systemLists()` to fetch and pass `$eventsLists` (flat list, ordered by `created_at` desc)
- Update `storeSystemList()` to allow `ListTypeEnum::EVENTS->value`
- Update `updateSystemList()` to allow changing to `EVENTS` type

### 4. `app/Http/Requests/StoreGameListRequest.php`
- Add `'events'` to the `list_type` validation rule in `rules()`

### 5. `resources/views/admin/system-lists/create.blade.php`
- Add list type dropdown with options: Monthly, Indie Games, Seasoned, Events
- Default to Monthly (most commonly created)

### 6. `resources/views/admin/system-lists/index.blade.php`
- Add "Events Lists" section (flat list like Seasoned, ordered by created_at desc)

### 7. `database/factories/GameListFactory.php`
- Add `events()` state method

### 8. Tests
- Create `tests/Feature/AdminEventsListTest.php` with tests for:
  - Creating events list via admin
  - Events appear in system lists index
  - Adding/removing games from events list

## Implementation Details

### ListTypeEnum Changes
```php
case EVENTS = 'events';

// In label()
self::EVENTS => 'Events',

// In colorClass()
self::EVENTS => 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900 dark:text-cyan-200',

// In isUniquePerUser()
self::EVENTS => false,

// In isSystemListType()
self::EVENTS => true,

// In fromValue()
'events' => self::EVENTS,

// In toSlug()
self::EVENTS => 'events',

// In fromSlug()
'events' => self::EVENTS,
```

### Create Form List Type Dropdown
```blade
<div class="mb-6">
    <label for="list_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
        List Type <span class="text-red-500">*</span>
    </label>
    <select name="list_type" id="list_type" required
            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
        <option value="monthly" {{ old('list_type', 'monthly') == 'monthly' ? 'selected' : '' }}>Monthly</option>
        <option value="indie-games" {{ old('list_type') == 'indie-games' ? 'selected' : '' }}>Indie Games</option>
        <option value="seasoned" {{ old('list_type') == 'seasoned' ? 'selected' : '' }}>Seasoned</option>
        <option value="events" {{ old('list_type') == 'events' ? 'selected' : '' }}>Events</option>
    </select>
    @error('list_type')
        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>
```

### Index View Events Section
Copy the Seasoned pattern - flat list showing all events (not grouped), ordered by created_at desc, showing list cards with status icons, edit/delete buttons.

## Verification

1. **Run existing tests**: `php artisan test --filter=Admin` to ensure no regressions
2. **Create events list via admin UI**:
   - Visit `/admin/system-lists/create`
   - Select "Events" from dropdown
   - Fill in name, dates, set active/public
   - Submit and verify redirect to edit page
3. **Check index page**: Events should appear in new "Events Lists" section
4. **Add game to events list**: Use game search to add a game
5. **Run new tests**: `php artisan test tests/Feature/AdminEventsListTest.php`
6. **Run Pint**: `vendor/bin/pint --dirty`
