# My Games Collection - Feature Specification

## Overview

Replace the existing per-user Backlog, Wishlist, and Custom Lists (REGULAR type) with a single unified **"My Games"** collection system. Each user has one personal game collection where games can have a **status** (playing, played, backlog), an independent **wishlist flag**, **time played**, and a **rating**. The collection is fully customizable with a display name, bio, and cover image.

This feature also introduces collection controls on game cards across the site, on the game detail page, and removes the old list infrastructure for user list types (REGULAR, BACKLOG, WISHLIST).

---

## 1. Data Model

### 1.1 New Model: `UserGameCollection`

Represents the user's customizable collection metadata. Created lazily on first game add.

**Table: `user_game_collections`**

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| id | bigint (PK) | no | auto | Primary key |
| user_id | foreignId | no | - | FK to users, unique |
| name | string(255) | no | `"{username}'s Games"` | Custom collection display name |
| description | text | yes | null | Bio/description |
| cover_image_path | string(255) | yes | null | Path to uploaded banner image |
| privacy_playing | boolean | no | true | Public visibility for playing status |
| privacy_played | boolean | no | true | Public visibility for played status |
| privacy_backlog | boolean | no | true | Public visibility for backlog status |
| privacy_wishlist | boolean | no | true | Public visibility for wishlist flag |
| created_at | timestamp | no | - | |
| updated_at | timestamp | no | - | |

**Model:** `App\Models\UserGameCollection`
- `user()` → BelongsTo User
- `isStatusPublic(string $status): bool` — checks the corresponding privacy column

**User Model addition:**
- `gameCollection()` → HasOne UserGameCollection
- `getOrCreateGameCollection(): UserGameCollection` — lazy creation

### 1.2 New Model: `UserGame`

Represents a game in the user's collection with status, wishlist, time, rating, and ordering.

**Table: `user_games`**

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| id | bigint (PK) | no | auto | Primary key |
| user_id | foreignId | no | - | FK to users |
| game_id | foreignId | no | - | FK to games |
| status | string/enum | yes | null | playing, played, backlog (nullable for wishlist-only entries) |
| is_wishlisted | boolean | no | false | Independent wishlist flag |
| time_played | decimal(6,1) | yes | null | Hours played (decimal, e.g., 42.5) |
| rating | integer | yes | null | 1-100 scale (e.g., 75, 84, 97) |
| sort_order | integer | no | 0 | Per-status manual ordering |
| added_at | timestamp | no | - | When game was first added (never changes) |
| status_changed_at | timestamp | yes | null | Last time status was changed |
| wishlisted_at | timestamp | yes | null | Last time wishlist was toggled on |
| created_at | timestamp | no | - | |
| updated_at | timestamp | no | - | |

**Unique constraint:** `(user_id, game_id)`

**Indexes:**
- `(user_id, status)` — for filtered queries
- `(user_id, is_wishlisted)` — for wishlist queries
- `(user_id, status, sort_order)` — for ordered status views

**Model:** `App\Models\UserGame`
- `user()` → BelongsTo User
- `game()` → BelongsTo Game
- Scopes: `playing()`, `played()`, `backlog()`, `wishlisted()`, `withStatus($status)`

### 1.3 New Enum: `UserGameStatusEnum`

**File:** `app/Enums/UserGameStatusEnum.php`

```
PLAYING = 'playing'
PLAYED = 'played'
BACKLOG = 'backlog'
```

Methods: `label()`, `icon()`, `colorClass()`

### 1.4 User Model Changes

Add relationships:
- `userGames()` → HasMany UserGame
- `gameCollection()` → HasOne UserGameCollection

Remove methods:
- `getOrCreateBacklogList()`
- `getOrCreateWishlistList()`
- `ensureSpecialLists()`
- `gameLists()` relationship (only if no system lists reference it — verify; likely keep for admin)

### 1.5 ListTypeEnum Changes

Remove cases:
- `REGULAR`
- `BACKLOG`
- `WISHLIST`

Remaining cases: `YEARLY`, `SEASONED`, `EVENTS`

