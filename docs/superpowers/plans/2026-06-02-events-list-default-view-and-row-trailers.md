# Events List: default list view + per-row YouTube trailers — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** On events-type game lists, default to list view, drop the leftover collection action buttons in list rows, and let each game carry an optional YouTube trailer shown as a thumbnail that opens the existing lightbox.

**Architecture:** A nullable `video_url` column on the `game_list_game` pivot stores a YouTube URL per game. A shared `App\Support\YouTube::idFromUrl()` helper extracts the id (reused by the existing `<x-video-embed>` and `GameList::getVideoEmbedUrl()`, refactored in this plan). The `table-row` variant of `<x-game-card>` renders the trailer thumbnail (`img.youtube.com/vi/{id}/mqdefault.jpg`) as a `data-video-id` trigger — the global `video-lightbox.js` (already bound to `[data-video-id]`) plays it. Default view mode is passed into the Alpine `listFilter()` component; events → `list`. Admin enters the URL through the existing Vue edit/add modal.

**Tech Stack:** Laravel 12, PHP 8.5, Blade, Alpine 3, Vue 3 (admin modals), Pest 4, Tailwind 3.

**Project specifics (must follow):**
- Run tests with Xdebug off: `XDEBUG_MODE=off php artisan test --compact ...` (otherwise it hangs on port 9003).
- Before any `npm` command run `nvm use 24` (system Node is too old for Vite).
- Run `vendor/bin/pint --dirty --format agent` before finalizing.
- **Do NOT** add a `Co-authored-by` trailer to commits.

---

## File structure

| File | Responsibility | Action |
|---|---|---|
| `app/Support/YouTube.php` | Extract YouTube video id from a URL | Create |
| `tests/Unit/YouTubeTest.php` | Unit-test the helper | Create |
| `database/migrations/<ts>_add_video_url_to_game_list_game_table.php` | Add `video_url` pivot column | Create |
| `app/Models/GameList.php` | Expose `video_url` on the pivot; reuse helper in `getVideoEmbedUrl()` | Modify |
| `resources/views/components/video-embed.blade.php` | Reuse helper for YouTube id | Modify |
| `app/Http/Controllers/AdminListController.php` | Validate + persist `video_url` (add/update/fetch) | Modify |
| `tests/Feature/AdminListVideoUrlTest.php` | Feature-test the admin write path | Create |
| `resources/views/components/game-card.blade.php` | `table-row`: drop action buttons, date chip, trailer thumbnail | Modify |
| `tests/Feature/GameCardTableRowTest.php` | Render-test the table-row variant | Create |
| `resources/views/lists/show.blade.php` | Pass `videoUrl` to row; pass default view mode | Modify |
| `resources/js/components/list-filter.js` | Accept + apply default view mode | Modify |
| `tests/Feature/EventsListViewTest.php` | Feature-test default view + row thumbnail | Create |
| `resources/js/components/GameFormModal.vue` | Trailer URL input | Modify |
| `resources/js/components/GameEditModals.vue` | Carry `video_url` in/out of edit modal | Modify |
| `resources/js/components/AddGameToList.vue` | Send `video_url` on add | Modify |
| `docs/previews/list-layout-preview.html` | Throwaway preview | Delete at end |

---

## Task 1: `App\Support\YouTube` helper + refactor existing callers

**Files:**
- Create: `app/Support/YouTube.php`
- Test: `tests/Unit/YouTubeTest.php`
- Modify: `app/Models/GameList.php:282-285`, `resources/views/components/video-embed.blade.php:9-12`

- [ ] **Step 1: Create the unit test file**

Run: `php artisan make:test --pest --unit YouTubeTest --no-interaction`
Expected: creates `tests/Unit/YouTubeTest.php`.

- [ ] **Step 2: Write the failing test**

Replace the contents of `tests/Unit/YouTubeTest.php` with:

```php
<?php

use App\Support\YouTube;

it('extracts the video id from youtube urls', function (?string $url, ?string $expected) {
    expect(YouTube::idFromUrl($url))->toBe($expected);
})->with([
    'watch url' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
    'watch url with extra params' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=42s', 'dQw4w9WgXcQ'],
    'short url' => ['https://youtu.be/dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
    'twitch url' => ['https://www.twitch.tv/somechannel', null],
    'random string' => ['not a url', null],
    'empty string' => ['', null],
    'null' => [null, null],
]);
```

- [ ] **Step 3: Run the test to verify it fails**

Run: `XDEBUG_MODE=off php artisan test --compact tests/Unit/YouTubeTest.php`
Expected: FAIL — `Class "App\Support\YouTube" not found`.

