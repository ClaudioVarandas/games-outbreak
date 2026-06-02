# Events list: default list view + per-row YouTube trailers

Date: 2026-06-02
Status: Approved design — pending spec review, then implementation plan.

## Overview

On events-type game lists (e.g. `/list/events/nacon-connect-2026`):

1. Default the page to **list view** (grid stays available via the existing toggle).
2. In **list view only**, remove the leftover status/collection action buttons (playing / played / backlog / wishlist).
3. Add an optional **per-row YouTube trailer**: each game in the list can carry a `video_url`. When set, the list row shows a 16:9 trailer **thumbnail** with a play overlay that opens the existing global video lightbox (same UX as the home page).
4. Replace the heavy outline-calendar date icon in the list row with a lighter **compact date chip**.

Scope is deliberately narrow: events lists only, YouTube only for rows, list view only for button removal and the date-chip change. The existing list-level header video (`event_data['video_url']`, YouTube **and** Twitch) is untouched.

## Locked decisions

| Decision | Choice |
|---|---|
| Per-row video storage | New nullable `video_url` string column on the `game_list_game` pivot |
| Play UX | Trailer **thumbnail** (`img.youtube.com/vi/{id}/mqdefault.jpg`) + ▶ overlay → existing lightbox |
| Default-list scope | `list_type = EVENTS` lists only |
| Button removal scope | List view (`variant="table-row"`) only; grid keeps its buttons |
| Row video platforms | YouTube only (Twitch stays at list-header level only) |
| Row date display | Compact chip (day large + month abbrev, cyan), replacing the outline-calendar SVG |
| YouTube id source | Extracted from the stored URL via a shared helper — **no YouTube API call** |

## Architecture / changes by area

### 1. Data model
- New migration: add `video_url` (nullable string) to `game_list_game`. Own migration file, not chained.
- `App\Models\GameList::games()` — add `video_url` to the `withPivot(...)` list so `$game->pivot->video_url` is available wherever the relationship is loaded.

### 2. Shared YouTube id helper (reuse, refactor existing callers)
The watch-URL→id regex currently lives inline in `resources/views/components/video-embed.blade.php`, and related embed logic in `GameList::getVideoEmbedUrl()`.

- Create `App\Support\YouTube` with `idFromUrl(?string $url): ?string` (handles `youtube.com/watch?v=`, `youtu.be/`, ignores extra params, returns `null` when not a YouTube URL).
- Refactor `video-embed.blade.php` and `GameList::getVideoEmbedUrl()` to use the helper in **this same change** (per project reuse rule — no deferred refactor). Twitch handling in `video-embed.blade.php` stays as-is.

### 3. Backend write path (admin)
`App\Http\Controllers\AdminListController`:
- `addGame()` — accept `video_url`, validate, include in the `attach(...)` pivot array.
- `updateGamePivotData()` — accept `video_url`, validate, include in the `updateExistingPivot(...)` array.
- `getGameGenres()` (per-item fetch for the edit modal) — return current `video_url` so the modal pre-fills.
- **Validation rule** (both write methods): `video_url` is `nullable`; when present it must be a YouTube URL that the helper can parse. Message: "Must be a valid YouTube URL (youtube.com/watch?v=… or youtu.be/…)." Implemented as a closure/rule using `YouTube::idFromUrl()`.

No new FormRequest unless the surrounding methods already use one; mirror the existing inline `$request->validate([...])` style in those methods.

### 4. Admin UI (Vue modal)
- `resources/js/components/GameFormModal.vue` — add a `Video URL` `<input type="url">` mirroring the existing `release_date` field; wire into `formData`, `resetForm()` (init from prop), and the `handleSubmit()` emit.
- `resources/js/components/GameEditModals.vue` — parse `video_url` from the `getGameGenres` fetch into the modal's initial props.
- `resources/js/components/AddGameToList.vue` — include `video_url` in the add-form submit payload.