Update all methods: `label()`, `colorClass()`, `isUniquePerUser()`, `isSystemListType()`, `fromValue()`, `toSlug()`, `fromSlug()`

Remove `isUniquePerUser()` entirely if it only applied to backlog/wishlist.

---

## 2. Routes

### 2.1 New Routes

```
GET    /u/{username}/games                    → UserGameController@index       (name: user.games)
GET    /u/{username}/games/settings           → UserGameController@settings    (name: user.games.settings)
PATCH  /u/{username}/games/settings           → UserGameController@updateSettings
POST   /u/{username}/games                    → UserGameController@store       (name: user.games.store)
PATCH  /u/{username}/games/{userGame}         → UserGameController@update      (name: user.games.update)
DELETE /u/{username}/games/{userGame}         → UserGameController@destroy     (name: user.games.destroy)
PATCH  /u/{username}/games/reorder            → UserGameController@reorder     (name: user.games.reorder)

POST   /api/user-games                        → Api\UserGameController@store   (quick add from card icon)
PATCH  /api/user-games/{userGame}             → Api\UserGameController@update  (popover updates)
DELETE /api/user-games/{userGame}             → Api\UserGameController@destroy (remove from popover)
GET    /api/user-games/status/{game}          → Api\UserGameController@status  (get current status for a game)
```

### 2.2 Removed Routes

All routes under:
- `GET /u/{username}/backlog`
- `GET /u/{username}/wishlist`
- `GET /u/{username}/lists` (and all sub-routes: create, store, show, update, delete)
- `POST /u/{username}/{type}/games` (old add-game-to-list)
- `DELETE /u/{username}/{type}/games/{game}` (old remove)
- `PATCH /u/{username}/{type}/games/reorder` (old reorder)

These return 404 — no redirects.

### 2.3 URL Filtering (Hybrid)

The `/u/{username}/games` route accepts query parameters:
- `?status=playing` — filter by status
- `?wishlist=1` — filter by wishlist flag
- `?sort=date_added|alpha|release_date|time_played|rating` — sort option

Default (no params): shows `status=playing` filter.

Client-side: subsequent filter changes use Alpine.js with `history.pushState()` to update URL without page reload. Initial load is server-rendered.

---

## 3. Controllers

### 3.1 `UserGameController`

**index(Request $request, string $username)**
- Load user by username
- Check privacy settings for requested status filter
- Return games filtered by status/wishlist with eager loading
- Pass collection metadata, stats summary, sort options
- Default filter: `playing`

**settings(string $username)**
- Owner only (middleware)
- Show settings form: collection name, bio, cover upload, privacy toggles

**updateSettings(Request $request, string $username)**
- Owner only
- Validate and update UserGameCollection fields
- Handle cover image upload (auto-crop to 16:5 banner ratio)

**store(Request $request, string $username)**
- Owner only
- Add game to collection (from search on My Games page)
- Create UserGameCollection lazily if first game
- Set initial status, wishlist, time, rating

**update(Request $request, string $username, UserGame $userGame)**
- Owner only
- Update status, wishlist, time_played, rating
- Update status_changed_at / wishlisted_at timestamps as needed

**destroy(string $username, UserGame $userGame)**
- Owner only
- Remove game from collection entirely

**reorder(Request $request, string $username)**
- Owner only
- Accept array of `{id, sort_order}` pairs
- Update sort_order per-status

### 3.2 `Api\UserGameController`

Handles AJAX requests from game card popovers across the site.

**store(Request $request)**
- Auth required
- Accept: `game_id`, `status` (nullable), `is_wishlisted` (boolean)
- Create UserGame record + lazy-create UserGameCollection
- Return JSON with new UserGame data

**update(Request $request, UserGame $userGame)**
- Owner only
- Accept: `status`, `is_wishlisted`, `time_played`, `rating`
- Handle timestamp updates
- Return JSON with updated UserGame data

**destroy(UserGame $userGame)**
- Owner only
- Delete UserGame record
- Return JSON success

**status(Game $game)**
- Auth required
- Return the current user's UserGame for this game (or null)
- Used to populate popover state on any page

---

## 4. UI/UX

### 4.1 Game Card Icons

