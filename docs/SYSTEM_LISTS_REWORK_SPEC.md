# System Lists Rework Specification

## Overview

This document specifies the rework of system lists (admin-curated game lists) to include advanced filtering, improved UI/UX, SEO optimization, and social sharing capabilities.

---

## 1. Core Architecture

### 1.1 Tech Stack

| Layer | Technology | Rationale |
|-------|------------|-----------|
| Backend | Laravel (existing) | Maintains consistency |
| Templates | Blade | SEO-friendly server rendering |
| Interactivity | Alpine.js | Lightweight, no build step changes |
| Data Fetching | Fetch API | Client-side filtering after initial load |
| Styling | Tailwind CSS (existing) | Consistent with current design |

### 1.2 Data Model Changes

#### GameList Model (Existing - Enhanced)

```php
// New/modified columns on game_lists table
'og_image_path'     => 'string|nullable',      // Path to uploaded OG image
'sections'          => 'json|nullable',        // Optional named sections
'auto_section_by_genre' => 'boolean|default:true', // Auto-group by genre if no sections
'tags'              => 'json|nullable',        // Flexible tagging for categorization
```

#### Sections JSON Structure

```json
{
  "sections": [
    {
      "id": "uuid",
      "name": "Day 1 Announcements",
      "order": 1,
      "game_ids": [1, 2, 3]
    },
    {
      "id": "uuid",
      "name": "Most Anticipated",
      "order": 2,
      "game_ids": [4, 5, 6]
    }
  ]
}
```

**Section Logic:**
- If `sections` JSON is populated: use admin-defined sections
- If `sections` is null/empty AND `auto_section_by_genre` is true: compute sections from game genres at render time
- If both are empty/false: render as flat list

#### Tags JSON Structure

```json
{
  "tags": ["showcase", "2026", "summer", "featured"]
}
```

Tags extend the existing `ListTypeEnum` for flexible categorization without replacing it.

#### Pivot Table (game_list_game) - No Changes

Keep existing pivot columns:
- `order` - Manual sort order (admin drag-drop)
- `release_date` - List-specific release date override
- `platforms` - List-specific platform override

**Sort Priority:**
1. Pivot `release_date` (if set)
2. Game `first_release_date` (fallback)
3. Pivot `order` (admin manual ordering)

---

## 2. URL Structure & Routing

### 2.1 Existing Routes (Preserved)

```
GET /lists/{type}/{slug}    → GameListController@showBySlug
```

Allowed types: `monthly`, `indie`, `seasoned`, `events`

### 2.2 Query Parameters (Major Filters - SEO/Shareable)

```
?platform=ps5,pc
?genre=action,rpg
?game_type=main,dlc
?mode=multiplayer
?perspective=first-person
```

### 2.3 Hash Fragments (Minor Preferences - Not Indexed)

```
#view=grid
#view=list
```

### 2.4 URL Hydration Flow

1. PHP controller parses query parameters
2. Passes initial filter state to Blade as JSON
3. Alpine reads initial state from Blade-injected data
4. URL updates via `history.pushState` on filter change

---

## 3. Filter System

### 3.1 Available Filters

| Filter | Type | Logic | Default |
|--------|------|-------|---------|
| Platform | Multi-select checkboxes | OR (any match) | All selected |
| Genre | Multi-select pills | OR (any match) | All selected |
| Game Type | Multi-select checkboxes | OR (any match) | All (including DLC) |
| Game Mode | Multi-select pills | OR (any match) | All selected |
| Player Perspective | Multi-select pills | OR (any match) | All selected |

### 3.2 Filter Behavior

- **OR Logic**: Game shows if it matches ANY selected value in a filter category
- **Cross-filter AND**: Game must match at least one value from EACH active filter category
- **Show counts**: Display game count next to each filter option (e.g., "Action (12)")
- **Empty filter handling**: If a filter option would yield 0 results, show count as (0) but keep selectable

### 3.3 Active Filter Pills

- Display selected filters as pills above the game grid
- Click on pill removes that specific filter value
- "Clear All" button when any filters active
- Example: `[PS5 ×] [Action ×] [RPG ×] [Clear All]`

---

## 4. UI Components

### 4.1 Page Layout

