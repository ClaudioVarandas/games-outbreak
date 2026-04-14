# Homepage Theme Rework — Dark Neon Cyberpunk Template

## Context

The homepage (`resources/views/homepage/index.blade.php`) is currently a stack of loosely-themed sections using generic orange/green/gray Tailwind classes and duplicated ornamental headings. The spec in `docs/SPEC/THEME_REWORK/THEME_UI_UX_REWORK.md` (with a visual reference at `docs/SPEC/THEME_REWORK/sketch.html`) asks for a **news-first gaming portal** with a dark neon cyberpunk aesthetic (orange + purple accents, glassmorphism panels, refined hover glow).

This is a **template rework**, not a product redesign. We keep existing data contracts, reuse the existing `<x-game-card>` and `<x-game-carousel>` components where possible, and replace the presentation layer of the homepage (plus minimally restyle `<x-header>` and `<x-footer>` so the whole page feels cohesive).

Key new requirement: **surface News on the homepage**. News currently lives at `/news` behind a feature flag and has never been shown on `/`. The hero in the new template (featured news + 3-4 compact news items) requires querying `News` from `HomepageController`. **The hero section itself is gated by the same `features.news` flag** via `EnsureNewsFeatureEnabled::isVisibleTo(...)` — when the flag is off (or no published news exists), the hero is not rendered at all and the page begins directly at "This Week's Choices". No fallback markup.

The expected outcome is a homepage that matches the section order, card hierarchy, and hover language of the sketch, while remaining responsive, accessible, and wired to existing data sources.

---

## Relevant existing files

**Controller / data**
- `app/Http/Controllers/HomepageController.php` — `index()` passes `$seasonedLists`, `$thisWeekGames`, `$weeklyUpcomingGames`, `$latestAddedGames`, `$platformEnums`, `$eventBanners`, `$currentYear`, `$currentMonth`.
- `app/Models/News.php` — has `scopePublished()`, `author()` relation, `image_url`, `formatted_published_at`, `reading_time` accessors.
- `app/Http/Middleware/EnsureNewsFeatureEnabled.php` — exposes `isVisibleTo(?User)`. Reuse for hero gating.
- `app/Enums/GameTypeEnum.php` — has `label()` (singular) and `colorClass()` helper.
- `app/Enums/PlatformEnum.php` — has `label()`, `color()`, `getActivePlatforms()`, `getPriority()`.

**Views to touch**
- `resources/views/homepage/index.blade.php` — replace content entirely.
- `resources/views/layouts/app.blade.php` — remove the `<x-releases-nav>` render on `/` (nav absorbed into new header).
- `resources/views/components/header.blade.php` — restyle to neon glass topbar; preserve `#app-search` / `#app-search-mobile` Vue mount points and auth modal triggers.
- `resources/views/components/footer.blade.php` — restyle to neon panel; preserve copyright, credit, attribution content.
- `resources/views/components/game-card.blade.php` — add a `neon` variant + continue honoring `layout="below"`; extend `colorClass()` pills already in use.
- `resources/views/components/game-carousel.blade.php` — add a `variant="neon"` prop to pass through to inner `<x-game-card>`, restyle arrows / fade masks.

**Reused as-is**
- `<x-game-card>` (with new `variant="neon" layout="below"`)
- `<x-game-carousel>` (for Upcoming Releases rail)
- `<x-seasonal-banners>` / `<x-seasonal-banner>` (for Events — retuned to wider banner feel)
- `<global-search>` Vue component, `AuthModals.js`, existing Alpine auth dropdown in header

**New files to create**
- `resources/views/components/homepage/hero.blade.php`
- `resources/views/components/homepage/hero-news-item.blade.php`
- `resources/views/components/homepage/this-week-choices.blade.php`
- `resources/views/components/homepage/events-grid.blade.php`
- `resources/views/components/homepage/upcoming-releases.blade.php`
- `resources/views/components/homepage/latest-added-table.blade.php`
- `resources/views/components/homepage/section-heading.blade.php`
- `resources/css/homepage.css` — scoped neon theme (backgrounds, grid scanlines, glow keyframes, shimmer pseudo-elements).
- Feature test: `tests/Feature/HomepagePageTest.php` (or update existing homepage test if present).