- [ ] **Step 4: Create the helper**

Create `app/Support/YouTube.php`:

```php
<?php

namespace App\Support;

class YouTube
{
    public static function idFromUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `XDEBUG_MODE=off php artisan test --compact tests/Unit/YouTubeTest.php`
Expected: PASS (7 cases).

- [ ] **Step 6: Refactor `GameList::getVideoEmbedUrl()` to use the helper**

In `app/Models/GameList.php`, replace the YouTube branch (currently lines 282-285):

```php
        // YouTube: https://www.youtube.com/watch?v=VIDEO_ID or https://youtu.be/VIDEO_ID
        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return 'https://www.youtube.com/embed/'.$matches[1];
        }
```

with:

```php
        // YouTube: https://www.youtube.com/watch?v=VIDEO_ID or https://youtu.be/VIDEO_ID
        if ($youtubeId = \App\Support\YouTube::idFromUrl($url)) {
            return 'https://www.youtube.com/embed/'.$youtubeId;
        }
```

(Leave the two Twitch branches below unchanged.)

- [ ] **Step 7: Refactor `video-embed.blade.php` to use the helper**

In `resources/views/components/video-embed.blade.php`, replace the YouTube branch (lines 8-12):

```php
        // YouTube: https://www.youtube.com/watch?v=VIDEO_ID or https://youtu.be/VIDEO_ID
        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $url, $matches)) {
            $embedUrl = 'https://www.youtube.com/embed/' . $matches[1] . '?rel=0&modestbranding=1';
            $platform = 'youtube';
        }
```

with:

```php
        // YouTube: https://www.youtube.com/watch?v=VIDEO_ID or https://youtu.be/VIDEO_ID
        if ($youtubeId = \App\Support\YouTube::idFromUrl($url)) {
            $embedUrl = 'https://www.youtube.com/embed/' . $youtubeId . '?rel=0&modestbranding=1';
            $platform = 'youtube';
        }
```

(Leave the two Twitch `elseif` branches unchanged.)

- [ ] **Step 8: Run the unit test again (refactor is behavior-preserving)**

Run: `XDEBUG_MODE=off php artisan test --compact tests/Unit/YouTubeTest.php`
Expected: PASS.

- [ ] **Step 9: Commit**

```bash
git add app/Support/YouTube.php tests/Unit/YouTubeTest.php app/Models/GameList.php resources/views/components/video-embed.blade.php
git commit -m "feat(video): add YouTube id helper and reuse it in video-embed"
```

---

## Task 2: `video_url` pivot column + model exposure

**Files:**
- Create: `database/migrations/<ts>_add_video_url_to_game_list_game_table.php`
- Modify: `app/Models/GameList.php:54-60`

- [ ] **Step 1: Create the migration**

Run: `php artisan make:migration add_video_url_to_game_list_game_table --no-interaction`
Expected: creates a timestamped file under `database/migrations/`.

- [ ] **Step 2: Write the migration body**

Replace the generated file's contents with:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_list_game', function (Blueprint $table) {
            $table->string('video_url')->nullable()->after('platform_group');
        });
    }

    public function down(): void
    {
        Schema::table('game_list_game', function (Blueprint $table) {
            $table->dropColumn('video_url');
        });
    }
};
```

- [ ] **Step 3: Expose `video_url` on the relationship**

In `app/Models/GameList.php`, the `games()` method (lines 54-60), add `'video_url'` to `withPivot`:

```php
    public function games(): BelongsToMany
    {
        return $this->belongsToMany(Game::class, 'game_list_game')
            ->withPivot('order', 'release_date', 'platforms', 'platform_group', 'is_highlight', 'is_tba', 'is_early_access', 'is_indie', 'genre_ids', 'primary_genre_id', 'video_url')
            ->withTimestamps()
            ->orderByPivot('order');
    }
```

- [ ] **Step 4: Run the migration**

Run: `XDEBUG_MODE=off php artisan migrate`
Expected: migrates the new file with no errors.

- [ ] **Step 5: Commit**

```bash
git add database/migrations app/Models/GameList.php
git commit -m "feat(lists): add nullable video_url to game_list_game pivot"
```

---

## Task 3: Admin write path (validate + persist `video_url`)

**Files:**
- Modify: `app/Http/Controllers/AdminListController.php` (`addGame` ~345-432, `getGameGenres` ~766-780, `updateGamePivotData` ~796-831; add a private rule helper)
- Test: `tests/Feature/AdminListVideoUrlTest.php`