```
┌─────────────────────────────────────────────────────────────┐
│  Header: List Name + Description                            │
├─────────────────────────────────────────────────────────────┤
│  Stats Bar: "Showing 12 of 45 games | PS5: 8 | PC: 10 | ..." │
├─────────────────────────────────────────────────────────────┤
│  Active Filters: [PS5 ×] [Action ×] [Clear All]             │
├──────────────┬──────────────────────────────────────────────┤
│              │  View Toggle: [Grid] [List]                  │
│   Filter     ├──────────────────────────────────────────────┤
│   Sidebar    │                                              │
│              │  Game Grid / List                            │
│   - Platform │                                              │
│   - Genre    │  ┌─────┐ ┌─────┐ ┌─────┐                    │
│   - Type     │  │Game │ │Game │ │Game │                    │
│   - Mode     │  │Card │ │Card │ │Card │                    │
│   - Persp.   │  └─────┘ └─────┘ └─────┘                    │
│              │                                              │
└──────────────┴──────────────────────────────────────────────┘
```

### 4.2 Stats Bar (Dynamic)

Updates in real-time as filters change:

```html
<div class="stats-bar">
  <span>Showing <strong x-text="filteredCount">12</strong> of <strong>45</strong> games</span>
  <span class="platform-breakdown">
    PS5: <span x-text="platformCounts.ps5">8</span> |
    PC: <span x-text="platformCounts.pc">10</span> |
    Switch: <span x-text="platformCounts.switch">6</span>
  </span>
</div>
```

**Calculation**: On-the-fly in Alpine from filtered games array.

### 4.3 View Modes

#### Grid View (Default)
- Reuse existing `<x-game-card>` component
- 3 columns on desktop, 2 on tablet, 1 on mobile
- Shows: Cover image, name, platforms, release date, add-to-list action

#### List/Table View (Compact)
- Scannable rows with columns: Name, Platforms, Date, Actions
- Platform icons (hide on mobile < 640px)
- Add-to-list quick action button
- Enhance `<x-game-card>` with `variant="table-row"` prop

### 4.4 Sections Display

When sections are defined (admin or auto-genre):

```html
<section x-show="section.games.length > 0" x-for="section in sections">
  <h2 class="section-header" x-text="section.name">RPG Games</h2>
  <div class="game-grid">
    <!-- Game cards for this section -->
  </div>
</section>
```

### 4.5 Mobile Filter Overlay

- Trigger: Filter button in sticky header
- Behavior: Full-screen overlay with Alpine `x-show`
- Transitions: Tailwind transition classes (fade + slide)
- Close: X button or "Apply Filters" button
- Default state: Collapsed on mobile, expanded on desktop (no localStorage persistence)

```html
<div x-show="mobileFiltersOpen"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0 translate-y-full"
     x-transition:enter-end="opacity-100 translate-y-0"
     class="fixed inset-0 z-50 bg-slate-900 lg:hidden">
  <!-- Filter content -->
</div>
```

### 4.6 Empty State

When no games match current filters:

```html
<div class="empty-state text-center py-12">
  <p class="text-gray-400 mb-4">No games match your current filters.</p>
  <button @click="clearAllFilters()" class="btn-primary">
    Clear All Filters
  </button>
</div>
```

### 4.7 Loading States

- Brief skeleton animation during filter transitions
- Duration: 150-200ms (just enough for visual feedback)
- Implementation: CSS transition on opacity + skeleton placeholder

---

## 5. Admin Management

### 5.1 Admin Interface

Keep existing admin pattern at `/system-lists/...`:
- Create/Edit list metadata
- Add/Remove games (existing search logic)
- Drag-drop reorder games
- Upload OG image (new field)
- Define sections (new JSON editor or simple UI)
- Toggle `auto_section_by_genre` flag
- Manage tags

### 5.2 OG Image Upload

- Simple file upload field in admin form
- Storage: `storage/app/public/list-og-images/{list_id}.{ext}`
- Accepted formats: JPG, PNG, WebP
- Recommended size: 1200x630px (Open Graph standard)
- Fallback: If no custom image, use first game's cover

### 5.3 Sections Management

Option A (Simple): JSON textarea for power users
Option B (Preferred): UI with:
- "Add Section" button
- Section name input
- Drag games into sections
- Reorder sections

---

## 6. SEO Implementation

### 6.1 Meta Tags (Dynamic per List)

```html
<title>{{ $list->name }} - Full Games List | GamesOutbreak</title>
<meta name="description" content="Discover all {{ $list->games->count() }} games from {{ $list->name }}. Filter by platform, genre, and more.">
```

### 6.2 Open Graph Tags

```html
<meta property="og:title" content="{{ $list->name }} | GamesOutbreak">
<meta property="og:description" content="Browse {{ $list->games->count() }}+ games from {{ $list->name }}">
<meta property="og:image" content="{{ $list->og_image_url ?? $list->games->first()->getCoverUrl() }}">
<meta property="og:url" content="{{ route('lists.show', [$type, $list->slug]) }}">
```

