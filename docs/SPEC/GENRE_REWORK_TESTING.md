# Genre Rework - Testing Guide

This document explains how to test the genre rework feature implementation.

---

## Prerequisites

1. **Run migrations**: Ensure the genre migrations are applied
   ```bash
   php artisan migrate
   ```

2. **Seed genres**: Populate the genres table with initial data
   ```bash
   php artisan db:seed --class=GenreSeeder
   ```

3. **Install npm dependencies**: Tom Select is required for genre selection
   ```bash
   npm install
   npm run build
   ```

---

## Automated Tests

### Run All Genre Tests

```bash
# Run genre management tests (admin CRUD)
php artisan test tests/Feature/GenreManagementTest.php

# Run multi-genre assignment tests
php artisan test tests/Feature/MultiGenreTest.php

# Run both together
php artisan test --filter=Genre
```

### Test Coverage

The automated tests cover:

| Test File | Coverage |
|-----------|----------|
| `GenreManagementTest.php` | Admin access control, CRUD operations, visibility toggles, review queue, reordering, merging, API search |
| `MultiGenreTest.php` | Multi-genre assignment via addGame, genre updates, bulk operations, TBA flag, frontend display logic |
| `IndieGamesReworkTest.php` | Toggle game indie status, sync to yearly indie list, genre requirement validation |
| `IndieGamesTest.php` | Indie games page display, year navigation, access control |

---

## Manual Testing Checklist

### 1. Genre Admin Page (`/admin/genres`)

- [ ] **Access Control**
  - Admin users can access the page
  - Non-admin users receive 403 Forbidden
  - Guests are redirected to login

- [ ] **Create Genre**
  - Click "Add Genre" button
  - Enter a unique name (slug auto-generates)
  - Toggle visibility if needed
  - Submit and verify genre appears in table

- [ ] **Edit Genre**
  - Click edit button on an existing genre
  - Modify name and/or slug
  - Save and verify changes persist

- [ ] **Delete Genre**
  - Only genres with 0 usage show delete button
  - System genres (like "Other") cannot be deleted
  - Confirm deletion works for unused genres

- [ ] **Drag-and-Drop Reordering**
  - Drag genres using the grip handle (left column)
  - Drop in new position
  - Refresh page and verify order persists

- [ ] **Toggle Visibility**
  - Click the eye icon to hide/show a genre
  - Verify system genres cannot be hidden
  - Hidden genres should not appear in selection dropdowns

- [ ] **Pending Review Queue**
  - If IGDB syncs new genres, they appear in the yellow section
  - Approve to make visible, Reject to delete

- [ ] **Merge Genres**
  - Select a source genre (will be removed)
  - Select a target genre (will receive games)
  - Submit and verify:
    - Source genre is deleted
    - Games with source genre now have target genre
    - Primary genre references are updated

### 2. Adding Games with Genres (Indie/Monthly Lists)

Navigate to an indie games list edit page:
```
/admin/system-lists/indie/{slug}/edit
```

- [ ] **Search and Add Game**
  - Type a game name in the search box
  - Click "Add" on a search result
  - Modal appears with:
    - Primary Genre dropdown
    - Additional Genres multi-select (Tom Select)
    - Release Date field
    - TBA checkbox
    - Platforms checkboxes

- [ ] **Genre Selection**
  - Select a primary genre
  - Use Tom Select to add up to 2 additional genres
  - Verify max of 3 total genres enforced

- [ ] **TBA Toggle**
  - Check TBA checkbox
  - Verify release date field is cleared
  - Submit and verify pivot data has `is_tba = true`

- [ ] **Verify Saved Data**
  - After adding, check the game card in the list
  - Use developer tools or tinker to verify pivot data:
    ```php
    $list = GameList::where('slug', 'indie-games-2026')->first();
    $game = $list->games()->first();
    dd($game->pivot->genre_ids, $game->pivot->primary_genre_id);
    ```

### 3. Updating Game Genres

- [ ] **API Endpoint Test**
  ```bash
  # Get current genres for a game
  curl -X GET /admin/system-lists/indie/{slug}/games/{game_id}/genres

  # Update genres
  curl -X PATCH /admin/system-lists/indie/{slug}/games/{game_id}/genres \
    -d "genre_ids[]={genre_id}&primary_genre_id={genre_id}"
  ```

### 4. Frontend Indie Games Page (`/indie-games`)

- [ ] **Sidebar Navigation (Desktop)**
  - Genres appear in sidebar on screens >= lg breakpoint
  - Active genre is highlighted
  - "Other" always appears last
  - Click genre to switch tabs