- [ ] **Step 1: Create the feature test file**

Run: `php artisan make:test --pest AdminListVideoUrlTest --no-interaction`
Expected: creates `tests/Feature/AdminListVideoUrlTest.php`.

- [ ] **Step 2: Write the failing tests**

Replace the contents of `tests/Feature/AdminListVideoUrlTest.php` with:

```php
<?php

use App\Models\Game;
use App\Models\GameList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function videoListAndGame(): array
{
    $admin = User::factory()->create(['is_admin' => true]);
    $list = GameList::factory()->events()->system()->create([
        'user_id' => $admin->id,
        'slug' => 'nacon-connect-2026',
    ]);
    $game = Game::factory()->create(['igdb_id' => 555111]);

    return [$admin, $list, $game];
}

it('stores video_url when adding a game to an events list', function () {
    [$admin, $list, $game] = videoListAndGame();

    $this->actingAs($admin)
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post('/admin/system-lists/events/nacon-connect-2026/games', [
            'game_id' => 555111,
            'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ])
        ->assertJson(['success' => true]);

    $pivot = $list->games()->where('game_id', $game->id)->first()->pivot;
    expect($pivot->video_url)->toBe('https://www.youtube.com/watch?v=dQw4w9WgXcQ');
});

it('updates video_url via the pivot endpoint', function () {
    [$admin, $list, $game] = videoListAndGame();
    $list->games()->attach($game->id, ['order' => 1]);

    $this->actingAs($admin)
        ->patchJson('/admin/system-lists/events/nacon-connect-2026/games/'.$game->id.'/pivot', [
            'video_url' => 'https://youtu.be/dQw4w9WgXcQ',
        ])
        ->assertJson(['success' => true]);

    $pivot = $list->games()->where('game_id', $game->id)->first()->pivot;
    expect($pivot->video_url)->toBe('https://youtu.be/dQw4w9WgXcQ');
});

it('clears video_url when an empty value is submitted', function () {
    [$admin, $list, $game] = videoListAndGame();
    $list->games()->attach($game->id, ['order' => 1, 'video_url' => 'https://youtu.be/dQw4w9WgXcQ']);

    $this->actingAs($admin)
        ->patchJson('/admin/system-lists/events/nacon-connect-2026/games/'.$game->id.'/pivot', [
            'video_url' => '',
        ])
        ->assertJson(['success' => true]);

    $pivot = $list->games()->where('game_id', $game->id)->first()->pivot;
    expect($pivot->video_url)->toBeNull();
});

it('rejects a non-youtube video_url', function () {
    [$admin, $list, $game] = videoListAndGame();

    $this->actingAs($admin)
        ->withHeaders(['Accept' => 'application/json'])
        ->post('/admin/system-lists/events/nacon-connect-2026/games', [
            'game_id' => 555111,
            'video_url' => 'https://example.com/not-youtube',
        ])
        ->assertUnprocessable();
});
```

- [ ] **Step 3: Run the tests to verify they fail**

Run: `XDEBUG_MODE=off php artisan test --compact tests/Feature/AdminListVideoUrlTest.php`
Expected: FAIL — `video_url` not persisted / invalid URL not rejected.

- [ ] **Step 4: Add a private YouTube-URL rule helper to the controller**

In `app/Http/Controllers/AdminListController.php`, add this private method (place it near the existing `guardReleaseState()` method):

```php
    private function youtubeUrlRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value && ! \App\Support\YouTube::idFromUrl($value)) {
                $fail('Must be a valid YouTube URL (youtube.com/watch?v=… or youtu.be/…).');
            }
        };
    }
```

- [ ] **Step 5: Validate + persist in `addGame()`**

In `addGame()`, add the `video_url` rule to the `$request->validate([...])` array (after `'is_early_access'`):

```php
            'is_early_access' => ['nullable', 'boolean'],
            'video_url' => ['nullable', 'string', $this->youtubeUrlRule()],
```

Then in the `$list->games()->attach($game->id, [...])` array (after `'primary_genre_id'`), add:

```php
            'primary_genre_id' => $primaryGenreId ? (int) $primaryGenreId : null,
            'video_url' => $request->input('video_url') ?: null,
```

- [ ] **Step 6: Validate + persist in `updateGamePivotData()`**

In `updateGamePivotData()`, add the rule to its `$request->validate([...])` array (after `'primary_genre_id'`):

```php
            'primary_genre_id' => ['nullable', 'exists:genres,id'],
            'video_url' => ['nullable', 'string', $this->youtubeUrlRule()],
```