**Location:** Integrated into the existing quick-actions area on game cards.

**Icons (gaming metaphors):**
- **Played:** Trophy/checkmark icon
- **Playing:** Gamepad/controller icon
- **Backlog:** Clock/queue icon
- **Wishlist:** Star or heart icon

**Behavior:**
- **Desktop:** Icons appear on hover (same as current quick actions). Smaller than current icons.
- **Mobile:** A dedicated small button (e.g., `+` or `...`) always visible on the card. Tapping it opens the popover.
- **Guest users:** Icons appear on hover. Clicking redirects to login page with a return URL.

**Click behavior (authenticated):**
- If game is **NOT** in collection: clicking an icon **immediately adds** the game with that status/flag set. No popover. Toast feedback optional.
- If game is **already** in collection: clicking any icon opens the **popover** for editing.

**Active state:** When a game is in the user's collection, the active status icon and/or wishlist icon are highlighted/filled to indicate current state. This is visible on hover.

### 4.2 Game Card Popover

A small floating panel that appears when clicking an icon on a game already in the collection.

**Contents:**
- **Status buttons:** Three buttons (Playing / Played / Backlog). Active one is highlighted. Clicking a different one changes status immediately (no confirmation). Clicking the active one deselects it (sets status to null — game remains in collection if wishlisted).
- **Wishlist toggle:** Independent checkbox/toggle. Can be on/off regardless of status.
- **Time played:** Small decimal input field (e.g., `42.5`). Only shown/relevant when status is played or playing. Displayed as formatted "42h 30m" but input accepts decimals.
- **Rating:** Numeric input 1-100. Shown for all statuses.
- **Remove:** A small "Remove from My Games" link at the bottom. Clears everything.

**Save behavior:** All changes save via AJAX immediately on interaction (no submit button). Debounce the time/rating inputs.

### 4.3 My Games Page (`/u/{username}/games`)

**Header area:**
- Custom collection name (editable by owner via settings)
- Bio/description text
- Cover image as banner background (uploaded by owner, auto-cropped to ~16:5 ratio)
- If no cover: fallback gradient or default banner

**Stats summary bar:**
- Total games count
- Per-status counts (e.g., "Playing: 5 | Played: 42 | Backlog: 23")
- Total hours played across all games
- Displayed below the header

**Filter tabs:**
- Tabs/pills: All | Playing | Played | Backlog | Wishlist
- Show count in each tab (e.g., "Playing (5)")
- Default: Playing
- Wishlist filter shows all wishlisted games regardless of status
- Hybrid: initial server-rendered, subsequent changes via Alpine.js with pushState

**Sort options:**
- Dropdown: Date Added (default) | Alphabetical | Release Date | Time Played | Rating
- Manual drag-to-reorder within each status (when sorted by manual order)

**Add Game:**
- "Add Game" button opens a lightweight search component
- New Vue component reusing the existing IGDB search API (`/api/search`)
- Search results shown in dropdown, clicking a result opens the popover to set initial status/wishlist/time/rating before adding

**View toggle:**
- Grid / List view toggle (reuse existing toggle mechanism)
- Grid: game cards with status + time badges
- List: table rows with all metadata visible

**Game cards on this page show:**
- Small status tag badge (e.g., "Playing" in colored pill)
- Time played badge (e.g., "42h") if applicable
- Rating badge if set
- Standard game card info (cover, name, platforms, release date)

**Settings (owner only):**
- Accessible via settings icon/button on the page
- Edit: collection name, bio, cover image upload
- Privacy toggles: per-status public/private switches (default: all public)

**Empty state:**
- When no games in collection: friendly message + prominent "Add Game" CTA

### 4.4 Auth Menu Changes

**Current menu items (authenticated):**
- Backlog → `/u/{username}/backlog`
- Wishlist → `/u/{username}/wishlist`
- My Lists → `/u/{username}/lists`

**New menu items (authenticated):**
- **My Games** → `/u/{username}/games` (single link replaces all three)
- [Admin section unchanged]
- Logout

**Icon for My Games:** Gamepad/controller icon.

### 4.5 Game Detail Page (`/game/{slug}`)

