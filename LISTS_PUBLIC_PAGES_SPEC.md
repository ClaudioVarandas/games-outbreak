# URL Refactoring Specification: System vs User Lists

**Project**: Games Outbreak - URL Structure Cleanup
**Version**: 1.0
**Date**: 2026-01-07
**Status**: Ready for Implementation

---

## Executive Summary

This specification defines a URL restructuring to clearly separate system-managed lists from user-owned lists. The refactoring enforces clean URL patterns, improves SEO clarity, and maintains backward compatibility through redirects.

### Key Changes

- **System lists**: Restrict `/list/{type}/{slug}` to ONLY system types (monthly, indie-games, seasoned)
- **User lists**: Consolidate under `/u/{username}/lists` pattern
- **Remove redundancy**: Eliminate `/u/{username}/regular` index page
- **Dual-mode pattern**: Single URL for viewing and editing custom lists (like backlog/wishlist) 

---

## Current Problems

1. **Ambiguous URLs**: `/list/regular/{slug}` can show user lists, creating confusion about ownership
2. **Inconsistent naming**: `/u/{username}/my-lists` doesn't align with viewing pattern
3. **Redundant pages**: `/u/{username}/regular` index duplicates overview functionality
4. **No validation**: System list route accepts ANY list type without filtering

---

## Proposed URL Structure

### System Lists (Public, Read-Only)

| Pattern | Allowed Types | Example |
|---|---|---|
| `/list/{type}/{slug}` | `monthly`, `indie-games`, `seasoned` ONLY | `/list/monthly/january-2026` |

**Blocked types**: `regular`, `backlog`, `wishlist`

### User Lists (Dual-Mode: View + Owner Management)

| URL | Purpose | Access |
|---|---|---|
| `/u/{username}/backlog` | Backlog list | Public view, owner edit |
| `/u/{username}/wishlist` | Wishlist list | Public view, owner edit |
| `/u/{username}/lists` | All lists overview | Public view |
| `/u/{username}/lists/{slug}` | Custom list | Public view, owner edit |
| `/u/{username}/lists/create` | Create form | Owner only |

### Removed URLs

| Old URL | Replacement | Notes |
|---|---|---|
| `/list/regular/{slug}` | `/u/{username}/lists/{slug}` | 404 error |
| `/u/{username}/regular` | `/u/{username}/lists` | 301 redirect |
| `/u/{username}/regular/{slug}/edit` | `/u/{username}/lists/{slug}` | Dual-mode replaces separate edit route |
| `/u/{username}/my-lists` | `/u/{username}/lists` | Route name change |

---

## Implementation Plan

### Phase 1: Add Type Validation

**File**: `app/Http/Controllers/GameListController.php`
**Method**: `showBySlug(string $type, string $slug)`

```php
public function showBySlug(string $type, string $slug): View
{
    // Only allow system list types for this public route
    $allowedTypes = ['monthly', 'indie-games', 'seasoned'];

    if (!in_array($type, $allowedTypes)) {
        abort(404, 'List type not found. User lists are available at /u/{username}/lists/{slug}');
    }

    $listType = ListTypeEnum::fromSlug($type);
    // ... rest of existing logic
}
```

---

### Phase 2: Update Routes

**File**: `routes/web.php`

#### A. Public Viewing Routes (Update)

```php
Route::middleware(['prevent-caching'])
    ->prefix('u/{user:username}')
    ->name('user.lists.')
    ->group(function () {
        Route::get('/backlog', [UserListController::class, 'backlog'])->name('backlog');
        Route::get('/wishlist', [UserListController::class, 'wishlist'])->name('wishlist');
        Route::get('/lists', [UserListController::class, 'myLists'])->name('lists'); // Renamed from my-lists
        Route::get('/lists/{slug}', [UserListController::class, 'showList'])->name('lists.show'); // NEW
    });
```

**Changes**:
- Rename `/my-lists` → `/lists`
- Add `/lists/{slug}` for individual custom list viewing (NEW)
- Remove `/regular` route (redundant)

#### B. Owner Management Routes (Update)