---

## Implementation steps

### 1. Data layer — surface News on the homepage

In `HomepageController::index()`:

- Compute `$newsEnabled = EnsureNewsFeatureEnabled::isVisibleTo(auth()->user())`.
- If `$newsEnabled`, query:
  ```php
  $news = News::published()->with('author')->orderByDesc('published_at')->limit(5)->get();
  $featuredNews = $news->shift(); // may be null if zero published
  $topNews = $news;                // up to 4
  ```
  Otherwise both are `null` / empty collection.
- The view renders the hero **only** when `$newsEnabled && $featuredNews !== null`. When the feature flag is off or there's no published news, the hero section does not render and the page starts at "This Week's Choices".
- Lower `getThisWeekGames()` limit from 12 → **10** (spec requires 10 cards).
- Keep `getWeeklyUpcomingGames()` at 18 — user confirmed preference for a denser rail than the 12 suggested by the spec.
- Keep all other methods untouched; keep returning `$seasonedLists` even though the current view drops it (already unused).

Pass `featuredNews`, `topNews`, and `$newsEnabled` into the view.

### 2. Design tokens & scoped CSS

Add a new file `resources/css/homepage.css` and import it from `resources/css/app.css` (bundled once via Vite). Guard every rule under a single root class `.theme-neon` so it never leaks to other pages.

Contents:
- CSS custom properties for the palette (bg, panel, panel-strong, line, line-strong, text, muted, cyan, orange, purple, neon-shadow). These mirror the sketch's `:root` block.
- `.theme-neon` body gradient + fixed scanline overlay (`::before`).
- `.neon-panel`, `.neon-card`, `.neon-card:hover` with subtle lift, border color shift, multi-layer `box-shadow`.
- `.neon-card::before` (gradient border glow) and `.neon-card::after` (diagonal shimmer sweep on hover).
- `.neon-topbar` (glass rounded pill for header).
- `.neon-btn-solid` and `.neon-btn-ghost` button primitives.
- `.neon-eyebrow` pill dot indicator.
- `.neon-carousel-shell::before`/`::after` left/right gradient fades over a horizontally scrolling rail.
- Keyframes for an optional subtle pulse on the brand badge (optional, not required).

Rationale for a dedicated CSS file rather than Tailwind theme extensions: the neon system uses several multi-stop radial gradients and pseudo-element shimmers that would be noisy as arbitrary Tailwind values. A scoped component layer is cleaner and stays out of the way of the rest of the app. Everything else (layout, spacing, typography) still uses Tailwind utilities.

**Tailwind safelist**: no change needed — we use plain CSS classes for the theme, and existing safelisted `bg-{color}-600` badge classes already cover `GameTypeEnum::colorClass()` and `PlatformEnum::color()`.

### 3. Layout adjustment

In `resources/views/layouts/app.blade.php`:
- Wrap the homepage in `<div class="theme-neon">` directly from `homepage/index.blade.php` — smaller diff than touching the layout body tag.
- **Remove** the conditional `<x-releases-nav active="" />` render on `/` (user confirmed). The new header absorbs News / Curated Lists / Events links. `<x-releases-nav>` stays available for any other routes that explicitly render it (grep to confirm no implicit callers after the change).

### 4. New header styling

Restyle `resources/views/components/header.blade.php` in place (no new component):
- Convert desktop layout to a three-column CSS grid matching the sketch: brand / centered search / right nav + auth icon button.
- Preserve `id="app-search"` and `id="app-search-mobile"` so Vue still mounts `<global-search>`.
- Right-side nav chips: **News** (visible only when `EnsureNewsFeatureEnabled::isVisibleTo(auth()->user())`), **Curated Lists** (link to `route('lists.index', 'seasoned')` or whatever matches), **Events** (link to `route('lists.index', 'events')` — verify route during implementation).
- Auth: a single compact icon button. `@guest` triggers the existing `open-modal` login modal via `$dispatch`. `@auth` keeps the existing Alpine dropdown (restyled round icon button). Keep all modal components at the bottom of the header component untouched.
- Brand: small gradient-bordered badge + name; keep the name and tagline responsive.
- Mobile (< 1180px): collapse the grid to a stack like the sketch's `@media (max-width: 1180px)` rule.

