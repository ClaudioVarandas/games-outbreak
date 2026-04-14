# Theme Rework Alignment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Align the current homepage neon theme with the `docs/SPEC/THEME_REWORK/sketch.html` spec across 7 identified gaps: CSS token values, card hover effects, type pill styling, section icon shape, hero eyebrow colour, carousel sizing, and missing backdrop-filter on section frames.

**Architecture:** All changes are CSS-first (`resources/css/homepage.css`) except Gap 2 (type pills), which adds a `neonColorClass()` method to `GameTypeEnum` and updates the blade templates that render neon-variant cards and table rows to call it. No new components are created.

**Tech Stack:** Laravel 12 Blade, Tailwind CSS v3, vanilla CSS custom properties, Pest v3.

---

## Files Modified

| File | Why |
|---|---|
| `resources/css/homepage.css` | All CSS gaps (1, 3, 4, 5, 6, 7) and new `.neon-type-*` pill classes (Gap 2) |
| `app/Enums/GameTypeEnum.php` | Add `neonColorClass(): string` method |
| `resources/views/components/game-card.blade.php` | Use `neonColorClass()` for neon variant type pill |
| `resources/views/components/homepage/latest-added-table.blade.php` | Use `neonColorClass()` for type pill in table rows |
| `resources/views/components/game-carousel.blade.php` | Shrink carousel arrow SVG from `h-8 w-8` → `h-5 w-5` |
| `tests/Unit/GameTypeEnumNeonColorTest.php` | New unit test for `neonColorClass()` |

---

## Task 1: CSS Token Alignment (Gap 5)

**Files:**
- Modify: `resources/css/homepage.css`

- [ ] **Step 1: Update CSS custom properties in `.theme-neon`**

In `resources/css/homepage.css`, find the `.theme-neon { ... }` rule (lines 1–16) and replace the four token values:

```css
.theme-neon {
    --neon-bg: #121522;
    --neon-panel: rgba(24, 28, 46, 0.84);          /* was 0.74 */
    --neon-panel-strong: rgba(30, 35, 58, 0.94);    /* was rgba(34,40,62,0.86) */
    --neon-line: rgba(99, 243, 255, 0.18);           /* was 0.12 */
    --neon-line-strong: rgba(99, 243, 255, 0.34);    /* was 0.22 */
    --neon-text: #ecf8ff;
    --neon-muted: #8ea7b8;
    --neon-cyan: #63f3ff;
    --neon-orange: #ff8a2a;
    --neon-purple: #7c3aed;
    --neon-shadow: 0 0 0 1px rgba(99, 243, 255, 0.08), 0 22px 70px rgba(0, 0, 0, 0.48); /* was 0.06/0.42 */
    font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    color: var(--neon-text);
    position: relative;
}
```

- [ ] **Step 2: Align page shell and header/footer shell max-width**

Find `.page-shell` (line ~123) and `.site-header__shell, .site-footer__shell` (line ~53). Change both from `min(1500px, ...)` to `min(1380px, ...)`:

```css
.site-header__shell,
.site-footer__shell {
    width: min(1380px, calc(100% - 2rem));
    margin: 0 auto;
}

/* and */

.page-shell {
    width: min(1380px, calc(100% - 2rem));
    margin: 0 auto;
    max-width: 100%;
    padding: 0 0 0.5rem;
}
```

Also update the `@media (max-width: 767px)` rule that resets these (line ~505):
```css
.page-shell,
.site-header__shell,
.site-footer__shell {
    width: min(100% - 1rem, 1380px);
}
```

- [ ] **Step 3: Run the site and verify visually**

Run: `nvm use 24 && npm run build`

Open the homepage. Panels and borders should appear slightly more opaque/vivid than before. No layout should break.

- [ ] **Step 4: Commit**

```bash
git add resources/css/homepage.css
git commit -m "fix(theme): align CSS token values and page max-width with sketch spec"
```

---

## Task 2: Card Hover Effects (Gap 1)

**Files:**
- Modify: `resources/css/homepage.css`

- [ ] **Step 1: Strengthen `.neon-card` hover shadow and add scale**

Find the `.neon-card:hover` rule (~line 215) and replace it:

```css
.neon-card:hover {
    transform: translateY(-4px) scale(1.005);
    border-color: rgba(109, 40, 217, 0.34);
    box-shadow:
        0 0 0 1px rgba(255, 138, 42, 0.12),
        0 0 14px rgba(255, 138, 42, 0.14),
        0 0 24px rgba(109, 40, 217, 0.18),
        0 10px 22px rgba(109, 40, 217, 0.16);
}
```

- [ ] **Step 2: Fix transition duration**

Find the `transition:` line inside `.neon-card` (~line 190):
```css
.neon-card {
    /* ... existing props ... */
    transition: transform 250ms ease, border-color 250ms ease, box-shadow 250ms ease;
}
```

- [ ] **Step 3: Fix shimmer to use diagonal stripe approach matching spec**

Replace the `.neon-card::after` and `.neon-card:hover::after` rules:

```css
.neon-card::after {
    content: '';
    position: absolute;
    top: -120%;
    left: -40%;
    width: 42%;
    height: 260%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.08), transparent);
    transform: rotate(18deg);
    opacity: 0;
    transition: transform 0.5s ease, opacity 0.28s ease;
    pointer-events: none;
}

.neon-card:hover::after {
    opacity: 1;
    transform: translateX(260%) rotate(18deg);
}
```

- [ ] **Step 4: Verify hover visually**

Open the homepage, hover over any game card. You should see:
- Slight scale-up in addition to the lift
- Orange AND purple glow (not just purple)
- Shimmer stripe crossing the full card diagonally

- [ ] **Step 5: Commit**

```bash
git add resources/css/homepage.css
git commit -m "fix(theme): strengthen card hover — scale, dual glow, full-width shimmer"
```

---

## Task 3: Neon Type Pills (Gap 2)

**Files:**
- Modify: `app/Enums/GameTypeEnum.php`
- Modify: `resources/css/homepage.css`
- Modify: `resources/views/components/game-card.blade.php`
- Modify: `resources/views/components/homepage/latest-added-table.blade.php`
- Create: `tests/Unit/GameTypeEnumNeonColorTest.php`

- [ ] **Step 1: Write the failing unit test**

Create `tests/Unit/GameTypeEnumNeonColorTest.php`:

```php
<?php

use App\Enums\GameTypeEnum;

it('returns a neon CSS class name for every game type', function (GameTypeEnum $type, string $expectedClass) {
    expect($type->neonColorClass())->toBe($expectedClass);
})->with([
    'main'        => [GameTypeEnum::MAIN,        'neon-type-main'],
    'dlc'         => [GameTypeEnum::DLC,         'neon-type-dlc'],
    'expansion'   => [GameTypeEnum::EXPANSION,   'neon-type-expansion'],
    'bundle'      => [GameTypeEnum::BUNDLE,       'neon-type-bundle'],
    'standalone'  => [GameTypeEnum::STANDALONE,  'neon-type-standalone'],
    'mod'         => [GameTypeEnum::MOD,         'neon-type-mod'],
    'episode'     => [GameTypeEnum::EPISODE,     'neon-type-episode'],
    'season'      => [GameTypeEnum::SEASON,      'neon-type-season'],
    'remake'      => [GameTypeEnum::REMAKE,      'neon-type-remake'],
    'remaster'    => [GameTypeEnum::REMASTER,    'neon-type-remaster'],
]);
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test --compact tests/Unit/GameTypeEnumNeonColorTest.php
```

Expected: FAIL — "Call to undefined method App\Enums\GameTypeEnum::neonColorClass()"

- [ ] **Step 3: Add `neonColorClass()` to `GameTypeEnum`**

In `app/Enums/GameTypeEnum.php`, add after `colorClass()`:

```php
public function neonColorClass(): string
{
    return match ($this) {
        self::MAIN       => 'neon-type-main',
        self::DLC        => 'neon-type-dlc',
        self::EXPANSION  => 'neon-type-expansion',
        self::BUNDLE     => 'neon-type-bundle',
        self::STANDALONE => 'neon-type-standalone',
        self::MOD        => 'neon-type-mod',
        self::EPISODE    => 'neon-type-episode',
        self::SEASON     => 'neon-type-season',
        self::REMAKE     => 'neon-type-remake',
        self::REMASTER   => 'neon-type-remaster',
    };
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
php artisan test --compact tests/Unit/GameTypeEnumNeonColorTest.php
```

Expected: PASS (10 tests)

- [ ] **Step 5: Add neon type pill CSS classes to `homepage.css`**

Append to `resources/css/homepage.css` (before any `@media` blocks):