```php
Route::middleware(['auth', 'user.ownership', 'prevent-caching'])
    ->prefix('u/{user:username}')
    ->name('user.lists.')
    ->group(function () {
        // Custom lists CRUD
        Route::get('/lists/create', [UserListController::class, 'createList'])->name('lists.create');
        Route::post('/lists', [UserListController::class, 'storeList'])->name('lists.store');
        Route::patch('/lists/{slug}', [UserListController::class, 'updateList'])->name('lists.update');
        Route::delete('/lists/{slug}', [UserListController::class, 'destroyList'])->name('lists.destroy');

        // Game management (unchanged)
        Route::post('/{type}/games', [UserListController::class, 'addGame'])->name('games.add');
        Route::delete('/{type}/games/{game}', [UserListController::class, 'removeGame'])->name('games.remove');
        Route::patch('/{type}/games/reorder', [UserListController::class, 'reorderGames'])->name('games.reorder');
    });
```

**Changes**:
- Change `/regular/*` → `/lists/*`
- Remove `/regular/{slug}/edit` (merged into dual-mode `/lists/{slug}`)
- Rename route names: `regular.*` → `lists.*`

#### C. Legacy Redirects (Add)

```php
// Redirect old /u/{user}/regular to new /lists
Route::get('/u/{user:username}/regular', function ($username) {
    return redirect("/u/{$username}/lists", 301);
});

// Update existing /lists redirect
Route::get('/lists', function () {
    return redirect()->route('user.lists.lists', ['user' => auth()->user()->username], 301);
})->middleware('auth')->name('legacy.lists');

// Update existing /lists/create redirect
Route::get('/lists/create', function () {
    return redirect()->route('user.lists.lists.create', ['user' => auth()->user()->username]);
})->middleware('auth')->name('legacy.lists.create');
```

---

### Phase 3: Update Controllers

**File**: `app/Http/Controllers/UserListController.php`

#### A. Create New Dual-Mode Method

```php
/**
 * Display a specific custom list (dual-mode: public viewing + owner editing).
 */
public function showList(User $user, string $slug): View
{
    $list = GameList::where('user_id', $user->id)
        ->where('list_type', ListTypeEnum::REGULAR)
        ->where('slug', $slug)
        ->with(['games' => function ($query) {
            $query->orderByPivot('order');
        }])
        ->firstOrFail();

    // Check visibility for non-owners
    if (auth()->id() !== $user->id && !auth()->user()?->is_admin) {
        if (!$list->is_public || !$list->is_active) {
            abort(404);
        }
    }

    // Determine if user can manage (owner or admin)
    $canManage = auth()->check() && (auth()->id() === $user->id || auth()->user()->is_admin);

    $viewMode = session('game_view_mode', 'grid');

    return view('user-lists.lists.show', compact('user', 'list', 'canManage', 'viewMode'));
}
```

#### B. Rename Existing Methods

| Old Method | New Method | Logic Changes |
|---|---|---|
| `createRegular()` | `createList()` | None (name only) |
| `storeRegular()` | `storeList()` | Update redirect route name |
| `updateRegular()` | `updateList()` | None (name only) |
| `destroyRegular()` | `destroyList()` | Update redirect route name |

#### C. Remove Methods

- **Remove**: `regularLists()` - Redundant (use `myLists()` instead)
- **Remove**: `editRegular()` - Merged into `showList()` dual-mode

#### D. Update AdminListController

**File**: `app/Http/Controllers/AdminListController.php`

```php
public function myLists(): RedirectResponse
{
    return redirect()->route('user.lists.lists', ['user' => auth()->user()->username], 301);
}
```

---

### Phase 4: Update Views

#### A. File Operations

**Rename**:
```bash
mv resources/views/user-lists/regular/edit.blade.php resources/views/user-lists/lists/show.blade.php
mv resources/views/user-lists/regular/create.blade.php resources/views/user-lists/lists/create.blade.php
```

**Delete**:
```bash
rm resources/views/user-lists/regular/index.blade.php
```

#### B. Update `lists/show.blade.php` (Dual-Mode View)

**File**: `resources/views/user-lists/lists/show.blade.php`

Convert from edit-only to dual-mode (like backlog.blade.php):