### 5. Hero section

Rendered from `homepage/index.blade.php` only when `$newsEnabled && $featuredNews`:

```blade
@if($newsEnabled && $featuredNews)
    <x-homepage.hero :featured="$featuredNews" :items="$topNews" />
@endif
```

Two-column grid (`minmax(0, 1.65fr) minmax(280px, 0.9fr)`).

**Left block** — featured news:
- Large image area using `$featuredNews->image_url` (fall back to placeholder gradient).
- Eyebrow "FEATURED NEWS".
- Headline (link to `route('news.show', $featuredNews)`).
- Summary (truncated to ~180 chars).
- Two CTAs anchored to bottom: **Read feature** (solid neon gradient) → article; **View all news** (ghost) → `route('news.index')`.
- Text aligned to top, CTAs pushed to bottom via `margin-top: auto`.

**Right block** — `<x-homepage.hero-news-item>` per item (up to 4):
- Thumbnail (96px square) + tiny "NEWS" pill + 2-line headline.
- Entire card is a link.
- Shares the same hover glow language (`.neon-card` pseudo-elements).

**No fallback markup**: when `$newsEnabled` is false or `$featuredNews` is null, the hero component is simply not rendered. The homepage begins at "This Week's Choices". This keeps the hero experience synced to the same feature flag that controls the rest of the news surface.

### 6. This Week's Choices

`<x-homepage.this-week-choices :games="$thisWeekGames" :platformEnums="$platformEnums" :currentYear="$currentYear" :currentMonth="$currentMonth" />`

- Section heading via `<x-homepage.section-heading icon="🎮" title="This Week's Choices" :seeAllRoute="route('releases.year.month', [$currentYear, $currentMonth])" />`.
- Remove the old "Curated / Upcoming" filter buttons (they do not exist in current view anyway — confirmed).
- Responsive grid: `grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5` to give a clean 10-card layout (2×5 on lg, 3×4 / leftover on md, etc.). Exactly 10 cards.
- Each card: `<x-game-card variant="neon" layout="below" aspectRatio="3/4" :game="$game" .../>` — title **below** the image, game type pill + release date on one row, platform pills below.
- `<x-this-week-choices-mobile>` is replaced by responsive grid columns. Grep `resources/views` for its usage; if only the homepage imports it, delete the file. Otherwise leave it.

### 7. Events section

`<x-homepage.events-grid :banners="$eventBanners" />`

- Section heading with icon 📡.
- Large wide banner cards (`minmax(320px, 1fr)` auto-fit grid). Bigger image area than before.
- Reuse `<x-seasonal-banner>` visual shell but override container classes to bump image height and apply the neon panel border + hover glow. If override via attributes proves awkward, inline the banner markup in this new component instead.

### 8. Upcoming Releases carousel

`<x-homepage.upcoming-releases :games="$weeklyUpcomingGames" :platformEnums="$platformEnums" />`

- Section heading with icon 🚀 and "See all →" → `route('upcoming')`.
- Reuse `<x-game-carousel>` with a **new prop** `variant="neon"` passed to inner `<x-game-card>`. That means:
  - Add `variant` prop to `game-carousel.blade.php` (default `glassmorphism` for backward compat) and forward it to the card.
  - In `game-card.blade.php`, add a `'neon'` case to the `$containerClasses`, `$imageContainerClasses`, `$imageClasses`, `$platformBadgeClasses` match arms. The neon case uses `layout="below"` exclusively and renders:
    - Row 1: `<h3>` uppercase title below the image.
    - Row 2: game type pill (left, from `$game->getGameTypeEnum()->colorClass()`) + release date (right, uppercase, bold — not a button).
    - Row 3: platform pills (compact, no color backgrounds by default; use neutral `neon-platform-pill` class for consistency, since the sketch shows uniform pill styling).
  - The card root gets the `neon-card` class so hover glow/shimmer works via the scoped CSS.