- [ ] **Horizontal Tabs (Mobile)**
  - Genres appear as scrollable pills on screens < lg
  - Tabs are horizontally scrollable
  - Active tab has distinct color

- [ ] **Game Grouping**
  - Games are grouped by primary genre
  - Games without primary genre appear in "Other"
  - Within each genre, games are grouped by month
  - TBA games appear in "TBA" section

- [ ] **Search Functionality**
  - Type in search box
  - Games filter by name in real-time

- [ ] **URL Hash Navigation**
  - Genre slug is stored in URL hash (e.g., `#metroidvania`)
  - Refreshing page maintains selected genre
  - Direct link to genre works

### 5. Bulk Operations

Navigate to `/admin/genres`:

- [ ] **Bulk Remove**
  - Select a genre and list
  - Submit and verify genre removed from all games in that list

- [ ] **Bulk Replace**
  - Select source, target genre, and list
  - Submit and verify all games with source now have target

- [ ] **Assign Games**
  - Via API: `POST /admin/genres/{id}/assign-games`
  - Verify genre added to multiple games at once

---

## API Endpoints Reference

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/admin/genres` | List all genres (admin page) |
| POST | `/admin/genres` | Create new genre |
| PATCH | `/admin/genres/{id}` | Update genre |
| DELETE | `/admin/genres/{id}` | Delete genre |
| PATCH | `/admin/genres/{id}/approve` | Approve pending genre |
| DELETE | `/admin/genres/{id}/reject` | Reject pending genre |
| PATCH | `/admin/genres/{id}/toggle-visibility` | Toggle visibility |
| PATCH | `/admin/genres/reorder` | Reorder genres |
| POST | `/admin/genres/merge` | Merge two genres |
| POST | `/admin/genres/bulk-remove` | Remove genre from list |
| POST | `/admin/genres/bulk-replace` | Replace genre in list |
| POST | `/admin/genres/{id}/assign-games` | Assign genre to games |
| GET | `/admin/api/genres/search` | Search genres (for Tom Select) |
| GET | `/admin/system-lists/{type}/{slug}/games/{id}/genres` | Get game genres |
| PATCH | `/admin/system-lists/{type}/{slug}/games/{id}/genres` | Update game genres |

---

## Database Verification

### Check Genre Table Structure

```sql
DESCRIBE genres;
-- Should have: id, igdb_id, name, slug, is_system, is_visible, is_pending_review, sort_order, timestamps
```

### Check Pivot Table Structure

```sql
DESCRIBE game_list_game;
-- Should have: genre_ids (json), primary_genre_id (foreignId)
-- indie_genre column should be removed
```

### Verify Genre Data

```php
// In tinker
Genre::visible()->ordered()->get(['id', 'name', 'slug', 'is_system', 'sort_order']);

// Check protected "Other" genre
Genre::where('is_system', true)->first();
```

### Verify Game Genres

```php
// Get a game's genre assignment
$list = GameList::indieGames()->first();
$game = $list->games()->first();
$genreIds = json_decode($game->pivot->genre_ids, true);
$primaryGenre = Genre::find($game->pivot->primary_genre_id);
```

---

## Troubleshooting

### Tom Select Not Working

1. Verify npm packages installed: `npm list tom-select`
2. Rebuild assets: `npm run build`
3. Check browser console for JS errors
4. Verify `window.TomSelect` is available in console

### Genres Not Showing in Dropdown

1. Check genre visibility: `Genre::where('is_visible', true)->count()`
2. Check pending review flag: `Genre::where('is_pending_rconteview', false)->count()`
3. Verify admin API returns data: `GET /admin/api/genres/search`

### Games Not Grouped by Genre

1. Verify `primary_genre_id` is set on pivot
2. Check the pivot data: `$game->pivot->primary_genre_id`
3. Ensure `IndieGamesController::groupGamesByGenre()` receives valid genre data

### Migration Issues

If the migrations fail:
1. Check if columns already exist: `Schema::hasColumn('genres', 'slug')`
2. The migrations use `if (!in_array(...))` checks to be idempotent
3. For fresh install, run in order:
   ```bash
   php artisan migrate:fresh
   php artisan db:seed --class=GenreSeeder
   ```

---

## Performance Considerations

- Genre list is cached via `ordered()` scope using database indexes
- Tom Select loads genres via AJAX to avoid bloating page load
- Frontend uses Alpine.js for reactive tab switching without page reloads
- Pivot table has index on `primary_genre_id` for efficient grouping queries
