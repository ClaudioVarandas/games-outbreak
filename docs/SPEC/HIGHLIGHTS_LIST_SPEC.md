# Highlights List Type Specification

## Overview

A new system list type "Highlights" (Portuguese: "Destaques") for featuring curated games organized by platform groups.

## Platform Groups

Games in the Highlights list will be organized into these conceptual groups:

| Group Key | Label | Platforms Included (IGDB IDs) |
|-----------|-------|-------------------------------|
| `multiplatform` | Multiplatform | PC (6), PS5 (167), Xbox X/S (169), Switch (130), PS4 (48), Xbox One (49), Switch 2 (508) |
| `playstation` | PlayStation | PS4 (48), PS5 (167) |
| `nintendo` | Nintendo | Switch (130), Switch 2 (508) |
| `xbox` | Xbox | Xbox One (49), Xbox X/S (169) |
| `mobile` | Mobile | Android (34), iOS (39) |
| `pc` | PC Exclusive | PC (6), Linux (3), macOS (14) |

---

## Database Changes

### Option A: Pivot Table Column (Recommended)

Add `platform_group` column to `game_list_game` pivot table:

```php
// Migration
Schema::table('game_list_game', function (Blueprint $table) {
    $table->string('platform_group')->nullable()->after('platforms');
});
```

This allows manual assignment of platform group per game in the highlights list.

### New Enum: PlatformGroupEnum

```php
enum PlatformGroupEnum: string
{
    case MULTIPLATFORM = 'multiplatform';
    case PLAYSTATION = 'playstation';
    case NINTENDO = 'nintendo';
    case XBOX = 'xbox';
    case MOBILE = 'mobile';
    case PC = 'pc';
}
```

---

## ListTypeEnum Updates

```php
case HIGHLIGHTS = 'highlights';

// In label()
self::HIGHLIGHTS => 'Highlights',

// In colorClass()
self::HIGHLIGHTS => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',

// In isSystemListType()
self::HIGHLIGHTS => true,

// In toSlug()
self::HIGHLIGHTS => 'highlights',

// In fromSlug()
'highlights' => self::HIGHLIGHTS,
```

---

## Routes

```php
// Frontend
Route::get('/highlights', [HighlightsController::class, 'index'])->name('highlights');

// Admin (uses existing system-lists routes)
// /admin/system-lists/highlights/{slug}/edit
```

---

## UI Mockups

### Frontend Page: /highlights (Filter Tab Navigation)

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  [Header]                                                                    │
├─────────────────────────────────────────────────────────────────────────────┤
│  [Releases Nav: Highlights | Monthly | Indie | Seasoned]                    │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  ═══════════════════════════════════════════════════════════════════════════│
│  HIGHLIGHTS - January 2026                                                   │
│  Curated selection of featured games                                         │
│  ═══════════════════════════════════════════════════════════════════════════│
│                                                                              │
│  ┌─────────────────────────────────────────────────────────────────────────┐│
│  │ Platform Group Filter Tabs (click to filter):                           ││
│  │                                                                         ││
│  │  ┌─────────────────┐ ┌─────────────┐ ┌──────────┐ ┌──────┐ ┌────────┐  ││
│  │  │ Multiplatform   │ │ PlayStation │ │ Nintendo │ │ Xbox │ │ Mobile │  ││
│  │  │ (12) [ACTIVE]   │ │    (5)      │ │   (3)    │ │ (4)  │ │  (2)   │  ││
│  │  └─────────────────┘ └─────────────┘ └──────────┘ └──────┘ └────────┘  ││
│  └─────────────────────────────────────────────────────────────────────────┘│
│                                                                              │
│  ┌─────────────────────────────────────────────────────────────────────────┐│
│  │  ██ MULTIPLATFORM                                              12 games ││
│  │  ────────────────────────────────────────────────────────────────────── ││
│  │                                                                         ││
│  │  [Game Grid - 5 columns on desktop, 2 on mobile]                        ││
│  │  ┌─────┐ ┌─────┐ ┌─────┐ ┌─────┐ ┌─────┐                               ││
│  │  │Cover│ │Cover│ │Cover│ │Cover│ │Cover│                               ││
│  │  │Game1│ │Game2│ │Game3│ │Game4│ │Game5│                               ││
│  │  │Jan15│ │Jan20│ │Jan22│ │Jan25│ │Jan28│                               ││
│  │  └─────┘ └─────┘ └─────┘ └─────┘ └─────┘                               ││
│  │  ┌─────┐ ┌─────┐ ...                                                   ││
│  │  │Cover│ │Cover│                                                       ││
│  │  └─────┘ └─────┘                                                       ││
│  └─────────────────────────────────────────────────────────────────────────┘│
│                                                                              │
│  (Other platform groups are HIDDEN until their tab is clicked)              │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘

After clicking "PlayStation" tab:

┌─────────────────────────────────────────────────────────────────────────────┐
│  ...                                                                         │
│  ┌─────────────────────────────────────────────────────────────────────────┐│
│  │  ┌─────────────────┐ ┌─────────────┐ ┌──────────┐ ┌──────┐ ┌────────┐  ││
│  │  │ Multiplatform   │ │ PlayStation │ │ Nintendo │ │ Xbox │ │ Mobile │  ││
│  │  │     (12)        │ │ (5) [ACTIVE]│ │   (3)    │ │ (4)  │ │  (2)   │  ││
│  │  └─────────────────┘ └─────────────┘ └──────────┘ └──────┘ └────────┘  ││
│  └─────────────────────────────────────────────────────────────────────────┘│
│                                                                              │
│  ┌─────────────────────────────────────────────────────────────────────────┐│
│  │  ██ PLAYSTATION                                                 5 games ││
│  │  ────────────────────────────────────────────────────────────────────── ││
│  │                                                                         ││
│  │  ┌─────┐ ┌─────┐ ┌─────┐ ┌─────┐ ┌─────┐                               ││
│  │  │Cover│ │Cover│ │Cover│ │Cover│ │Cover│                               ││
│  │  │FF16 │ │GoW  │ │Spdr │ │GT7  │ │Hrzn │                               ││
│  │  └─────┘ └─────┘ └─────┘ └─────┘ └─────┘                               ││
│  └─────────────────────────────────────────────────────────────────────────┘│
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

**Tab Behavior:**
- Click a tab → shows only that platform group's games
- Active tab is visually highlighted (e.g., orange background)
- Tab shows game count in parentheses
- Empty groups are hidden from tabs
- Default: First non-empty group is selected (likely "Multiplatform")

---

### Admin: Edit Highlights List

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  Edit Highlights List: January 2026                                          │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  [List Settings - Name, Dates, Active, Public]                              │
│                                                                              │
│  ═══════════════════════════════════════════════════════════════════════════│
│  GAMES IN LIST                                                               │
│  ═══════════════════════════════════════════════════════════════════════════│
│                                                                              │
│  ┌───────────────────────────────────────────────────────────────┐          │
│  │ Filter by group: [All ▼] [Multiplatform] [PS] [Nintendo] ...  │          │
│  └───────────────────────────────────────────────────────────────┘          │
│                                                                              │
│  ┌─────────────────────────────────────────────────────────────────────────┐│
│  │ ┌───────┐  Game Name Here                                               ││
│  │ │[Cover]│  Platforms: PS5, Xbox X/S, PC, Switch                         ││
│  │ │       │  Release: Jan 15, 2026                                        ││
│  │ └───────┘                                                               ││
│  │                                                                         ││
│  │            Platform Group: [Multiplatform ▼]    [Remove]                ││
│  │                            ├─ Multiplatform                             ││
│  │                            ├─ PlayStation                               ││
│  │                            ├─ Nintendo                                  ││
│  │                            ├─ Xbox                                      ││
│  │                            ├─ Mobile                                    ││
│  │                            └─ PC Exclusive                              ││
│  └─────────────────────────────────────────────────────────────────────────┘│
│                                                                              │
│  ┌─────────────────────────────────────────────────────────────────────────┐│
│  │ ┌───────┐  Another Game                                                 ││
│  │ │[Cover]│  Platforms: PS5 only                                          ││
│  │ │       │  Release: Jan 20, 2026                                        ││
│  │ └───────┘                                                               ││
│  │            Platform Group: [PlayStation ▼]      [Remove]                ││
│  └─────────────────────────────────────────────────────────────────────────┘│
│                                                                              │
│  ═══════════════════════════════════════════════════════════════════════════│
│  ADD GAME                                                                    │
│  ═══════════════════════════════════════════════════════════════════════════│
│                                                                              │
│  [Search games...                                          ]                 │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## Implementation Tasks

### Phase 1: Backend Foundation
1. Create `PlatformGroupEnum` enum
2. Add migration for `platform_group` column in `game_list_game` pivot
3. Update `ListTypeEnum` with `HIGHLIGHTS` case
4. Update `GameList` model to handle platform groups in pivot

### Phase 2: Admin Interface
5. Update `AdminListController` to handle highlights list type
6. Update `admin/system-lists/index.blade.php` to show highlights lists
7. Update `admin/system-lists/edit.blade.php` to show platform group selector for highlights
8. Update game-grid component to show/edit platform group

### Phase 3: Frontend
9. Create `HighlightsController` with `index` method
10. Create `highlights/index.blade.php` view
11. Update `releases-nav.blade.php` to include Highlights link
12. Add route for `/highlights`

### Phase 4: Testing
13. Add tests for ListTypeEnum HIGHLIGHTS case
14. Add feature tests for highlights page
15. Add tests for admin platform group management

---

## Design Decisions (Confirmed)

1. **Single active highlights list** - Only one highlights list active at a time (like seasoned lists)

2. **Auto-suggest platform group** - When adding a game, suggest platform group based on platforms:
   - PS-only → "PlayStation"
   - Nintendo-only → "Nintendo"
   - Xbox-only → "Xbox"
   - Mobile-only → "Mobile"
   - PC/Linux/Mac only → "PC Exclusive"
   - Multiple major platforms → "Multiplatform"

3. **Order by release date** - Games ordered by:
   - First: `game_list_game.release_date` (pivot table)
   - Fallback: `games.first_release_date`

4. **Hide empty groups** - Platform groups with no games are not displayed