```css
/* ── Neon game-type pills ── */
.neon-type-pill {
    display: inline-flex;
    align-items: center;
    border-radius: 9999px;
    min-height: 22px;
    padding: 0 0.5rem;
    font-size: 0.66rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-width: 1px;
    border-style: solid;
}

.neon-type-main       { background: rgba(255, 138,  42, 0.18); color: #ffbe7b; border-color: rgba(255, 138,  42, 0.22); }
.neon-type-dlc        { background: rgba( 59, 130, 246, 0.18); color: #93c5fd; border-color: rgba( 59, 130, 246, 0.22); }
.neon-type-expansion  { background: rgba(139,  92, 246, 0.20); color: #c4b5fd; border-color: rgba(139,  92, 246, 0.24); }
.neon-type-bundle     { background: rgba(236,  72, 153, 0.18); color: #f9a8d4; border-color: rgba(236,  72, 153, 0.22); }
.neon-type-standalone { background: rgba( 16, 185, 129, 0.18); color: #86efac; border-color: rgba( 16, 185, 129, 0.22); }
.neon-type-mod        { background: rgba(234, 179,   8, 0.18); color: #fde68a; border-color: rgba(234, 179,   8, 0.22); }
.neon-type-episode    { background: rgba(  6, 182, 212, 0.18); color: #a5f3fc; border-color: rgba(  6, 182, 212, 0.22); }
.neon-type-season     { background: rgba(244, 114, 182, 0.18); color: #fbcfe8; border-color: rgba(244, 114, 182, 0.22); }
.neon-type-remake     { background: rgba(249, 115,  22, 0.18); color: #fdba74; border-color: rgba(249, 115,  22, 0.22); }
.neon-type-remaster   { background: rgba( 99, 102, 241, 0.20); color: #c7d2fe; border-color: rgba( 99, 102, 241, 0.24); }
```

- [ ] **Step 6: Update `game-card.blade.php` neon variant to use `neonColorClass()`**

In `resources/views/components/game-card.blade.php`, find the type pill in the `@if($layout === 'below' ...)` block (~line 324). The current code is:

```blade
<span class="{{ $game->getGameTypeEnum()->colorClass() }} {{ $variant === 'neon' ? 'rounded-full px-2.5 py-[0.28rem] text-[10px] font-semibold uppercase tracking-[0.06em]' : 'px-2 py-0.5 text-xs font-medium rounded' }} text-white">
    {{ $game->getGameTypeEnum()->label() }}
</span>
```

Replace with:

```blade
@if($variant === 'neon')
    <span class="neon-type-pill {{ $game->getGameTypeEnum()->neonColorClass() }}">
        {{ $game->getGameTypeEnum()->label() }}
    </span>
@else
    <span class="{{ $game->getGameTypeEnum()->colorClass() }} px-2 py-0.5 text-xs font-medium rounded text-white">
        {{ $game->getGameTypeEnum()->label() }}
    </span>
@endif
```

- [ ] **Step 7: Update `latest-added-table.blade.php` type pill**

In `resources/views/components/homepage/latest-added-table.blade.php`, find (~line 48):

```blade
<span class="{{ $game->getGameTypeEnum()->colorClass() }} mt-2 inline-flex rounded-full px-2 py-1 text-[11px] font-semibold uppercase tracking-[0.06em] text-white">
    {{ $game->getGameTypeEnum()->label() }}
</span>
```

Replace with:

```blade
<span class="neon-type-pill {{ $game->getGameTypeEnum()->neonColorClass() }} mt-2">
    {{ $game->getGameTypeEnum()->label() }}
</span>
```

- [ ] **Step 8: Run existing homepage tests**

```bash
php artisan test --compact tests/Feature/HomepagePageTest.php
```

Expected: all 6 tests PASS

- [ ] **Step 9: Build assets and verify visually**

```bash
nvm use 24 && npm run build
```

Open the homepage. Type pills on game cards and the latest-added table should now show transparent coloured backgrounds with matching borders and pastel text — matching the spec's neon visual language. The `table-row` variant, non-neon cards, and other pages are unchanged (they still use `colorClass()`).

- [ ] **Step 10: Commit**

```bash
git add app/Enums/GameTypeEnum.php \
        resources/css/homepage.css \
        resources/views/components/game-card.blade.php \
        resources/views/components/homepage/latest-added-table.blade.php \
        tests/Unit/GameTypeEnumNeonColorTest.php
git commit -m "feat(theme): add neonColorClass() and neon rgba type pills to match spec"
```