**Desktop: Integrated in header**
- Collection controls appear inline in the game's hero/header area
- Status buttons (Playing / Played / Backlog) next to or below the game title
- Wishlist toggle icon
- Time played input
- Rating input (1-100)
- If not in collection: "Add to My Games" CTA with status buttons
- If in collection: shows current state with edit controls

**Mobile: Sticky bottom bar**
- Fixed bar at the bottom of the viewport
- Contains: status buttons, wishlist toggle, time, rating in a compact layout
- Collapses/expands on tap if needed for space
- If not in collection: simplified "Add" buttons
- If in collection: shows current state with inline editing

**Guest users:** Show the controls but clicking prompts login.

---

## 5. Cover Image Upload

**Upload handling:**
- Accept: JPEG, PNG, WebP
- Max file size: 2MB
- Auto-resize to max width 1920px, maintain aspect ratio
- Auto-crop to 16:5 banner ratio (center crop)
- Store in: `storage/app/public/user-collections/{user_id}/` (or similar)
- Symlink via `storage:link`
- Delete old image when replaced

**Display:**
- Use `object-fit: cover` as additional CSS fallback
- Serve via public storage path

---

## 6. Code Removal

### 6.1 Files to Remove

**Controllers:**
- `app/Http/Controllers/UserListController.php` (entire file)

**Views:**
- `resources/views/user-lists/backlog.blade.php`
- `resources/views/user-lists/wishlist.blade.php`
- `resources/views/user-lists/lists/` (entire directory: index, create, show, etc.)

**Components (if exclusively used by old lists):**
- Review and remove any Blade components only used by the old user list system

### 6.2 Code to Modify

**ListTypeEnum:**
- Remove `REGULAR`, `BACKLOG`, `WISHLIST` cases
- Remove related methods: `isUniquePerUser()` if only for those types
- Update all other methods that reference removed cases

**GameList Model:**
- Remove: `isBacklog()`, `isWishlist()`, `isRegular()`
- Remove scopes: `scopeBacklog()`, `scopeWishlist()`, `scopeRegular()`, `scopeUserLists()`
- Keep: `isYearly()`, `isSeasoned()`, `isEvents()` and their scopes

**User Model:**
- Remove: `getOrCreateBacklogList()`, `getOrCreateWishlistList()`, `ensureSpecialLists()`
- Keep `gameLists()` only if still used for admin system list management
- Add: `userGames()`, `gameCollection()`

**Routes (web.php):**
- Remove all user list routes (backlog, wishlist, lists CRUD, game add/remove/reorder for user lists)
- Keep admin system list routes
- Add new user game routes

**Header component:**
- Replace 3 menu links with single "My Games" link

**Game card components:**
- Replace current backlog/wishlist quick actions with new 4-icon system
- Add popover component

**Admin user lists page:**
- Review `admin/user-lists` — may need updating or removal since REGULAR lists no longer exist. Keep if it should show UserGame collections instead.

### 6.3 Database

- Do NOT drop old tables (`game_lists`, `game_list_game`) — they are still used by YEARLY/SEASONED/EVENTS system lists
- Do NOT migrate old backlog/wishlist data (fresh start per user decision)
- Old user-created lists (REGULAR, BACKLOG, WISHLIST type records) can be cleaned up via a separate command or migration that deletes GameList records where `list_type IN ('regular', 'backlog', 'wishlist')`

---

## 7. Summary of Decisions