Then, just before `$list->games()->updateExistingPivot($game->id, $pivotUpdate);`, add:

```php
        if ($request->has('video_url')) {
            $pivotUpdate['video_url'] = $request->input('video_url') ?: null;
        }

        $list->games()->updateExistingPivot($game->id, $pivotUpdate);
```

- [ ] **Step 7: Return `video_url` from `getGameGenres()`**

In `getGameGenres()`, add to the `response()->json([...])` array (after `'cover_url'`):

```php
            'cover_url' => $game->getCoverUrl('cover_big'),
            'video_url' => $pivotData->video_url ?? null,
```

- [ ] **Step 8: Run the tests to verify they pass**

Run: `XDEBUG_MODE=off php artisan test --compact tests/Feature/AdminListVideoUrlTest.php`
Expected: PASS (4 cases).

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/AdminListController.php tests/Feature/AdminListVideoUrlTest.php
git commit -m "feat(admin): persist and validate per-game video_url on system lists"
```

---

## Task 4: List-row markup — drop action buttons, date chip, trailer thumbnail

**Files:**
- Modify: `resources/views/components/game-card.blade.php` (props line 1-19; `table-row` branch lines 112-182)
- Test: `tests/Feature/GameCardTableRowTest.php`

- [ ] **Step 1: Create the render-test file**

Run: `php artisan make:test --pest GameCardTableRowTest --no-interaction`
Expected: creates `tests/Feature/GameCardTableRowTest.php`.

- [ ] **Step 2: Write the failing tests**

Replace the contents of `tests/Feature/GameCardTableRowTest.php` with:

```php
<?php

use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders a youtube trailer thumbnail when a video url is given', function () {
    $game = Game::factory()->create();

    $this->blade(
        '<x-game-card :game="$game" variant="table-row" :videoUrl="$videoUrl" :displayReleaseDate="$date" />',
        [
            'game' => $game,
            'videoUrl' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'date' => now()->setDate(2026, 3, 14),
        ]
    )
        ->assertSee('data-video-id="dQw4w9WgXcQ"', false)
        ->assertSee('img.youtube.com/vi/dQw4w9WgXcQ/mqdefault.jpg', false);
});

it('renders no trailer trigger when the game has no video url', function () {
    $game = Game::factory()->create();

    $this->blade(
        '<x-game-card :game="$game" variant="table-row" :videoUrl="$videoUrl" :displayReleaseDate="$date" />',
        ['game' => $game, 'videoUrl' => null, 'date' => now()->setDate(2026, 3, 14)]
    )->assertDontSee('data-video-id', false);
});

it('does not render collection action buttons in the table-row', function () {
    $game = Game::factory()->create();

    $this->blade(
        '<x-game-card :game="$game" variant="table-row" :displayReleaseDate="$date" />',
        ['game' => $game, 'date' => now()->setDate(2026, 3, 14)]
    )
        ->assertDontSee('Wishlist')
        ->assertDontSee('gameCollectionActions(', false);
});