---

## Task 4: Section Heading Icon — Square Gradient Badge (Gap 3)

**Files:**
- Modify: `resources/css/homepage.css`

> **Note:** Keep the existing SVG icons from the project's icon library. Only the container shape and fill change — circle with transparent border → 34×34px square badge with orange→purple gradient, matching the spec's `section-icon` style.

- [ ] **Step 1: Restyle `.neon-section-heading__icon`**

In `resources/css/homepage.css`, find `.neon-section-heading__icon` (~line 301) and replace the entire rule:

```css
.neon-section-heading__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.125rem;        /* 34px — matches spec */
    height: 2.125rem;
    border-radius: 0.75rem; /* 12px square badge — matches spec */
    background: linear-gradient(135deg, var(--neon-orange), var(--neon-purple));
    box-shadow: 0 0 14px rgba(109, 40, 217, 0.22);
    border: 1px solid rgba(255, 255, 255, 0.08);
    color: #ffffff;
    flex-shrink: 0;
}
```

- [ ] **Step 2: Verify icons render correctly**

```bash
nvm use 24 && npm run build
```

Open the homepage. Each section heading icon should now be a small square with rounded corners and an orange→purple gradient background. The SVG strokes should be white (`color: #ffffff` inherits to `stroke="currentColor"`).

- [ ] **Step 3: Commit**

```bash
git add resources/css/homepage.css
git commit -m "fix(theme): reshape section icon to square gradient badge per spec"
```

---

## Task 5: Hero Eyebrow Colour (Gap 4)

**Files:**
- Modify: `resources/css/homepage.css`

- [ ] **Step 1: Update `.neon-eyebrow` to use cyan text and solid cyan dot**

In `resources/css/homepage.css`, find `.neon-eyebrow` and `.neon-eyebrow::before` (~lines 263–281). Replace both rules:

```css
.neon-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--neon-cyan);  /* was #9cb2c0 */
}

.neon-eyebrow::before {
    content: '';
    width: 0.5rem;
    height: 0.5rem;
    border-radius: 9999px;
    background: var(--neon-cyan);                   /* was gradient orange/purple */
    box-shadow: 0 0 12px var(--neon-cyan);           /* cyan glow, not orange */
    flex-shrink: 0;
}
```

- [ ] **Step 2: Verify in hero**

Open the homepage (hero section must be enabled: set `FEATURES_NEWS=true` in `.env` if needed, or check the config). The "Featured News" eyebrow label should now be cyan, with a glowing cyan dot — not muted grey.

- [ ] **Step 3: Commit**

```bash
git add resources/css/homepage.css
git commit -m "fix(theme): eyebrow colour — cyan text and dot to match spec"
```

---

## Task 6: Carousel Arrow & Edge Fade Sizing (Gap 6)

**Files:**
- Modify: `resources/css/homepage.css`
- Modify: `resources/views/components/game-carousel.blade.php`

- [ ] **Step 1: Resize carousel arrow button in CSS**

In `resources/css/homepage.css`, find `.neon-carousel-arrow` (~line 395). Change the dimensions from `3.75rem` to `2.375rem` (38px):

```css
.neon-carousel-arrow {
    position: absolute;
    top: 10.25rem;
    z-index: 60;
    height: 2.375rem;   /* was 3.75rem */
    width: 2.375rem;    /* was 3.75rem */
    transform: translateY(-50%);
    box-shadow:
        0 0 0 1px rgba(99, 243, 255, 0.18),
        0 0 20px rgba(0, 0, 0, 0.38);
}

@media (min-width: 768px) {
    .neon-carousel-arrow {
        top: 12.85rem;
    }
    /* keep left-3 / right-3 positioning as-is */
}
```

- [ ] **Step 2: Widen carousel edge fade strips**

In `resources/css/homepage.css`, find `.neon-release-carousel-edge` (~line 376). Change `width` from `1.7rem` to `2.875rem` (46px), and update the edge gradient colours to match spec:

```css
.neon-release-carousel-edge {
    position: absolute;
    top: 1.15rem;
    height: 19.25rem;
    width: 2.875rem;    /* was 1.7rem */
    z-index: 20;
    pointer-events: none;
    border-radius: 1.5rem;
}

.neon-release-carousel-edge--left {
    left: 0;
    background: linear-gradient(90deg, rgba(24, 28, 46, 0.92), rgba(24, 28, 46, 0));   /* was rgba(20,24,39,0.72) */
}

.neon-release-carousel-edge--right {
    right: 0;
    background: linear-gradient(270deg, rgba(24, 28, 46, 0.92), rgba(24, 28, 46, 0));  /* was rgba(20,24,39,0.72) */
}
```

- [ ] **Step 3: Shrink SVG icons inside carousel arrows**

In `resources/views/components/game-carousel.blade.php`, find both `<svg class="h-8 w-8"` occurrences (left and right arrow, lines ~32 and ~68) and change to `h-5 w-5`:

Left arrow (~line 32):
```blade
<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M15 19l-7-7 7-7"/>
</svg>
```

Right arrow (~line 68):
```blade
<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M9 5l7 7-7 7"/>
</svg>
```

- [ ] **Step 4: Run existing tests**

```bash
php artisan test --compact tests/Feature/HomepagePageTest.php
```

Expected: all PASS (carousel aria-labels are unchanged)

- [ ] **Step 5: Build and verify visually**

```bash
nvm use 24 && npm run build
```

Open the homepage upcoming releases carousel. Arrows should be noticeably smaller and more refined. Edge fades should be slightly wider and more opaque.

- [ ] **Step 6: Commit**

```bash
git add resources/css/homepage.css \
        resources/views/components/game-carousel.blade.php
git commit -m "fix(theme): resize carousel arrows (38px) and widen edge fades (46px) per spec"
```

---

## Task 7: Backdrop-Filter on Section Frames (Gap 7)

**Files:**
- Modify: `resources/css/homepage.css`

- [ ] **Step 1: Add backdrop-filter to `.neon-section-frame`**

In `resources/css/homepage.css`, find `.neon-section-frame` (~line 135). Add `backdrop-filter: blur(16px)` and `-webkit-backdrop-filter` to the rule:

```css
.neon-section-frame {
    position: relative;
    overflow: hidden;
    border-radius: 1.9rem;
    padding: 1.1rem;
    background:
        linear-gradient(180deg, rgba(25, 30, 47, 0.7), rgba(25, 30, 47, 0.62)),
        linear-gradient(140deg, rgba(255, 138, 42, 0.03), transparent 34%),
        linear-gradient(220deg, rgba(124, 58, 237, 0.05), transparent 36%);
    border: 1px solid rgba(99, 243, 255, 0.1);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    box-shadow:
        inset 0 0 0 1px rgba(99, 243, 255, 0.02),
        0 0 0 1px rgba(99, 243, 255, 0.03),
        0 14px 32px rgba(0, 0, 0, 0.24),
        0 0 12px rgba(99, 243, 255, 0.03);
}
```

- [ ] **Step 2: Build and verify visually**

```bash
nvm use 24 && npm run build
```

Open the homepage. Scroll past the hero — the section cards (This Week's Choices, Events, Upcoming, Latest) should now show frosted-glass depth over the grid background, instead of the background bleeding through cleanly.

- [ ] **Step 3: Commit**

```bash
git add resources/css/homepage.css
git commit -m "fix(theme): add backdrop-filter blur(16px) to section frames per spec"
```

---

## Verification Checklist

After all tasks, run the full homepage test suite:

```bash
php artisan test --compact tests/Feature/HomepagePageTest.php tests/Unit/GameTypeEnumNeonColorTest.php
```

Expected: all tests PASS.

Visual checklist (open homepage in browser):

- [ ] Panels and borders are noticeably more opaque/vivid than before
- [ ] Page content is constrained to ~1380px max (no wider than before at large viewports)
- [ ] Hovering a game card shows lift + scale + orange-and-purple dual glow + diagonal shimmer
- [ ] Type pills on game cards use transparent coloured backgrounds with pastel text (not solid bg-orange-600)
- [ ] Type pills in the Latest Added table match same neon style
- [ ] Section heading icons are small square gradient badges (not circles)
- [ ] SVG icons inside section heading badges are white
- [ ] "Featured News" eyebrow in hero is cyan with a glowing cyan dot
- [ ] Carousel prev/next arrows are smaller (≈38px)
- [ ] Carousel edge fades are wider and more opaque
- [ ] Section frames have frosted-glass effect over the grid background
