# My Games Collection - Summary & Testing Guide

## Summary of Changes

### New Models & Database
- **UserGame** (`user_games` table) - Tracks a user's game with status (playing/played/backlog), independent wishlist flag, time played (decimal hours), rating (1-100), sort order, and timestamps
- **UserGameCollection** (`user_game_collections` table) - Per-user collection metadata: custom name, description, cover image, and per-status privacy toggles
- **UserGameStatusEnum** - Enum with Playing, Played, Backlog cases

### New Pages & Routes
- **My Games page** (`/u/{username}/games`) - Public page showing a user's game collection with:
  - Cover banner with collection name/description
  - Stats bar (total, playing, played, backlog, wishlist, total hours)
  - Filter tabs (Playing, Played, Backlog, Wishlist)
  - Sort dropdown (Date Added, Alphabetical, Release Date, Time Played, Rating, Manual)
  - Grid/List view toggle
  - Add Game search (owner only, via Vue component)
- **Settings page** (`/u/{username}/games/settings`) - Owner-only page for collection name, description, cover image, and privacy toggles

### API Endpoints (`/api/user-games/`)
- `POST /` - Add game to collection (status or wishlist)
- `PATCH /{userGame}` - Update status, wishlist, time played, rating
- `DELETE /{userGame}` - Remove from collection
- `GET /status/{game_id}` - Check if game is in authenticated user's collection

### Game Card Integration
- Replaced old backlog/wishlist quick-action buttons with new collection action icons across all game card layouts (desktop hover, mobile variants)
- Icons: gamepad (playing), trophy (played), clock (backlog), heart (wishlist)
- Uses Alpine.js `gameCollectionActions` component for AJAX interactions

### Game Detail Page
- **Desktop**: Sidebar widget with 4 large collection action buttons below game info
- **Mobile**: Fixed sticky bottom bar with labeled buttons

### Header Navigation
- Replaced separate Backlog/Wishlist/My Lists links with single "My Games" link

### Cleanup
- `add-to-list` component simplified to only handle admin system lists
- Removed `ensureSpecialLists()` calls (old list system bootstrapping)

---

## How to Test

### Prerequisites
- Run migrations: `php artisan migrate`
- Build frontend: `npm run build` (requires Node 24+)
- Have at least one user account

### Automated Tests
```bash
# Run all My Games tests (31 tests)
php artisan test tests/Feature/UserGamesPageTest.php tests/Feature/UserGameApiTest.php

# Run the full test suite to check for regressions
php artisan test
```

### Manual Testing

#### 1. My Games Page
1. Log in and navigate to your username's games page (`/u/{username}/games`)
2. Verify the cover banner shows with default collection name ("{username}'s Games")
3. Verify the stats bar shows all zeroes initially
4. Verify the settings gear icon appears (top-right of banner)

#### 2. Adding Games via Game Card
1. Go to any game listing page (releases, upcoming, etc.)
2. Hover over a game card - verify the 4 collection action icons appear
3. Click the gamepad icon (Playing) - verify it highlights green
4. Click the trophy icon (Played) - verify it highlights blue, gamepad unhighlights
5. Click the heart icon (Wishlist) - verify it highlights red (independent of status)
6. Click the same status icon again to remove the status
7. Test on mobile by tapping the card

#### 3. Adding Games via Game Detail Page
1. Open any game detail page (`/game/{slug}`)
2. On desktop: verify the sidebar shows 4 collection action buttons
3. On mobile: verify the sticky bottom bar appears with labeled buttons
4. Click buttons and verify they toggle correctly

#### 4. Adding Games via Search (Owner Page)
1. On your My Games page, use the search bar at the top
2. Search for a game and add it
3. Verify it appears in the current filter tab

#### 5. Filter & Sort
1. Add games with different statuses (playing, played, backlog, wishlisted)
2. Click each filter tab and verify only matching games appear
3. Test each sort option
4. Toggle between Grid and List views

#### 6. Collection Settings
1. Click the gear icon on your My Games banner
2. Change the collection name and description
3. Upload a cover imagecvarandas's Games
4. Toggle privacy settings (e.g., uncheck "Playing")
5. Save and verify changes appear on the My Games page
6. Visit your games page as a logged-out user and verify private sections return 403

#### 7. API (for developers)
```bash
# Check game status (authenticated)
GET /api/user-games/status/{game_id}

# Add game
POST /api/user-games { game_id, status?, is_wishlisted? }

# Update
PATCH /api/user-games/{id} { status?, is_wishlisted?, time_played?, rating? }

# Remove
DELETE /api/user-games/{id}
```

#### 8. Header Navigation
1. Verify the mobile and desktop dropdown menus show "My Games" instead of Backlog/Wishlist/My Lists
2. Click "My Games" and verify it navigates to `/u/{username}/games`

#### 9. Admin System Lists
1. As admin, open a game detail page
2. Verify the "Add to List" section still appears for system lists (yearly, seasoned, events)
3. Verify non-admin users do NOT see this section