```blade
@extends('layouts.app')

@section('title', ($canManage ? 'Manage ' : '') . $list->name)

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-8 flex items-center justify-between">
        <div>
            <h1 class="text-4xl font-bold mb-2 text-gray-800 dark:text-gray-100">
                {{ $list->name }}
                @if($canManage)
                    <span class="text-sm text-orange-600 dark:text-orange-400 font-normal ml-2">(Managing)</span>
                @endif
            </h1>
            <p class="text-gray-600 dark:text-gray-400">
                {{ $list->games->count() }} {{ Str::plural('game', $list->games->count()) }}
            </p>
        </div>

        @if($canManage)
            {{-- View mode toggle buttons --}}
        @endif
    </div>

    @if($canManage)
        <x-user-lists.game-search :user="$user" :type="$list->slug" />
    @endif

    @if($list->games->count() > 0)
        @if($canManage)
            <x-user-lists.game-grid
                :games="$list->games"
                :user="$user"
                :type="$list->slug"
                :viewMode="$viewMode"
            />
        @else
            <x-featured-games-glassmorphism
                :games="$list->games"
                :initialLimit="999"
                emptyMessage="No games in this list."
            />
        @endif
    @endif
</div>
@endsection
```

#### C. Update Route References

**Files to update**:
- `resources/views/user-lists/my-lists.blade.php`
- `resources/views/user-lists/lists/create.blade.php`
- `resources/views/admin/user-lists.blade.php`

**Search and replace**:
- `user.lists.my-lists` → `user.lists.lists`
- `user.lists.regular.create` → `user.lists.lists.create`
- `user.lists.regular.store` → `user.lists.lists.store`
- `user.lists.regular.edit` → `user.lists.lists.show`
- `user.lists.regular.update` → `user.lists.lists.update`
- `user.lists.regular.destroy` → `user.lists.lists.destroy`

---

### Phase 5: Update Tests

**File**: `tests/Feature/GameListControllerTest.php`

#### Add Type Validation Test

```php
it('list route only shows system lists', function () {
    $user = User::factory()->create();
    $regularList = GameList::factory()->create([
        'user_id' => $user->id,
        'list_type' => ListTypeEnum::REGULAR,
        'slug' => 'my-custom-list',
        'is_public' => true,
        'is_active' => true,
    ]);

    // Should return 404 for regular lists on /list/ route
    $response = $this->get("/list/regular/my-custom-list");
    $response->assertStatus(404);

    // Should work on user route
    $response = $this->get("/u/{$user->username}/lists/my-custom-list");
    $response->assertStatus(200);
});

it('list route shows system lists', function () {
    $monthlyList = GameList::factory()->monthly()->system()->create([
        'slug' => 'january-2026',
        'is_public' => true,
        'is_active' => true,
    ]);

    $response = $this->get("/list/monthly/january-2026");
    $response->assertStatus(200);
});
```

#### Update Existing Tests

- Search for `user.lists.regular` and update to `user.lists.lists`
- Search for `/regular/` paths and update to `/lists/`
- Verify all route names resolve correctly
- Run full test suite: `php artisan test`

---

## Files Affected

### Controllers (3 files)
1. `app/Http/Controllers/GameListController.php` - Add validation
2. `app/Http/Controllers/UserListController.php` - Rename methods, add showList()
3. `app/Http/Controllers/AdminListController.php` - Update redirect

### Routes (1 file)
1. `routes/web.php` - Update routes, add redirects

### Views (5 files)
1. `resources/views/user-lists/my-lists.blade.php` - Update route references
2. `resources/views/user-lists/lists/show.blade.php` - Convert to dual-mode (renamed from regular/edit.blade.php)
3. `resources/views/user-lists/lists/create.blade.php` - Update form action (renamed from regular/create.blade.php)
4. `resources/views/admin/user-lists.blade.php` - Update edit links
5. ~~`resources/views/user-lists/regular/index.blade.php`~~ - DELETE

### Tests (1 file)
1. `tests/Feature/GameListControllerTest.php` - Add validation test, update route references

---

## URL Examples

### ✅ AFTER Implementation