### 5. List row rendering (frontend)
`resources/views/components/game-card.blade.php`, `variant="table-row"` branch:
- **Remove** the Quick Actions cell (`<x-game-collection-actions-mobile>`).
- **Date chip**: replace the outline-calendar SVG with a compact chip — day (large, cyan) + month abbrev (small, uppercase). Keep the existing `TBA` branch. Grid (`neon`) variant date display is unchanged.
- **Trailer thumbnail cell** (right side):
  - Add a `videoUrl` prop to the component. Compute `$videoId = \App\Support\YouTube::idFromUrl($videoUrl)`.
  - When `$videoId` present: render a 16:9 thumbnail (`https://img.youtube.com/vi/{$videoId}/mqdefault.jpg`, `loading="lazy"`) inside a trigger element with `data-video-id="{$videoId}"` and `data-video-title="{game name}"`, with a ▶ play overlay. Reuses the global lightbox unchanged (it already binds to any `[data-video-id]`).
  - When absent: render an empty/placeholder cell of equal width to keep rows aligned.
- `resources/views/lists/show.blade.php` (events list-view loop) — pass `:videoUrl="$game->pivot->video_url"` to the `table-row` card.

### 6. Default view = list (events)
`resources/js/components/list-filter.js` + `show.blade.php`:
- Pass a default view mode into the Alpine component (e.g. `listFilter(..., defaultViewMode)` = `'list'` when `$gameList->isEvents()`, else `'grid'`).
- `init()`: set `viewMode = defaultViewMode`; URL hash still overrides (`#view=grid` / `#view=list`). Toggle behavior unchanged.

### 7. Responsive
- Desktop: 108×60 thumbnail. Below a small breakpoint (mobile), shrink to ~84×47 (or fall back to the icon trigger) so the row doesn't overflow. Cover thumbnail (left) + trailer thumbnail (right) both remain legible.

## Testing (Pest)

- **Unit** — `YouTube::idFromUrl()`: `watch?v=`, `youtu.be/`, URL with extra query params, non-YouTube/invalid → `null`. Dataset-driven.
- **Feature (admin write)** — `addGame` persists `video_url` to the pivot; `updateGamePivotData` updates it; validation rejects a non-YouTube URL (assert validation error) and accepts `null`/empty.
- **Feature (rendering)** — events list defaults to list markup; a row whose pivot has `video_url` renders the trailer thumbnail (assert `data-video-id` = extracted id and the `img.youtube.com/vi/{id}` src); a row without renders no trailer trigger; the collection-actions component is absent from `table-row` output.
- Run with `XDEBUG_MODE=off`, `--compact`, filtered to the touched files.

## Out of scope / untouched
- List-level header video (`event_data['video_url']`) and its Twitch support.
- Grid card layout and grid action buttons.
- Non-events list types (still default to grid).
- YouTube Data API usage (the `YOUTUBE_API_KEY` admin-import pipeline is unrelated).

## File touch-list
- `database/migrations/<new>_add_video_url_to_game_list_game_table.php` (new)
- `app/Support/YouTube.php` (new)
- `app/Models/GameList.php` (`withPivot`, refactor `getVideoEmbedUrl`)
- `app/Http/Controllers/AdminListController.php` (`addGame`, `updateGamePivotData`, `getGameGenres`)
- `resources/js/components/GameFormModal.vue`, `GameEditModals.vue`, `AddGameToList.vue`
- `resources/views/components/game-card.blade.php` (table-row branch)
- `resources/views/components/video-embed.blade.php` (use helper)
- `resources/views/lists/show.blade.php` (pass `videoUrl`, default view flag)
- `resources/js/components/list-filter.js` (default view mode)
- Tests: `tests/Unit/YouTubeTest.php`, feature tests under `tests/Feature/...`

## Preview
Interactive mockup of the final look: `docs/previews/list-layout-preview.html` (throwaway; safe to delete).