- Render all 18 games from `$weeklyUpcomingGames` (user confirmed — density over strict 12-card count).
- Carousel shell:
  - Retain existing horizontal scroll / snap / scrollbar-hide setup.
  - Narrower slides (`grid-auto-columns: 210px` on desktop per sketch).
  - Add left/right fade overlay pseudo-elements via `.neon-carousel-shell::before/::after`.
  - Keep existing Alpine-less arrow buttons; restyle to circular glass pills with neon border.
  - Confirm desktop arrows call `scrollBy` on the rail; preserve ARIA labels.

### 9. Latest Added Games (table-like)

`<x-homepage.latest-added-table :games="$latestAddedGames" :platformEnums="$platformEnums" />`

- Section heading with icon ✨.
- Desktop (`md+`): CSS grid with four columns: `Game | Platforms | Release Date | Added`.
  - Header row: small uppercase muted labels.
  - Each row: `.latest-table-row` neon panel with dashed border.
  - Col 1: thumbnail (58×58) + uppercase title + game type pill (from `$game->getGameTypeEnum()`).
  - Col 2: platform pills using existing `$platformEnums[$id]->label()` and `color()`.
  - Col 3: `$game->first_release_date?->format('d M Y')` or "TBA".
  - Col 4: human-diff of `$game->created_at` (`->diffForHumans()`).
- Mobile (`< 700px`): collapse each row to a stacked block (single column grid).
- Limit: keep at 12 (current). Matches the sketch's ~6 visible rows with room to scroll.
- Existing `<x-latest-added-games>` component: superseded. Grep `resources/views` for usages; if only the homepage imports it, delete. Otherwise leave it.

### 10. Footer

Restyle `resources/views/components/footer.blade.php` in place:
- Three-column grid: Copyright / Powered By / Credits (matching existing content).
- Each block wrapped as a neon panel with dashed inner border.
- Preserve the existing text content, links, and any translation keys.

### 11. Homepage view composition

Rewrite `resources/views/homepage/index.blade.php` to:
```blade
@extends('layouts.app')

@section('title', 'Games Outbreak')

@section('content')
<div class="theme-neon page-shell mx-auto px-4 pb-20">
    @if($newsEnabled && $featuredNews)
        <x-homepage.hero :featured="$featuredNews" :items="$topNews" />
    @endif

    <main class="main-column grid gap-6 min-w-0">
        <x-homepage.this-week-choices
            :games="$thisWeekGames"
            :platformEnums="$platformEnums"
            :currentYear="$currentYear"
            :currentMonth="$currentMonth" />

        <x-homepage.events-grid :banners="$eventBanners" />

        <x-homepage.upcoming-releases
            :games="$weeklyUpcomingGames"
            :platformEnums="$platformEnums" />

        <x-homepage.latest-added-table
            :games="$latestAddedGames"
            :platformEnums="$platformEnums" />
    </main>
</div>
@endsection
```

### 12. Tests (Pest feature tests)