**System Lists (Public)**:
- `/list/monthly/january-2026` → Works
- `/list/indie/best-indies-2026` → Works
- `/list/seasoned/best-horror` → Works

**User Lists**:
- `/u/cvarandas/lists` → All lists overview
- `/u/cvarandas/backlog` → Backlog (unchanged)
- `/u/cvarandas/wishlist` → Wishlist (unchanged)
- `/u/cvarandas/lists/whishlist-to-buy-2026` → Custom list (dual-mode)
- `/u/cvarandas/lists/create` → Create form (owner only)

### ❌ BLOCKED After Implementation

- `/list/regular/anything` → 404 error
- `/list/backlog/anything` → 404 error
- `/list/wishlist/anything` → 404 error

### ↪️ REDIRECTED After Implementation

- `/u/cvarandas/regular` → `/u/cvarandas/lists` (301)
- `/u/cvarandas/regular/slug/edit` → N/A (use `/u/cvarandas/lists/slug`)
- `/lists` → `/u/{auth_user}/lists` (301)

---

## Implementation Checklist

### Phase 1: Validation ✅
- [ ] Add type validation to `GameListController@showBySlug()`
- [ ] Test `/list/regular/slug` returns 404
- [ ] Test `/list/monthly/slug` still works

### Phase 2: Routes ✅
- [ ] Update public routes: rename `my-lists` → `lists`, add `lists/{slug}`
- [ ] Update owner routes: change `regular/*` → `lists/*`
- [ ] Add legacy redirect for `/u/{user}/regular`
- [ ] Update legacy redirects for `/lists`

### Phase 3: Controllers ✅
- [ ] Rename methods: `createRegular` → `createList`, etc.
- [ ] Create `showList()` dual-mode method
- [ ] Remove `regularLists()` and `editRegular()`
- [ ] Update `AdminListController` redirect

### Phase 4: Views ✅
- [ ] Rename files: `regular/edit` → `lists/show`, `regular/create` → `lists/create`
- [ ] Delete `regular/index.blade.php`
- [ ] Convert `lists/show.blade.php` to dual-mode
- [ ] Update all route references in views
- [ ] Update `admin/user-lists.blade.php`

### Phase 5: Tests ✅
- [ ] Add system list type validation test
- [ ] Update route references in tests
- [ ] Run full test suite (`php artisan test`)

### Phase 6: Verification 🔍
- [ ] Test as guest: View public system lists
- [ ] Test as guest: View public user custom lists
- [ ] Test as owner: Manage own custom lists
- [ ] Test as admin: View/edit all lists
- [ ] Test legacy URLs redirect correctly
- [ ] Test backlog/wishlist unchanged

---

## Success Criteria

- ✅ `/list/{type}/{slug}` only shows system lists (monthly, indie, seasoned)
- ✅ `/list/regular/*` returns 404 with helpful error message
- ✅ `/u/{username}/lists` shows all lists overview
- ✅ `/u/{username}/lists/{slug}` dual-mode for custom lists (view + owner edit)
- ✅ Custom list CRUD works with new route structure
- ✅ Legacy URLs redirect to new structure (301)
- ✅ All existing tests pass
- ✅ Backlog and wishlist functionality unchanged
- ✅ No broken links in views

---

## Benefits

1. **Clear URL Ownership**: System vs user lists have distinct patterns
2. **SEO Clarity**: URLs communicate content ownership clearly
3. **Consistent Patterns**: All user lists under `/u/{username}/` namespace
4. **Reduced Redundancy**: One overview page instead of two
5. **Dual-Mode Simplicity**: Single URL for viewing and editing (like backlog/wishlist)
6. **Backward Compatible**: Legacy URLs redirect gracefully

---

## Migration Notes

**Database**: No changes required (routing/controller refactor only)

**Breaking Changes**:
- Bookmarks to `/list/regular/{slug}` will 404
- Direct code references to `user.lists.regular.*` routes will break

**Non-Breaking**:
- All system list URLs unchanged
- Backlog/wishlist URLs unchanged
- Admin routes unchanged
- Legacy user URLs redirect

---

**Document Version**: 1.0
**Last Updated**: 2026-01-07
**Status**: Ready for Implementation