| Decision | Choice |
|----------|--------|
| Data model approach | New UserGame model (separate from GameList) |
| Time logging | Manual total hours (decimal) |
| Wishlist behavior | Independent flag (can coexist with any status) |
| Migration of old data | Fresh start, no migration |
| URL structure | /u/{user}/games with query param filters |
| Card interaction | Click icon = instant add if not in collection; popover if already in collection |
| Default landing filter | "Playing" |
| Privacy | Per-status toggles, default all public |
| Cover image | User upload, auto-crop to 16:5 banner ratio |
| Icon style | Gaming metaphors (trophy, gamepad, clock, heart/star) |
| Icon visibility | Hover/tap only (dedicated mobile button) |
| Sorting | Per-status manual drag + auto sort options |
| Bulk actions | None |
| Remove action | Available in popover |
| Timestamps | added_at (immutable), status_changed_at, wishlisted_at |
| Public collection view | Full custom header |
| Card metadata on My Games | Status + time + rating badges |
| Add game search | New lightweight component, reuses IGDB search logic |
| Auth menu | Single "My Games" link replaces Backlog + Wishlist + My Lists |
| Guest experience | Icons show on hover, clicking prompts login |
| Custom lists (REGULAR) | Remove entirely |
| Old routes | Remove completely (404) |
| Rating | 1-100 integer scale |
| Game detail page (desktop) | Controls integrated in header |
| Game detail page (mobile) | Sticky bottom bar |
| Filtering method | Hybrid (server initial + client-side with pushState) |
| Status changes | Implicit, no confirmation |
| Time input format | Decimal hours (displayed as Xh Ym) |
| Stats | Summary bar with totals and per-status counts |
| Collection creation | Lazy on first game add |
| Popover contents | Status buttons, wishlist toggle, time input, rating, remove link |

---

## 8. Testing Requirements

### 8.1 Feature Tests

- **UserGame CRUD:** create, update, delete user games via API and web routes
- **Status transitions:** changing between playing/played/backlog, setting to null
- **Wishlist independence:** wishlisting with and without status, removing wishlist without affecting status
- **Privacy:** verify per-status privacy settings block/allow public access
- **Collection creation:** lazy creation on first game add
- **Cover upload:** upload, replace, delete cover image; file size and type validation
- **Reorder:** per-status drag reorder, verify orders are independent
- **Rating:** valid range 1-100, null allowed
- **Time played:** decimal input, null allowed
- **Auth menu:** verify single "My Games" link renders correctly
- **Guest access:** verify login redirect when clicking icons as guest
- **Owner-only actions:** verify non-owners cannot modify another user's collection
- **Settings update:** name, bio, privacy toggles
- **Sort options:** verify each sort option returns correct order
- **Timestamps:** verify added_at immutability, status_changed_at updates on change, wishlisted_at updates on toggle

### 8.2 Unit Tests

- `UserGameStatusEnum` methods
- `UserGameCollection` privacy check methods
- `UserGame` model scopes
- Rating/time validation logic

---

## 9. Implementation Order (Suggested Phases)

### Phase 1: Data Layer
1. Create `user_game_collections` migration + model
2. Create `user_games` migration + model
3. Create `UserGameStatusEnum`
4. Add relationships to User and Game models
5. Create factories and seeders
6. Write model/unit tests

### Phase 2: API Layer
1. Create `Api\UserGameController` (store, update, destroy, status)
2. Create Form Request classes for validation
3. Write API feature tests

### Phase 3: My Games Page
1. Create `UserGameController` (index, settings, updateSettings, store, update, destroy, reorder)
2. Create My Games Blade view with header, stats bar, filters, grid/list
3. Create settings panel/modal
4. Build lightweight search Vue component (reuse IGDB search)
5. Implement hybrid filtering with Alpine.js + pushState
6. Implement drag-to-reorder
7. Cover image upload handling
8. Write feature tests

### Phase 4: Game Card Integration
1. Create popover Blade/Alpine component
2. Update game card component with 4 status icons
3. Mobile dedicated button integration
4. Guest login prompt behavior
5. Write feature tests

### Phase 5: Game Detail Page
1. Add collection controls to game detail header (desktop)
2. Add sticky bottom bar (mobile)
3. Write feature tests

### Phase 6: Code Cleanup
1. Remove old routes (backlog, wishlist, custom lists)
2. Remove UserListController
3. Remove old Blade views
4. Clean up ListTypeEnum (remove REGULAR, BACKLOG, WISHLIST)
5. Clean up GameList model (remove user-list methods/scopes)
6. Clean up User model (remove old list methods)
7. Update header component (single "My Games" link)
8. Migration to delete old REGULAR/BACKLOG/WISHLIST GameList records
9. Update/remove admin user-lists page
10. Run full test suite, fix broken tests