Add or extend `tests/Feature/HomepagePageTest.php`:
- Homepage returns 200 for guests and authenticated users.
- Renders the four expected section headings ("This Week's Choices", "Events", "All Upcoming Releases", "Latest Added Games").
- When `features.news` is enabled and there is at least one published `News`, the featured news title, "FEATURED NEWS" eyebrow, and up to 4 side news items are rendered.
- When `features.news = false`, the hero markup is absent and the page still returns 200 with the remaining four sections intact.
- When `features.news` is enabled but there are zero published `News`, the hero is still absent (null-safe).
- Game cards display the expected `GameTypeEnum::label()` pill and at least one `PlatformEnum::label()` badge.
- No horizontal overflow: at minimum, assert the page shell has `overflow-x-hidden` class (can't easily test layout itself in Pest, but structural asserts protect against obvious regressions).

Use existing factories: `Game::factory()`, `GameList::factory()`, `News::factory()`. Check `database-schema` tool during implementation to confirm nullability/defaults before setup.

### 13. Pint, boost search, and manual verification

- Run `vendor/bin/pint --dirty` after PHP changes.
- Run `php artisan test --compact --filter=Homepage` after each section.
- Run `npm run build` (or have the user run `npm run dev`) before visual verification.
- Manually verify in browser: header grid, hero gated path with news disabled, carousel scroll snap, latest table mobile stacking, no horizontal overflow at 320/768/1024/1440 widths.

---

## Task breakdown (suggested execution order)

1. **Controller + data**: add news queries, lower `thisWeek` limit to 10, pass `$featuredNews`, `$topNews`, `$newsEnabled`.
2. **Scoped CSS**: create `resources/css/homepage.css`, import from `app.css`, add `.theme-neon` tokens + card/panel/glow primitives.
3. **Layout + wrapper**: remove `<x-releases-nav>` render on `/`; wrap homepage in `.theme-neon`.
4. **Header restyle**: rewrite topbar layout; keep Vue/Alpine mount points; rebuild right-nav chips (News gated).
5. **Footer restyle**.
6. **Hero component + hero-news-item** (rendered only when `$newsEnabled && $featuredNews`).
7. **Section heading primitive**.
8. **Game card `neon` variant**: extend `game-card.blade.php` match arms and add `neon-card` class.
9. **Game carousel `neon` variant**: add `variant` prop, forward to card, restyle shell arrows + fade masks.
10. **This Week's Choices** wrapper component using the neon card grid.
11. **Events grid** wrapper using bigger banner cards.
12. **Upcoming Releases** wrapper using the restyled carousel.
13. **Latest Added** table-like component.
14. **Rewrite `homepage/index.blade.php`** to compose everything.
15. **Tests**: add/extend Pest homepage feature tests.
16. **Pint + build + manual smoke test**.

---

## Verification

End-to-end:
- `php artisan test --compact --filter=Homepage` → all green.
- `php artisan test --compact` → full suite still green (no component regressions).
- `vendor/bin/pint --dirty` → clean.
- `npm run build` → no Vite errors; CSS bundle includes `.theme-neon` rules.
- Boost MCP: `mcp__plugin_laravel-boost_laravel-boost__get-absolute-url /` → open homepage, visually verify section order, hover glow, carousel scroll, mobile stacking.
- Toggle `config/features.news` to `false` and reload: hero is not rendered at all, page starts at This Week's Choices, no errors.
- Seed a minimal dataset via tinker if needed to see all sections populated.

Acceptance (from spec):
- [ ] Homepage section order matches: (optional Hero when news enabled) → This Week's Choices → Events → Upcoming Releases → Latest Added → Footer.
- [ ] Header uses centered search + compact right-side nav + single auth icon.
- [ ] Hero is 2-column (featured left, 3-4 stacked news right).
- [ ] This Week's Choices is a responsive grid of 10 cards (not a carousel).
- [ ] Events uses larger banner cards.
- [ ] Upcoming Releases is a horizontal carousel with dense-strip feel and arrow controls.
- [ ] Upcoming cards use the exact 3-row metadata hierarchy (title / type+date / platforms) with title below the image.
- [ ] Latest Added is table-like on desktop, stacked on mobile.
- [ ] Footer content preserved, presentation restyled.
- [ ] No horizontal overflow at any breakpoint.
- [ ] Hover glow is consistent across game cards and hero news items.

---

## Decisions locked in from clarifications

- **Hero gating**: rendered only when `features.news` is enabled AND there is at least one published `News`. No fallback markup when absent.
- **`<x-releases-nav>`**: removed from the homepage layout. The new topbar is the sole navigation on `/`.
- **Upcoming carousel size**: render all 18 games from the existing query for a denser rail.
- **Old components (`this-week-choices-mobile`, `latest-added-games`)**: delete during implementation if grep confirms the homepage is their only caller.

## Open item to resolve during implementation

- **Header nav chip routes**: confirm the exact target routes for the "News", "Curated Lists", and "Events" chips while editing `header.blade.php`. News → `route('news.index')`. Curated Lists and Events → read `routes/web.php` (lists/releases routes) and pick the best existing named route (`lists.index` with a type slug, or `releases.year`/`releases.year.month`). No controller change required — only link targets.