it('renders a compact date chip with day and month', function () {
    $game = Game::factory()->create();

    $this->blade(
        '<x-game-card :game="$game" variant="table-row" :displayReleaseDate="$date" />',
        ['game' => $game, 'date' => now()->setDate(2026, 3, 14)]
    )
        ->assertSee('14')
        ->assertSee('Mar');
});
```

- [ ] **Step 3: Run the tests to verify they fail**

Run: `XDEBUG_MODE=off php artisan test --compact tests/Feature/GameCardTableRowTest.php`
Expected: FAIL — no `data-video-id`; `Wishlist` still present; unknown `videoUrl` prop.

- [ ] **Step 4: Add the `videoUrl` prop**

In `resources/views/components/game-card.blade.php`, add `'videoUrl' => null,` to the `@props([...])` array (after `'isEarlyAccess' => false,` on line 18):

```php
    'isEarlyAccess' => false, // If true, shows an "EA" badge alongside the date (mutually exclusive with TBA)
    'videoUrl' => null, // Optional: YouTube watch URL for the per-row trailer (table-row variant only)
])
```

- [ ] **Step 5: Replace the `table-row` branch**

Replace the entire `table-row` block (currently lines 112-182, from `{{-- TABLE ROW VARIANT --}}` through the closing `</div>` before `@else`) with:

```blade
{{-- TABLE ROW VARIANT --}}
@if($variant === 'table-row')
@php $videoId = \App\Support\YouTube::idFromUrl($videoUrl); @endphp
<div class="relative flex items-center gap-4 p-3 transition-colors hover:bg-white/[0.03]">
    {{-- Small Cover Thumbnail --}}
    <a href="{{ $linkUrl }}" class="flex-shrink-0">
        <div class="relative w-12 h-16 rounded overflow-hidden bg-gray-200 dark:bg-gray-700">
            @if($coverUrl)
                <img src="{{ $coverUrl }}"
                     alt="{{ $game->name }}"
                     class="w-full h-full object-cover"
                     loading="lazy">
            @else
                <div class="w-full h-full flex items-center justify-center text-gray-400 text-xs">
                    <x-heroicon-o-photo class="w-6 h-6" />
                </div>
            @endif
            @if($isEarlyAccess)
                <span class="absolute left-0 top-0 rounded-br bg-blue-500/90 px-1 text-[0.55rem] font-bold uppercase tracking-wide text-white shadow">EA</span>
            @endif
        </div>
    </a>

    {{-- Game Name + Type + Platforms --}}
    <div class="flex-1 min-w-0">
        <a href="{{ $linkUrl }}" class="block">
            <h3 class="font-semibold text-slate-100 truncate hover:text-cyan-300 transition-colors">
                {{ $game->name }}
            </h3>
        </a>
        <div class="mt-1.5 flex flex-wrap items-center gap-1">
            <span class="{{ $game->getGameTypeEnum()->neonColorClass() }} px-1.5 py-0.5 text-xs font-medium rounded">
                {{ $game->getGameTypeEnum()->label() }}
            </span>
            @foreach($sortedPlatforms->take(4) as $platform)
                @php $enum = $platformEnums[$platform->igdb_id] ?? null; @endphp
                <span class="neon-platform-pill">
                    {{ $enum?->label() ?? \Illuminate\Support\Str::limit($platform->name, 4) }}
                </span>
            @endforeach
            @if($sortedPlatforms->count() > 4)
                <span class="neon-platform-pill opacity-60">+{{ $sortedPlatforms->count() - 4 }}</span>
            @endif
        </div>
    </div>

    {{-- Date chip --}}
    <div class="flex-shrink-0">
        @if($isTba || !($displayReleaseDateFormatted ?? $releaseDate))
            <span class="text-[0.7rem] font-bold uppercase tracking-[0.06em] text-slate-500">TBA</span>
        @else
            <div class="flex w-[50px] flex-col items-center justify-center rounded-[10px] border border-cyan-400/30 bg-cyan-400/[0.06] py-1.5">
                <span class="text-xl font-extrabold leading-none text-cyan-300">{{ $releaseDate->format('j') }}</span>
                <span class="mt-1 text-[0.55rem] font-bold uppercase tracking-[0.14em] text-slate-400">{{ $releaseDate->format('M') }}</span>
            </div>
        @endif
    </div>

    {{-- Trailer thumbnail (YouTube) --}}
    <div class="flex-shrink-0">
        @if($videoId)
            <button type="button"
                    data-video-id="{{ $videoId }}"
                    data-video-title="{{ $game->name }}"
                    aria-label="{{ __('Play trailer') }}: {{ $game->name }}"
                    class="group/vid relative block h-[47px] w-[84px] overflow-hidden rounded-lg border border-white/10 bg-black/40 transition hover:border-cyan-400/60 hover:shadow-[0_0_18px_rgba(99,243,255,0.3)] sm:h-[60px] sm:w-[108px]">
                <img src="https://img.youtube.com/vi/{{ $videoId }}/mqdefault.jpg"
                     alt=""
                     loading="lazy"
                     class="h-full w-full object-cover opacity-90 transition group-hover/vid:opacity-100">
                <span class="absolute inset-0 grid place-items-center">
                    <span class="grid h-7 w-7 place-items-center rounded-full border border-cyan-400/60 bg-slate-950/60 backdrop-blur-sm">
                        <svg viewBox="0 0 24 24" fill="currentColor" class="ml-0.5 h-3.5 w-3.5 text-cyan-300"><path d="M8 5v14l11-7z"/></svg>
                    </span>
                </span>
            </button>
        @else
            <div class="h-[47px] w-[84px] sm:h-[60px] sm:w-[108px]"></div>
        @endif
    </div>
