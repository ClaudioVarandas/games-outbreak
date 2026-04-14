# Theme Rework Implementation Checklist

Homepage-first implementation of the neon/cyberpunk theme rework defined in `THEME_UI_UX_REWORK.md` and `sketch.html`.

## Planning And Setup
- [x] Confirm homepage-first scope and component-variant strategy
- [x] Confirm header nav targets for `NEWS`, `CURATED LISTS`, and `EVENTS`
- [x] Confirm final section order against sketch and spec files

## Data And Controller
- [x] Add homepage news query to `HomepageController@index`
- [x] Gate hero rendering with existing news feature visibility rules
- [x] Limit “This Week’s Choices” to 10 items
- [x] Preserve upcoming, events, and latest-added data contracts

## Theme System And Shell
- [x] Add scoped homepage neon CSS layer and import it
- [x] Restyle global `x-header` to the new topbar layout
- [x] Remove homepage dependency on `x-releases-nav`
- [x] Restyle global `x-footer` to match the new shell
- [x] Replace emoji section markers with project-style SVG icons
- [x] Add persistent framed glow treatment to homepage sections

## Homepage Sections
- [x] Rebuild homepage template structure in `resources/views/homepage/index.blade.php`
- [x] Implement hero with featured news and compact news feed
- [x] Implement This Week’s Choices as a responsive 10-card grid
- [x] Implement Events as wide banner-style cards
- [x] Implement All Upcoming Releases as a neon carousel rail
- [x] Implement Latest Added Games as a responsive table-like layout

## Shared Components
- [x] Add homepage/neon variant to `x-game-card`
- [x] Add homepage/neon variant support to `x-game-carousel`
- [x] Reuse or replace event banner rendering without breaking non-homepage usages
- [x] Replace homepage-only legacy section components if they block the new design

## Testing And Verification
- [x] Update homepage feature tests for new structure
- [x] Add tests for hero visibility when news is enabled or disabled
- [x] Add tests for hero absence when no published news exists
- [x] Verify this-week limit of 10 and latest-added limit behavior
- [x] Run targeted Pest tests
- [x] Run `vendor/bin/pint --dirty --format agent`
- [ ] Perform responsive visual QA against the sketch
- [ ] Verify section framing and icon treatment visually against the sketch

## Assumptions
- Homepage-first rollout
- Header and footer restyled globally
- Shared UI uses homepage-specific variants where needed
- `EVENTS` links to the homepage Events section in this pass
