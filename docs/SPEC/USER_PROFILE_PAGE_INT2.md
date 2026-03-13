# User Games Profile Page — Iteration 2

## Summary of Changes

### 1. Cover Header Redesign
**File:** `resources/views/user-games/index.blade.php`

- **Increase cover height** to `h-56 md:h-72`
- **Lighter overlay**: change `bg-black/40` to `bg-black/20` on cover image
- **Remove settings gear** from cover (moves to toolbar)
- **Move all elements inside the cover**: avatar, title/description, stat cards — free-floating with drop shadows
- **Remove** the `-mt-12` overlap and the separate "Profile Section" div; everything goes inside the cover
- **Layout**: Left = avatar (bigger, ~w-28 h-28), Center-left = title + description, Right = stat cards row
- **Clean transition**: `py-6` gap between cover bottom and filter tabs (no negative margin)

### 2. Stat Cards (replace ring gauges)
**File:** `resources/views/user-games/partials/ring-gauge.blade.php` → **DELETE** this file
**New file:** `resources/views/user-games/partials/stat-card.blade.php`

Each stat card is a small box with:
- `bg-black/40 backdrop-blur-sm` background, rounded corners
- Colored icon (status color) + bold count number + small label
- **Mini horizontal progress bar** (proportional to total games), colored per status
- **Percentage text** below the bar in tiny text
- **CSS animation**: bar width transitions from 0 → target over 0.5s on load

**5 status stat cards:**
| Stat | Color | Icon | Has progress bar? |
|------|-------|------|-------------------|
| Playing | green | gamepad | Yes (proportional + %) |
| Played | purple | trophy | Yes (proportional + %) |
| Backlog | orange | clock | Yes (proportional + %) |
| Total Games | gray | gamepad/controller | No bar (just icon + number) |
| Hours | gray | clock | No bar (just icon + number) |

**Wishlist card**: heart icon + count + "Wishlist" label, red accent, **no progress bar**

**Mobile (< md)**: Collapse to colored dots + numbers in a row. Show 3 primary (Total, Playing, Played). Expandable chevron reveals Backlog, Hours, Wishlist.

### 3. Fix Filter Tab Dots (Tailwind purge issue)
**File:** `resources/views/user-games/index.blade.php`

Switch from dynamic `bg-{{ $filter['color'] }}-500` to full static class names in the PHP array:
```php
$filters = [
    ['key' => 'all', 'label' => 'All', 'count' => $stats['total'], 'dot' => null],
    ['key' => 'playing', 'label' => 'Playing', 'count' => $stats['playing'], 'dot' => 'bg-green-500'],
    ['key' => 'played', 'label' => 'Played', 'count' => $stats['played'], 'dot' => 'bg-purple-500'],
    ['key' => 'backlog', 'label' => 'Backlog', 'count' => $stats['backlog'], 'dot' => 'bg-orange-500'],
];
```
- "All" tab gets **no dot**
- Render dot only when `$filter['dot']` is not null

### 4. Settings Button → Toolbar
**File:** `resources/views/user-games/index.blade.php`

- **Remove** the gear icon from the cover
- **Add** a "Settings" button (gear icon + "Settings" text) in the toolbar row, positioned before the Sort button
- Same style as Sort button: `bg-gray-800 rounded-lg text-sm text-gray-300 hover:bg-gray-700`
- Only visible when `$isOwner`

### 5. List View Changes
**File:** `resources/views/user-games/index.blade.php` (list view section)

**Column order change:**
`[Drag] | Game | Wishlist | Status | Time | Rating | [Edit]`

- **Remove** "Added" column entirely
- **Add Wishlist column** after Game: header "Wishlist" (hidden md:table-cell), shows filled red heart only when wishlisted, empty cell otherwise. Add `data-wishlist-cell` for JS updates.
- **Status column**: remove wishlist heart from status cell (it moves to dedicated column)
- **Cover size**: increase from `w-12 h-16` to `w-14 h-[4.5rem]`
- **Release date**: change from year-only (`2024`) to full date (`Mar 15, 2024`) using `->format('M j, Y')`
- **Edit button**: change from small icon to orange outline button style — `border border-orange-500 text-orange-500 hover:bg-orange-500 hover:text-white p-2 rounded-lg transition`
- **Row padding** stays at `py-4` (already set)

### 6. Edit Modal — Fix Cover Display
**File:** `resources/views/user-games/partials/edit-modal.blade.php`

- Add placeholder for missing covers: when `gameCover` is empty/null, show a gray box with gamepad icon
- Use `x-show="gameCover"` on the img, and a placeholder div with `x-show="!gameCover"`
- Also add `onerror` handler to fall back to placeholder on broken URLs

### 7. JS Color Map Updates for List Wishlist Cell
**File:** `resources/js/components/user-game-edit-modal.js`

- In `_updateCard()`: update the new `[data-wishlist-cell]` element (show/hide heart)
- Ensure `_updateCard` handles the list view time cell properly (it now has an icon inside, so use innerHTML instead of textContent)

### 8. Build & Test
- `npm run build` (with nvm use 24)
- `vendor/bin/pint --dirty`
- Update existing tests if needed
- Run full test suite

---

## Files Modified

| File | Action |
|------|--------|
| `resources/views/user-games/index.blade.php` | Major: cover header, filter dots, settings button, list columns, edit button |
| `resources/views/user-games/partials/stat-card.blade.php` | **New**: stat card component |
| `resources/views/user-games/partials/ring-gauge.blade.php` | **Delete** |
| `resources/views/user-games/partials/edit-modal.blade.php` | Cover placeholder fallback |
| `resources/js/components/user-game-edit-modal.js` | Wishlist cell update, time cell innerHTML |