</div>
@else
```

(The trailing `@else` keeps the existing non-table-row markup below it intact. Do not touch anything after `@else`.)

- [ ] **Step 6: Run the tests to verify they pass**

Run: `XDEBUG_MODE=off php artisan test --compact tests/Feature/GameCardTableRowTest.php`
Expected: PASS (4 cases).

> If the isolated render errors on platform enum resolution, add `:displayPlatforms="[]"` to the `<x-game-card ... />` strings in the test — the component then falls back to the (empty) game platforms collection.

- [ ] **Step 7: Commit**

```bash
git add resources/views/components/game-card.blade.php tests/Feature/GameCardTableRowTest.php
git commit -m "feat(lists): list-row trailer thumbnail + date chip, drop action buttons"
```

---

## Task 5: Wire the list page — pass `videoUrl`, default events to list view

**Files:**
- Modify: `resources/views/lists/show.blade.php` (x-data ~72-83; table-row card ~433-441)
- Modify: `resources/js/components/list-filter.js` (signature line 7; `viewMode` line 24; `init()` lines 40-51)
- Test: `tests/Feature/EventsListViewTest.php`

- [ ] **Step 1: Create the feature test file**

Run: `php artisan make:test --pest EventsListViewTest --no-interaction`
Expected: creates `tests/Feature/EventsListViewTest.php`.

- [ ] **Step 2: Write the failing tests**

Replace the contents of `tests/Feature/EventsListViewTest.php` with:

```php
<?php

use App\Models\Game;
use App\Models\GameList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('defaults events lists to the list view', function () {
    $list = GameList::factory()->events()->system()->create([
        'slug' => 'nacon-connect-2026',
        'is_public' => true,
    ]);
    $list->games()->attach(Game::factory()->create()->id, [
        'order' => 1,
        'release_date' => now()->setDate(2026, 3, 14),
    ]);

    $this->actingAs(User::factory()->create())
        ->get('/list/events/nacon-connect-2026')
        ->assertOk()
        ->assertSee('"list"', false); // 5th listFilter() argument = default view mode
});

it('defaults non-events system lists to the grid view', function () {
    $list = GameList::factory()->yearly()->system()->create([
        'slug' => 'year-2026',
        'is_public' => true,
    ]);
    $list->games()->attach(Game::factory()->create()->id, [
        'order' => 1,
        'release_date' => now()->setDate(2026, 3, 14),
    ]);

    $this->actingAs(User::factory()->create())
        ->get('/list/yearly/year-2026')
        ->assertOk()
        ->assertSee('"grid"', false);
});