### 6.3 Twitter Card

```html
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $list->name }}">
<meta name="twitter:description" content="Browse {{ $list->games->count() }}+ games">
<meta name="twitter:image" content="{{ $list->og_image_url }}">
```

### 6.4 JSON-LD Schema (Server-Side, Full List)

```html
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "ItemList",
  "name": "{{ $list->name }}",
  "description": "{{ $list->description }}",
  "url": "{{ url()->current() }}",
  "numberOfItems": {{ $list->games->count() }},
  "itemListElement": [
    @foreach($list->games as $index => $game)
    {
      "@type": "ListItem",
      "position": {{ $index + 1 }},
      "item": {
        "@type": "VideoGame",
        "name": "{{ $game->name }}",
        "genre": {!! json_encode($game->genres->pluck('name')) !!},
        "gamePlatform": {!! json_encode($game->platforms->pluck('name')) !!},
        "image": "{{ $game->getCoverUrl() }}",
        "url": "{{ route('games.show', $game->slug) }}"
      }
    }@if(!$loop->last),@endif
    @endforeach
  ]
}
</script>
```

**Note**: Schema always includes full list for SEO indexing, regardless of client-side filters.

### 6.5 Heading Hierarchy

```
H1: {{ $list->name }}
H2: Section names (e.g., "Action Games", "RPG Highlights")
H3: Not used in list view (game names are links, not headings)
```

---

## 7. Performance Considerations

### 7.1 Data Loading Strategy

- **Initial Load**: All games (50-100 max) loaded server-side
- **Images**: Lazy-loaded with `loading="lazy"` attribute
- **Filtering**: Pure client-side (Alpine.js array filtering)
- **No pagination**: Full list loaded upfront for instant filtering

### 7.2 Caching

- List metadata: Standard Laravel view caching
- Game data: Eager-loaded relationships (`platforms`, `genres`, `gameModes`, `playerPerspectives`)
- Stats: Calculated client-side, no server caching needed

### 7.3 Bundle Size

- Alpine.js: ~15KB gzipped (already included)
- No additional JS libraries required
- Filter logic: ~2-3KB custom Alpine component

---

## 8. Accessibility (Minimal)

### 8.1 Basic Requirements

- Semantic HTML (`<main>`, `<nav>`, `<section>`, `<article>`)
- ARIA labels on interactive elements
- Focus states on all clickable elements
- Skip-to-content link

### 8.2 Filter Accessibility

```html
<div role="group" aria-labelledby="platform-filter-label">
  <h3 id="platform-filter-label">Platform</h3>
  <label>
    <input type="checkbox" aria-describedby="ps5-count">
    PlayStation 5
    <span id="ps5-count" class="sr-only">(8 games)</span>
  </label>
</div>
```

### 8.3 Not Included (Deferred)

- Full WCAG 2.1 AA compliance
- Screen reader announcements on filter change
- Custom keyboard shortcuts

---

## 9. Component Enhancements

### 9.1 x-game-card Component

Add new props to existing component:

```php
@props([
    'game',
    'variant' => 'card',  // 'card' | 'table-row'
    'showActions' => true,
    'listContext' => null, // For add-to-list quick actions
])
```

### 9.2 New Alpine Component: ListFilter

```javascript
// resources/js/components/list-filter.js
document.addEventListener('alpine:init', () => {
    Alpine.data('listFilter', (initialGames, initialFilters) => ({
        games: initialGames,
        filters: {
            platforms: initialFilters.platforms || [],
            genres: initialFilters.genres || [],
            gameTypes: initialFilters.gameTypes || [],
            modes: initialFilters.modes || [],
            perspectives: initialFilters.perspectives || []
        },
        viewMode: 'grid', // or 'list'

        get filteredGames() {
            return this.games.filter(game => {
                // OR logic within each filter, AND across filters
                if (this.filters.platforms.length &&
                    !game.platforms.some(p => this.filters.platforms.includes(p.id))) {
                    return false;
                }
                // ... similar for other filters
                return true;
            });
        },

        get stats() {
            return {
                total: this.games.length,
                filtered: this.filteredGames.length,
                platforms: this.calculatePlatformCounts()
            };
        },

        toggleFilter(type, value) {
            const index = this.filters[type].indexOf(value);
            if (index > -1) {
                this.filters[type].splice(index, 1);
            } else {
                this.filters[type].push(value);
            }
            this.updateUrl();
        },

        clearAllFilters() {
            Object.keys(this.filters).forEach(key => {
                this.filters[key] = [];
            });
            this.updateUrl();
        },

        updateUrl() {
            const params = new URLSearchParams();
            // Add active filters to URL
            if (this.filters.platforms.length) {
                params.set('platform', this.filters.platforms.join(','));
            }
            // ... other filters

            const newUrl = `${window.location.pathname}?${params.toString()}#view=${this.viewMode}`;
            history.pushState({}, '', newUrl);
        }
    }));
});
```

---

## 10. Database Migrations

### 10.1 Add Columns to game_lists

```php
Schema::table('game_lists', function (Blueprint $table) {
    $table->string('og_image_path')->nullable()->after('description');
    $table->json('sections')->nullable()->after('og_image_path');
    $table->boolean('auto_section_by_genre')->default(true)->after('sections');
    $table->json('tags')->nullable()->after('auto_section_by_genre');
});
```

---

## 11. Routes (No Changes)

Existing routes remain unchanged:

```php
// Public
Route::get('/lists/{type}/{slug}', [GameListController::class, 'showBySlug']);

