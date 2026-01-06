# Specification

This file contains the specification for the project, including requirements, design decisions, and any other relevant information.


---

## Auth Routes Rework - Game Lists Management

### Requirements

**Do NOT touch:**
- Login/register/password recovery modals or pages
- Profile page functionality (keep as-is)
- Public list viewing routes (`/list/{type}/{slug}`)

**Changes only affect:**
- Game list management for authenticated users
- Backlog and wishlist management
- Admin system list management

### URL Structure

**Public Routes (Keep Unchanged):**
- `GET /list/{type}/{slug}` - Public read-only list view

**User Viewing (Read-only, change the routes only):**
- `GET /u/{userId}/backlog` - Simplified backlog viewing
- `GET /u/{userId}/wishlist` - Simplified wishlist viewing

**User Management Routes:**
- Prefix: `/u/{userId}`
- `/u/{userId}/backlog` - Manage backlog (no slug, one per user)
- `/u/{userId}/wishlist` - Manage wishlist (no slug, one per user)
- `/u/{userId}/regular` - List all regular lists
- `/u/{userId}/regular/create` - Create regular list
- `/u/{userId}/regular/{slug}/edit` - Edit regular list

**Admin Management Routes:**
- Prefix: `/admin`
- `/admin/my-lists` - Admin's own lists (grouped by type)
- `/admin/system-lists` - All system lists (grouped by monthly/indie/seasoned)
- `/admin/user-lists` - All users' lists (sorted by username)

### Middleware Requirements

**User Management:**
- `auth` - Authentication required
- `prevent-caching` - No browser caching

**Admin Management:**
- `auth` - Authentication required
- `EnsureAdminUser` - Admin verification
- `prevent-caching` - No browser caching

### Page Layout Requirements

**Management Page Components:**
1. **View Toggle** - Grid/List layouts (session-based persistence)
2. **Game Search** - Top section IGDB search bar for adding games
3. **Breadcrumb Navigation** - Clear page hierarchy
4. **Game Count Display** - Show total games in list
5. **Mobile Responsive** - Full mobile support

**Management Game Card Component:**
- New component: `game-card-management.blade.php`
- Separate from frontend cards to allow independent changes
- Features:
  - Remove button (X icon)
  - Drag-and-drop handle for reordering
  - Click to view details modal
  - Inline edit for pivot data (release date, platforms)
  - Supports both grid and list layouts

### Management Capabilities

**List Metadata Editing:**
- Name, description, slug
- Public/private visibility
- Admin-only: System list flag, start_at, end_at dates
- Use dedicated edit pages (not inline)

**Game Management:**
- Add games via IGDB search (reuse existing search component)
- Remove games with confirmation
- Reorder via drag-and-drop (auto-save to database)
- Edit game-specific data (pivot: release_date, platforms)

### Admin Features

**Admin List Organization:**

1. **My Lists Page** (`/admin/my-lists`)
   - Admin's own regular lists (alphabetically, paginated)
   - Admin's backlog (single item)
   - Admin's wishlist (single item)

2. **System Lists Page** (`/admin/system-lists`)
   - Monthly lists (by start_at desc)
   - Indie Games lists (alphabetically)
   - Seasoned lists (alphabetically)
   - Filters: Active/Inactive, Date range, Public/Private

3. **User Lists Page** (`/admin/user-lists`)
   - Sorted by username alphabetically
   - Shows each user's backlog, wishlist, regular lists
   - Filters: Username search, List type, Visibility, Date created/updated
   - Traditional pagination

**Admin Permissions:**
- Full edit access to any user's list
- Can override visibility settings
- Can delete any list
- Exclusive access to create/edit system lists

### Navigation Structure

**User Menu Dropdown:**
- Backlog → `/u/{userId}/backlog` (read-only view)
- Wishlist → `/u/{userId}/wishlist` (read-only view)
- My Lists → `/u/{userId}/regular` (management area)
- Profile → (unchanged)
- Logout → (unchanged)

**Admin Menu Additions:**
- My Lists → `/admin/my-lists`
- System Lists → `/admin/system-lists`
- User Lists → `/admin/user-lists`

### Technical Implementation Notes

**View Mode Persistence:**
- Store in session: `session(['game_view_mode' => 'grid'])`
- Apply globally across all management pages
- Default to grid view

**Drag-and-Drop Ordering:**
- Use SortableJS library
- Auto-save on drop (AJAX)
- Update `order` in `game_list_game` pivot table
- Optimistic UI updates with error handling

**Game Addition:**
- Top section search bar
- Inline results display
- Add button per result
- AJAX call to add game
- Success toast notification

### Edit Route Strategy

**User Lists:**
- Use `/u/{userId}/{type}/{slug}/edit`
- Type can be: backlog, wishlist, regular

**System Lists (Admin):**
- Use `/admin/system-lists/{type}/{slug}/edit`
- Type can be: monthly, indie-games, seasoned

**User Lists (Admin Override):**
- Use `/u/{userId}/{type}/{slug}/edit` (same as regular users)
- Permissions checked in controller