it('renders a row trailer thumbnail for games with a video url', function () {
    $list = GameList::factory()->events()->system()->create([
        'slug' => 'nacon-connect-2026',
        'is_public' => true,
    ]);
    $list->games()->attach(Game::factory()->create()->id, [
        'order' => 1,
        'release_date' => now()->setDate(2026, 3, 14),
        'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ]);

    $this->actingAs(User::factory()->create())
        ->get('/list/events/nacon-connect-2026')
        ->assertOk()
        ->assertSee('data-video-id="dQw4w9WgXcQ"', false)
        ->assertSee('img.youtube.com/vi/dQw4w9WgXcQ/mqdefault.jpg', false);
});
```

- [ ] **Step 3: Run the tests to verify they fail**

Run: `XDEBUG_MODE=off php artisan test --compact tests/Feature/EventsListViewTest.php`
Expected: FAIL — no `"list"` default arg yet; no row thumbnail.

- [ ] **Step 4: Pass `videoUrl` to the table-row card**

In `resources/views/lists/show.blade.php`, in the List View loop, update the `<x-game-card ... variant="table-row" ... />` (lines 433-441) to add the `videoUrl` prop:

```blade
                                                    <x-game-card
                                                        :game="$game"
                                                        :displayReleaseDate="$displayDate"
                                                        :displayPlatforms="$pivotPlatforms"
                                                        variant="table-row"
                                                        :platformEnums="$platformEnums"
                                                        :isTba="$isTba"
                                                        :isEarlyAccess="$isEarlyAccess"
                                                        :videoUrl="$game->pivot->video_url ?? null"
                                                    />
```

- [ ] **Step 5: Pass the default view mode into `listFilter()`**

In `resources/views/lists/show.blade.php`, update the `x-data="listFilter(...)"` (lines 72-83) to add a 5th argument:

```blade
        <div x-data="listFilter(
            {{ Js::from($gamesData) }},
            {{ Js::from($initialFilters ?? []) }},
            {{ Js::from($filterOptions ?? []) }},
            {{ Js::from([
                'enabled' => auth()->check(),
                'backlogGameIds' => $backlogGameIds ?? [],
                'wishlistGameIds' => $wishlistGameIds ?? [],
                'csrfToken' => csrf_token(),
                'username' => auth()->user()?->username ?? '',
            ]) }},
            {{ Js::from($gameList->isEvents() ? 'list' : 'grid') }}
        )" class="min-h-screen">
```

- [ ] **Step 6: Accept + apply the default view mode in the Alpine component**

In `resources/js/components/list-filter.js`:

(a) Update the factory signature (line 7):

```js
Alpine.data('listFilter', (initialGames, initialFilters, filterOptions, quickActionsConfig = {}, defaultViewMode = 'grid') => ({
```

(b) Change the `viewMode` initial value (line 24):

```js
        // View mode: 'grid' or 'list'
        viewMode: defaultViewMode,
```

(c) Replace the `init()` hash logic (lines 40-51) so the URL hash overrides the default in both directions:

```js
        init() {
            // URL hash overrides the default view mode
            const hash = window.location.hash;
            if (hash.includes('view=grid')) {
                this.viewMode = 'grid';
            } else if (hash.includes('view=list')) {
                this.viewMode = 'list';
            }

            // Listen for popstate (back/forward navigation)
            window.addEventListener('popstate', () => {
                this.parseUrlFilters();
            });
        },
```

- [ ] **Step 7: Build assets**

Run: `nvm use 24 && npm run build`
Expected: Vite build succeeds (compiles the updated `list-filter.js`).

- [ ] **Step 8: Run the tests to verify they pass**

Run: `XDEBUG_MODE=off php artisan test --compact tests/Feature/EventsListViewTest.php`
Expected: PASS (3 cases).

- [ ] **Step 9: Commit**

```bash
git add resources/views/lists/show.blade.php resources/js/components/list-filter.js tests/Feature/EventsListViewTest.php
git commit -m "feat(lists): default events lists to list view and render row trailers"
```

---

## Task 6: Admin Vue modal — trailer URL input

No JS test runner is configured in this project, so these changes are verified manually and via the Task 3 controller contract (the modal POSTs/PATCHes the same `video_url` field already tested). Make all three edits, build, then verify in the browser.

**Files:**
- Modify: `resources/js/components/GameFormModal.vue`
- Modify: `resources/js/components/GameEditModals.vue`
- Modify: `resources/js/components/AddGameToList.vue`

- [ ] **Step 1: `GameFormModal.vue` — add the prop**

In the `defineProps({...})` object, after the `initialGenreIds` prop (lines 293-296), add:

```js
  initialGenreIds: {
    type: Array,
    default: () => []
  },
  initialVideoUrl: {
    type: String,
    default: ''
  }
```

- [ ] **Step 2: `GameFormModal.vue` — add to `formData`**

Update the `formData` ref (lines 304-311):

```js
const formData = ref({
  releaseDate: '',
  platforms: [],
  primaryGenreId: '',
  secondaryGenreIds: [],
  isTba: false,
  isEarlyAccess: false,
  videoUrl: ''
});
```

- [ ] **Step 3: `GameFormModal.vue` — add the input field**

In the template, immediately after the Release Date block (after its closing `</div>` on line 90, before the Platforms block), add:

```html
            <!-- Trailer URL (YouTube) -->
            <div v-if="mode === 'add' || mode === 'edit'">
              <label class="block text-sm font-medium text-gray-300 mb-2">
                Trailer URL (YouTube)
              </label>
              <input
                v-model="formData.videoUrl"
                type="url"
                placeholder="https://www.youtube.com/watch?v=…"
                class="w-full px-4 py-2 rounded-lg bg-gray-700 text-white border border-gray-600 focus:outline-none focus:ring-2"
                :class="modeStyles.focusRing"
              >
              <p class="mt-1 text-xs text-gray-400">
                Shown as a play button on the list row. Leave empty for none.
              </p>
            </div>
```

- [ ] **Step 4: `GameFormModal.vue` — initialise in `resetForm()` and emit on submit**

In `resetForm()` (lines 445-452), add `videoUrl`:

```js
  formData.value = {
    releaseDate: props.initialReleaseDate || props.game?.release_date || '',
    platforms: platformIds,
    primaryGenreId: props.initialPrimaryGenreId || '',
    secondaryGenreIds: secondaryIds,
    isTba: props.initialIsTba,
    isEarlyAccess: props.initialIsEarlyAccess,
    videoUrl: props.initialVideoUrl || ''
  };
```

In `handleSubmit()` (the `emit('submit', {...})` on lines 487-494), add `videoUrl`:

```js
  emit('submit', {
    releaseDate: formData.value.isTba ? null : formData.value.releaseDate,
    platforms: formData.value.platforms,
    primaryGenreId: formData.value.primaryGenreId,
    genreIds: allGenreIds,
    isTba: formData.value.isTba,
    isEarlyAccess: formData.value.isEarlyAccess,
    videoUrl: formData.value.videoUrl || null
  });
```

Then add a watcher next to the other `initial*` watchers (after the `initialIsEarlyAccess` watcher, ~line 545):

```js
watch(() => props.initialVideoUrl, (val) => {
  if (props.show) {
    formData.value.videoUrl = val || '';
  }
});
```

- [ ] **Step 5: `GameEditModals.vue` — carry `video_url` in and out**

(a) Add a ref next to the other initial refs (after `const initialGenreIds = ref([]);`, line 108):

```js
const initialGenreIds = ref([]);
const initialVideoUrl = ref('');
```

(b) In `openModal()`, after `initialGenreIds.value = data.genre_ids || [];` (line 160), add:

```js
      initialGenreIds.value = data.genre_ids || [];
      initialVideoUrl.value = data.video_url || '';
```

(c) In `closeModal()`, after `initialGenreIds.value = [];` (line 204), add:

```js
  initialGenreIds.value = [];
  initialVideoUrl.value = '';
```

(d) Pass the prop to `<GameFormModal>` in the template (after `:initial-genre-ids="initialGenreIds"`, line 18):

```html
    :initial-genre-ids="initialGenreIds"
    :initial-video-url="initialVideoUrl"
```

(e) In `performEdit()`, add `video_url` to the request `body` (after `genre_ids:` line 242):

```js
    genre_ids: formData.genreIds || [],
    video_url: formData.videoUrl || null,
```

- [ ] **Step 6: `AddGameToList.vue` — send `video_url` on add**

In `handleFormSubmit()`, after the `is_early_access` append (line 273), add:

```js
    submitData.append('is_early_access', formData.isEarlyAccess ? '1' : '0');

    if (formData.videoUrl) {
      submitData.append('video_url', formData.videoUrl);
    }
```

- [ ] **Step 7: Build assets**

Run: `nvm use 24 && npm run build`
Expected: Vite build succeeds.

- [ ] **Step 8: Manual verification**

1. Log in as an admin, open `/admin/system-lists/events/<an-events-slug>/edit`.
2. Add a game → the modal shows a **Trailer URL (YouTube)** field. Paste a watch URL, submit.
3. Edit the same game → the field is pre-filled with the saved URL; change/clear it, save.
4. Open `/list/events/<slug>` → the row shows the trailer thumbnail; clicking it opens the lightbox and plays.

- [ ] **Step 9: Commit**

```bash
git add resources/js/components/GameFormModal.vue resources/js/components/GameEditModals.vue resources/js/components/AddGameToList.vue
git commit -m "feat(admin): trailer URL input in the list-item add/edit modal"
```

---

## Task 7: Finalize — lint, full suite, cleanup

- [ ] **Step 1: Format with Pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: any style issues auto-fixed.

- [ ] **Step 2: Run the touched tests together**

Run: `XDEBUG_MODE=off php artisan test --compact tests/Unit/YouTubeTest.php tests/Feature/AdminListVideoUrlTest.php tests/Feature/GameCardTableRowTest.php tests/Feature/EventsListViewTest.php`
Expected: PASS — all green.

- [ ] **Step 3: Remove the throwaway preview**

```bash
git rm docs/previews/list-layout-preview.html
```

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "chore: pint formatting and remove list-layout preview"
```

- [ ] **Step 5: Offer to run the full suite**

Ask the user whether to run the entire suite (`XDEBUG_MODE=off php artisan test --compact`) before finishing.

---

## Self-review notes

- **Spec coverage:** default-list (Task 5) ✓; remove buttons in list view only (Task 4 — `table-row` only; grid untouched) ✓; per-row `video_url` storage (Task 2) ✓; admin entry (Tasks 3, 6) ✓; thumbnail + lightbox reuse via `data-video-id` (Task 4) ✓; shared YouTube helper + refactor existing callers same change (Task 1) ✓; date chip (Task 4) ✓; responsive shrink 84→108px (Task 4) ✓; YouTube-only, Twitch untouched (Task 1 leaves Twitch branches) ✓; tests (Tasks 1,3,4,5) ✓.
- **Type/name consistency:** `YouTube::idFromUrl()` used identically in helper, model, blade, controller rule, and card. Prop `videoUrl` (Blade) maps to pivot `video_url`; Vue uses `videoUrl` in JS and `video_url` over the wire — matching the controller's `$request->input('video_url')`.
- **No placeholders:** every code step shows full code; commands include `XDEBUG_MODE=off` and `nvm use 24` per project rules; commits omit the `Co-authored-by` trailer per user preference.