// Admin (existing)
Route::prefix('admin')->middleware(['auth', 'admin'])->group(function () {
    Route::resource('system-lists', AdminListController::class);
    // ... existing admin routes
});
```

---

## 12. Testing Requirements

### 12.1 Feature Tests

- [ ] List displays all games without filters
- [ ] Platform filter shows only matching games
- [ ] Genre filter shows only matching games
- [ ] Multiple filters combine correctly (AND across, OR within)
- [ ] Clear filters resets to full list
- [ ] URL params hydrate initial filter state
- [ ] Stats bar shows correct counts
- [ ] Sections render when defined
- [ ] Auto-genre sections work when enabled

### 12.2 Browser Tests (Dusk)

- [ ] Filter pills appear and can be clicked to remove
- [ ] View toggle switches between grid and list
- [ ] Mobile filter overlay opens and closes
- [ ] URL updates on filter change

---

## 13. Deferred Features (Future Versions)

The following features are explicitly deferred:

1. **Share My Hype** - User-generated shareable images
2. **Comparison Mode** - Side-by-side game comparison
3. **Search within list** - Name search box on list pages
4. **Scheduled publishing** - Timed list visibility
5. **Event model** - Formal event-list relationships
6. **Keyboard shortcuts** - Power user navigation
7. **Full WCAG compliance** - Comprehensive accessibility
8. **Index page** - Browsable list of all system lists
9. **Dynamic OG images** - Filter-specific social images

---

## 14. Implementation Phases

### Phase 1: Database & Backend
1. Run migration for new columns
2. Update GameList model with new casts/accessors
3. Update controller to parse query params and pass to view
4. Add OG image upload to admin

### Phase 2: Frontend Components
1. Create Alpine listFilter component
2. Enhance x-game-card with table-row variant
3. Build filter sidebar component
4. Build stats bar component
5. Build mobile filter overlay

### Phase 3: View Integration
1. Update lists/show.blade.php with new layout
2. Integrate Alpine component
3. Add JSON-LD schema
4. Add meta tags

### Phase 4: Testing & Polish
1. Write feature tests
2. Write browser tests
3. Performance testing with 100 games
4. Cross-browser testing
5. Mobile testing

---

## 15. Appendix: Filter Data Structure

### Games Array (Passed to Alpine)

```javascript
const games = [
    {
        id: 1,
        name: "Elden Ring 2",
        slug: "elden-ring-2",
        cover_url: "/storage/covers/...",
        release_date: "2026-03-15",
        platforms: [
            { id: 167, name: "PlayStation 5", slug: "ps5" },
            { id: 6, name: "PC (Windows)", slug: "pc" }
        ],
        genres: [
            { id: 12, name: "Action RPG" },
            { id: 31, name: "Soulslike" }
        ],
        game_type: { id: 0, name: "Main Game" },
        modes: [
            { id: 1, name: "Single player" },
            { id: 2, name: "Multiplayer" }
        ],
        perspectives: [
            { id: 1, name: "Third person" }
        ]
    },
    // ... more games
];
```

### Filter Options (Computed from Games)

```javascript
const filterOptions = {
    platforms: [
        { id: 167, name: "PlayStation 5", slug: "ps5", count: 23 },
        { id: 6, name: "PC (Windows)", slug: "pc", count: 31 },
        // ...
    ],
    genres: [
        { id: 12, name: "Action", count: 15 },
        { id: 31, name: "RPG", count: 8 },
        // ...
    ],
    // ... other filter types
};
```
