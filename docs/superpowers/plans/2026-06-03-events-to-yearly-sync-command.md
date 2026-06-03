# Events → Yearly Sync Command — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** An artisan command `events:sync-to-yearly` that copies games (release date, platforms, genres, flags, YouTube trailer) from a system events list into the matching yearly list(s), routed per game by release year, with an interactive picker and non-destructive fill-missing semantics.

**Architecture:** A thin command delegates to two services. `GameListSyncService` holds reusable primitives (`findYearlyList`, `firstOrCreateYearlyList`, `resolvePlatforms`, `insertGame`, `fillMissing`); the existing `CreateSystemList` command and `AdminListController::syncIndieToYearlyList` are refactored onto these primitives in the same PR. `EventYearlySyncService` orchestrates `plan()` (for the picker) and `apply()` (the DB writes).

**Tech Stack:** Laravel 12, PHP 8.4, Pest 4, Laravel Prompts (`multiselect`).

**Prerequisite / branching:** This depends on the per-game `video_url` pivot column and `GameList::games()->withPivot('video_url')` from the `feat/events-list-trailers` branch. Create the implementation branch off `main` **after** that work is merged, or branch off `feat/events-list-trailers`. Verify `Schema::hasColumn('game_list_game','video_url')` is true before starting.

**Project rules (MANDATORY):**
- Tests with Xdebug off: `XDEBUG_MODE=off php artisan test --compact <path>`.
- `vendor/bin/pint --dirty --format agent` before each commit.
- Conventional Commits; **no `Co-authored-by` trailer.**
- PHP 8.4: `declare(strict_types=1)` (match `app/Support` siblings), curly braces always, explicit return types, constructor property promotion, PHPDoc array shapes.

---

## File structure

| File | Responsibility | Action |
|---|---|---|
| `app/Services/GameListSyncService.php` | Reusable yearly-list + pivot primitives | Create |
| `app/Services/EventYearlySyncService.php` | Plan + apply event→yearly sync | Create |
| `app/Console/Commands/SyncEventToYearly.php` | CLI: resolve source, picker, summary | Create |
| `app/Console/Commands/CreateSystemList.php` | Reuse `firstOrCreateYearlyList` | Modify |
| `app/Http/Controllers/AdminListController.php` | `syncIndieToYearlyList` reuse primitives + inject service | Modify |
| `tests/Unit/GameListSyncServiceTest.php` | Unit-test primitives | Create |
| `tests/Feature/SyncEventToYearlyCommandTest.php` | Feature-test command + apply | Create |
| `tests/Feature/CreateSystemListCommandTest.php` | Guard the CreateSystemList refactor | Create |

---

## Task 1: `GameListSyncService` — yearly list find/create (+ refactor `CreateSystemList`)

**Files:**
- Create: `app/Services/GameListSyncService.php`
- Test: `tests/Unit/GameListSyncServiceTest.php`
- Modify: `app/Console/Commands/CreateSystemList.php`
- Create: `tests/Feature/CreateSystemListCommandTest.php`

- [ ] **Step 1: Create the unit test**

Run: `php artisan make:test --pest --unit GameListSyncServiceTest --no-interaction`
Then replace its contents with:

```php
<?php

use App\Models\GameList;
use App\Services\GameListSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function syncService(): GameListSyncService
{
    return app(GameListSyncService::class);
}

it('finds nothing when no yearly list exists for the year', function () {
    expect(syncService()->findYearlyList(2031))->toBeNull();
});

it('creates a yearly system list with the expected attributes', function () {
    $list = syncService()->firstOrCreateYearlyList(2031);

    expect($list->list_type->value)->toBe('yearly')
        ->and($list->is_system)->toBeTrue()
        ->and($list->is_public)->toBeTrue()
        ->and($list->is_active)->toBeTrue()
        ->and($list->name)->toBe('Game Releases 2031')
        ->and($list->slug)->toBe('game-releases-2031')
        ->and($list->start_at->format('Y-m-d'))->toBe('2031-01-01')
        ->and($list->end_at->format('Y-m-d'))->toBe('2031-12-31');
});

it('returns the existing yearly list instead of creating a duplicate', function () {
    $first = syncService()->firstOrCreateYearlyList(2031);
    $second = syncService()->firstOrCreateYearlyList(2031);

    expect($second->id)->toBe($first->id)
        ->and(GameList::yearly()->whereYear('start_at', 2031)->count())->toBe(1);
});
```

- [ ] **Step 2: Run it to verify failure**

Run: `XDEBUG_MODE=off php artisan test --compact tests/Unit/GameListSyncServiceTest.php`
Expected: FAIL — `Class "App\Services\GameListSyncService" not found`.

- [ ] **Step 3: Create the service (find/create only for now)**

Create `app/Services/GameListSyncService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ListTypeEnum;
use App\Models\GameList;
use Carbon\Carbon;
use Illuminate\Support\Str;

class GameListSyncService
{
    public function findYearlyList(int $year): ?GameList
    {
        return GameList::yearly()
            ->where('is_system', true)
            ->whereYear('start_at', $year)
            ->first();
    }

    public function firstOrCreateYearlyList(int $year): GameList
    {
        if ($existing = $this->findYearlyList($year)) {
            return $existing;
        }

        $name = "Game Releases {$year}";

        return GameList::create([
            'user_id' => 1,
            'name' => $name,
            'description' => "Curated game releases for {$year}",
            'slug' => $this->uniqueYearlySlug($name),
            'is_public' => true,
            'is_system' => true,
            'is_active' => true,
            'list_type' => ListTypeEnum::YEARLY->value,
            'start_at' => Carbon::create($year, 1, 1)->startOfDay(),
            'end_at' => Carbon::create($year, 12, 31)->endOfDay(),
        ]);
    }

    private function uniqueYearlySlug(string $name): string
    {
        $slug = Str::slug($name);
        $original = $slug;
        $counter = 1;

        while (GameList::where('slug', $slug)->where('list_type', ListTypeEnum::YEARLY->value)->exists()) {
            $slug = $original.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
```

- [ ] **Step 4: Run it to verify pass**

Run: `XDEBUG_MODE=off php artisan test --compact tests/Unit/GameListSyncServiceTest.php`
Expected: PASS (3 cases).

- [ ] **Step 5: Refactor `CreateSystemList` to reuse the service**

In `app/Console/Commands/CreateSystemList.php`:

(a) Replace the imports block (lines 3-9) so only what's still used remains, and inject the service:

```php
<?php

namespace App\Console\Commands;

use App\Services\GameListSyncService;
use Illuminate\Console\Command;
```

(b) Change `handle()` to inject the service and pass it through:

```php
    public function handle(GameListSyncService $sync): int
    {
        $type = $this->argument('type');
        $year = (int) $this->argument('year');

        if ($year < 2000 || $year > 2100) {
            $this->error('Invalid year. Please enter a year between 2000 and 2100.');

            return Command::FAILURE;
        }

        return match ($type) {
            'yearly' => $this->createYearlyList($year, $sync),
            default => $this->invalidType($type),
        };
    }
```

(c) Replace `createYearlyList()` entirely with:

```php
    private function createYearlyList(int $year, GameListSyncService $sync): int
    {
        $this->info("Creating yearly list for year: {$year}");

        if ($existing = $sync->findYearlyList($year)) {
            $this->error("A yearly list already exists for {$year}: '{$existing->name}' (ID: {$existing->id})");

            return Command::FAILURE;
        }

        $gameList = $sync->firstOrCreateYearlyList($year);

        $this->info("Created: {$gameList->name} (ID: {$gameList->id}, Slug: {$gameList->slug})");
        $this->line("  Start: {$gameList->start_at->format('Y-m-d')} | End: {$gameList->end_at->format('Y-m-d')}");

        return Command::SUCCESS;
    }
```

(d) **Delete** the now-unused `private function generateUniqueSlug(...)` method entirely (its logic moved to the service). Leave `invalidType()` as-is.

- [ ] **Step 6: Add a guard test for the refactored command**

Create `tests/Feature/CreateSystemListCommandTest.php`:

```php
<?php

use App\Models\GameList;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a yearly system list via the command', function () {
    $this->artisan('system-list:create', ['type' => 'yearly', 'year' => 2031])
        ->assertSuccessful();

    expect(GameList::yearly()->whereYear('start_at', 2031)->where('is_system', true)->exists())->toBeTrue();
});

it('refuses to create a duplicate yearly list', function () {
    $this->artisan('system-list:create', ['type' => 'yearly', 'year' => 2031])->assertSuccessful();

    $this->artisan('system-list:create', ['type' => 'yearly', 'year' => 2031])->assertFailed();

    expect(GameList::yearly()->whereYear('start_at', 2031)->count())->toBe(1);
});
```

- [ ] **Step 7: Run both test files**

Run: `XDEBUG_MODE=off php artisan test --compact tests/Unit/GameListSyncServiceTest.php tests/Feature/CreateSystemListCommandTest.php`
Expected: PASS (5 cases).

- [ ] **Step 8: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/GameListSyncService.php tests/Unit/GameListSyncServiceTest.php app/Console/Commands/CreateSystemList.php tests/Feature/CreateSystemListCommandTest.php
git commit -m "feat(lists): GameListSyncService yearly find/create; reuse in CreateSystemList"
```

---

## Task 2: `GameListSyncService` — pivot primitives (`resolvePlatforms`, `insertGame`, `fillMissing`)

**Files:**
- Modify: `app/Services/GameListSyncService.php`
- Modify: `tests/Unit/GameListSyncServiceTest.php`

- [ ] **Step 1: Add failing tests**

Append to `tests/Unit/GameListSyncServiceTest.php` (add the `Game` import at the top: `use App\Models\Game;`):

```php
it('resolvePlatforms decodes a json pivot string', function () {
    $game = Game::factory()->create();

    expect(syncService()->resolvePlatforms($game, '[6,48]'))->toBe([6, 48]);
});

it('resolvePlatforms returns an integer array unchanged', function () {
    $game = Game::factory()->create();

    expect(syncService()->resolvePlatforms($game, [6, 48]))->toBe([6, 48]);
});

it('insertGame attaches with order, platform_group, encoded platforms and video_url', function () {
    $list = syncService()->firstOrCreateYearlyList(2031);
    $game = Game::factory()->create();

    syncService()->insertGame($list, $game, [
        'release_date' => '2031-03-14',
        'platforms' => [6, 48],
        'is_tba' => false,
        'is_early_access' => false,
        'genre_ids' => [5],
        'primary_genre_id' => 5,
        'video_url' => 'https://youtu.be/dQw4w9WgXcQ',
    ]);

    $pivot = $list->games()->where('games.id', $game->id)->first()->pivot;
    expect($pivot->order)->toBe(1)
        ->and(json_decode($pivot->platforms, true))->toBe([6, 48])
        ->and($pivot->video_url)->toBe('https://youtu.be/dQw4w9WgXcQ')
        ->and($pivot->platform_group)->not->toBeNull();
});

it('fillMissing only fills empty fields and reports them', function () {
    $list = syncService()->firstOrCreateYearlyList(2031);
    $game = Game::factory()->create();

    // Existing row: has platforms + release_date, but no video_url.
    $list->games()->attach($game->id, [
        'order' => 1,
        'release_date' => '2031-05-01',
        'platforms' => json_encode([6]),
        'video_url' => null,
    ]);

    $filled = syncService()->fillMissing($list, $game, [
        'release_date' => '2031-09-09',
        'platforms' => [6, 48],
        'video_url' => 'https://youtu.be/dQw4w9WgXcQ',
    ]);

    $pivot = $list->games()->where('games.id', $game->id)->first()->pivot;
    expect($filled)->toBe(['video_url'])
        ->and($pivot->video_url)->toBe('https://youtu.be/dQw4w9WgXcQ')
        ->and($pivot->release_date)->not->toBeNull()
        ->and(\Carbon\Carbon::parse($pivot->release_date)->format('Y-m-d'))->toBe('2031-05-01') // unchanged
        ->and(json_decode($pivot->platforms, true))->toBe([6]); // unchanged
});
```

- [ ] **Step 2: Run to verify failure**

Run: `XDEBUG_MODE=off php artisan test --compact tests/Unit/GameListSyncServiceTest.php`
Expected: FAIL — `Call to undefined method ... resolvePlatforms()`.

- [ ] **Step 3: Add the primitives to the service**

In `app/Services/GameListSyncService.php`, add these imports under the existing ones:

```php
use App\Enums\PlatformEnum;
use App\Enums\PlatformGroupEnum;
use App\Models\Game;
```

Then add these methods to the class (after `firstOrCreateYearlyList`, before `uniqueYearlySlug`):

```php
    /**
     * Decode pivot platform ids, falling back to the game's active platforms when empty.
     *
     * @return list<int>
     */
    public function resolvePlatforms(Game $game, mixed $pivotPlatforms): array
    {
        $platforms = $pivotPlatforms;

        if (is_string($platforms)) {
            $platforms = json_decode($platforms, true) ?? [];
        }

        if (! is_array($platforms) || empty($platforms)) {
            $game->loadMissing('platforms');
            $platforms = $game->platforms
                ->filter(fn ($p) => PlatformEnum::getActivePlatforms()->has($p->igdb_id))
                ->map(fn ($p) => $p->igdb_id)
                ->values()
                ->toArray();
        }

        return array_map('intval', $platforms);
    }

    /**
     * Attach a game to a list, computing order + platform_group and encoding json fields.
     *
     * @param  array{release_date?: mixed, platforms?: list<int>, is_tba?: bool, is_early_access?: bool, is_indie?: bool, is_highlight?: bool, genre_ids?: list<int>, primary_genre_id?: int|null, video_url?: string|null}  $attrs
     */
    public function insertGame(GameList $list, Game $game, array $attrs): void
    {
        $platforms = array_values($attrs['platforms'] ?? []);
        $maxOrder = $list->games()->max('order') ?? 0;

        $list->games()->attach($game->id, [
            'order' => $maxOrder + 1,
            'release_date' => $attrs['release_date'] ?? null,
            'platforms' => json_encode($platforms),
            'platform_group' => PlatformGroupEnum::suggestFromPlatforms($platforms)->value,
            'is_tba' => $attrs['is_tba'] ?? false,
            'is_early_access' => $attrs['is_early_access'] ?? false,
            'is_indie' => $attrs['is_indie'] ?? false,
            'is_highlight' => $attrs['is_highlight'] ?? false,
            'genre_ids' => json_encode(array_values($attrs['genre_ids'] ?? [])),
            'primary_genre_id' => $attrs['primary_genre_id'] ?? null,
            'video_url' => $attrs['video_url'] ?? null,
        ]);
    }

    /**
     * Set only the empty fields on an existing pivot row. Returns the names of fields filled.
     *
     * @param  array{release_date?: mixed, platforms?: list<int>, video_url?: string|null}  $candidate
     * @return list<string>
     */
    public function fillMissing(GameList $list, Game $game, array $candidate): array
    {
        $pivot = $list->games()->where('games.id', $game->id)->first()?->pivot;

        if (! $pivot) {
            return [];
        }

        $update = [];
        $filled = [];

        if (empty($pivot->video_url) && ! empty($candidate['video_url'])) {
            $update['video_url'] = $candidate['video_url'];
            $filled[] = 'video_url';
        }

        if (empty($pivot->release_date) && ! empty($candidate['release_date'])) {
            $update['release_date'] = $candidate['release_date'];
            $filled[] = 'release_date';
        }

        $existingPlatforms = $pivot->platforms;
        if (is_string($existingPlatforms)) {
            $existingPlatforms = json_decode($existingPlatforms, true) ?? [];
        }
        if (empty($existingPlatforms) && ! empty($candidate['platforms'])) {
            $update['platforms'] = json_encode(array_values($candidate['platforms']));
            $filled[] = 'platforms';
        }

        if ($update) {
            $list->games()->updateExistingPivot($game->id, $update);
        }

        return $filled;
    }
```

- [ ] **Step 4: Run to verify pass**

Run: `XDEBUG_MODE=off php artisan test --compact tests/Unit/GameListSyncServiceTest.php`
Expected: PASS (7 cases).

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/GameListSyncService.php tests/Unit/GameListSyncServiceTest.php
git commit -m "feat(lists): add resolvePlatforms/insertGame/fillMissing pivot primitives"
```

---

## Task 3: Refactor `AdminListController::syncIndieToYearlyList` onto the primitives

**Files:**
- Modify: `app/Http/Controllers/AdminListController.php`
- Test: `tests/Feature/SyncIndieToYearlyTest.php`

`AdminListController` currently has **no constructor**. Add one (constructor property promotion) to inject the service.

- [ ] **Step 1: Write a regression test for the seasoned → yearly indie insert path**

Run: `php artisan make:test --pest SyncIndieToYearlyTest --no-interaction`
Replace its contents with:

```php
<?php

use App\Models\Game;
use App\Models\GameList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('inserts a game into the matching yearly list when marked indie on a seasoned list', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $seasoned = GameList::factory()->seasoned()->system()->create([
        'slug' => 'summer-2031',
        'start_at' => now()->setDate(2031, 6, 1),
        'end_at' => now()->setDate(2031, 8, 31),
    ]);
    $yearly = GameList::factory()->yearly()->system()->create([
        'slug' => 'game-releases-2031',
        'start_at' => now()->setDate(2031, 1, 1),
        'end_at' => now()->setDate(2031, 12, 31),
    ]);

    $game = Game::factory()->create();
    $seasoned->games()->attach($game->id, [
        'order' => 1,
        'release_date' => now()->setDate(2031, 7, 15),
        'platforms' => json_encode([6]),
    ]);

    $this->actingAs($admin)
        ->patchJson('/admin/system-lists/seasoned/summer-2031/games/'.$game->id.'/indie', [
            'is_indie' => true,
        ])
        ->assertJson(['success' => true]);

    $pivot = $yearly->games()->where('games.id', $game->id)->first()?->pivot;
    expect($pivot)->not->toBeNull()
        ->and((bool) $pivot->is_indie)->toBeTrue();
});
```

> Note: confirm the indie route param/shape against `routes/web.php` (`admin.system-lists.games.toggle-indie`, `PATCH .../games/{game:id}/indie`). If the request key differs from `is_indie`, match the controller's `toggleGameIndie` expectations. Run the test first to surface the real contract.

- [ ] **Step 2: Run to verify it passes BEFORE refactor (it documents current behaviour)**

Run: `XDEBUG_MODE=off php artisan test --compact tests/Feature/SyncIndieToYearlyTest.php`
Expected: PASS against the current (pre-refactor) code. If it fails, fix the test to match the real route/request shape until it passes — this is the regression baseline.

- [ ] **Step 3: Inject the service via a constructor**

In `app/Http/Controllers/AdminListController.php`, add the import (with the others, alphabetically near `use App\Services\IgdbService;`):

```php
use App\Services\GameListSyncService;
```

Add a constructor at the top of the class body (immediately after the opening `{` of the class, before the first method):

```php
    public function __construct(private GameListSyncService $sync) {}
```

- [ ] **Step 4: Refactor `syncIndieToYearlyList` to use the primitives**

Replace the entire `syncIndieToYearlyList(...)` method body with:

```php
    protected function syncIndieToYearlyList(GameList $sourceList, Game $game, $pivotData, bool $isIndie, ?array $genreIds = null, ?int $primaryGenreId = null, mixed $releaseDate = null, bool $isTba = false, mixed $platforms = null, bool $isEarlyAccess = false): void
    {
        $year = $sourceList->start_at?->year ?? now()->year;

        $yearlyList = $this->sync->findYearlyList($year);
        if (! $yearlyList) {
            return;
        }

        $alreadyInList = $yearlyList->games()->where('games.id', $game->id)->exists();

        if ($isIndie) {
            if ($alreadyInList) {
                $yearlyList->games()->updateExistingPivot($game->id, ['is_indie' => true]);

                return;
            }

            $this->sync->insertGame($yearlyList, $game, [
                'release_date' => $releaseDate ?? $pivotData->release_date,
                'platforms' => $this->sync->resolvePlatforms($game, $platforms ?? $pivotData->platforms),
                'is_tba' => $isTba,
                'is_early_access' => $isEarlyAccess,
                'is_indie' => true,
                'genre_ids' => $genreIds ?? [],
                'primary_genre_id' => $primaryGenreId,
            ]);

            return;
        }

        if ($alreadyInList) {
            $yearlyList->games()->updateExistingPivot($game->id, ['is_indie' => false]);
        }
    }
```

- [ ] **Step 5: Run the regression test + the existing indie-related tests**

Run: `XDEBUG_MODE=off php artisan test --compact tests/Feature/SyncIndieToYearlyTest.php tests/Feature/EditPivotDataTest.php tests/Feature/MultiGenreTest.php`
Expected: PASS — behaviour preserved.

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/AdminListController.php tests/Feature/SyncIndieToYearlyTest.php
git commit -m "refactor(admin): syncIndieToYearlyList reuses GameListSyncService primitives"
```

---

## Task 4: `EventYearlySyncService::plan()`

**Files:**
- Create: `app/Services/EventYearlySyncService.php`
- Test: `tests/Feature/SyncEventToYearlyCommandTest.php` (start it here with plan() tests)

- [ ] **Step 1: Write failing tests for `plan()`**

Run: `php artisan make:test --pest SyncEventToYearlyCommandTest --no-interaction`
Replace its contents with:

```php
<?php

use App\Models\Game;
use App\Models\GameList;
use App\Services\EventYearlySyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function eventWithGames(): array
{
    $event = GameList::factory()->events()->system()->create([
        'slug' => 'nacon-connect-2026',
        'start_at' => now()->setDate(2026, 6, 2),
        'end_at' => now()->setDate(2026, 6, 2),
    ]);

    $in2026 = Game::factory()->create(['name' => 'Game A']);
    $in2028 = Game::factory()->create(['name' => 'Game B']);
    $tba = Game::factory()->create(['name' => 'Game C']);

    $event->games()->attach($in2026->id, ['order' => 1, 'release_date' => now()->setDate(2026, 9, 1), 'video_url' => 'https://youtu.be/dQw4w9WgXcQ']);
    $event->games()->attach($in2028->id, ['order' => 2, 'release_date' => now()->setDate(2028, 3, 1)]);
    $event->games()->attach($tba->id, ['order' => 3, 'is_tba' => true]);

    return [$event->fresh('games'), $in2026, $in2028, $tba];
}

it('routes each game to the yearly list for its own release year, TBA to the event year', function () {
    [$event, $in2026, $in2028, $tba] = eventWithGames();

    $plan = app(EventYearlySyncService::class)->plan($event);
    $byId = collect($plan)->keyBy(fn ($p) => $p['game']->id);

    expect($byId[$in2026->id]['target_year'])->toBe(2026)
        ->and($byId[$in2026->id]['has_video'])->toBeTrue()
        ->and($byId[$in2028->id]['target_year'])->toBe(2028)
        ->and($byId[$tba->id]['target_year'])->toBe(2026) // event year
        ->and($byId[$tba->id]['release_label'])->toBe('TBA');
});

it('marks a game already complete in its yearly list as skip', function () {
    [$event, $in2026] = eventWithGames();

    $yearly = GameList::factory()->yearly()->system()->create([
        'slug' => 'game-releases-2026',
        'start_at' => now()->setDate(2026, 1, 1),
        'end_at' => now()->setDate(2026, 12, 31),
    ]);
    // Already present with a video_url + platforms + date → nothing to fill.
    $yearly->games()->attach($in2026->id, [
        'order' => 1,
        'release_date' => now()->setDate(2026, 9, 1),
        'platforms' => json_encode([6]),
        'video_url' => 'https://youtu.be/dQw4w9WgXcQ',
    ]);

    $plan = app(EventYearlySyncService::class)->plan($event);
    $entry = collect($plan)->firstWhere(fn ($p) => $p['game']->id === $in2026->id);

    expect($entry['action'])->toBe('skip')
        ->and($entry['fills'])->toBe([]);
});
```

- [ ] **Step 2: Run to verify failure**

Run: `XDEBUG_MODE=off php artisan test --compact tests/Feature/SyncEventToYearlyCommandTest.php`
Expected: FAIL — `Class "App\Services\EventYearlySyncService" not found`.

- [ ] **Step 3: Create the service with `plan()` (and helpers used by `apply()` next)**

Create `app/Services/EventYearlySyncService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Game;
use App\Models\GameList;
use App\Support\YouTube;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class EventYearlySyncService
{
    public function __construct(private GameListSyncService $sync) {}

    /**
     * Per-game sync plan for the picker / dry display.
     *
     * @return list<array{game: Game, name: string, release_label: string, target_year: int, has_video: bool, action: string, fills: list<string>}>
     */
    public function plan(GameList $eventList): array
    {
        $eventYear = $eventList->start_at?->year ?? now()->year;
        $plan = [];

        foreach ($eventList->games as $game) {
            $pivot = $game->pivot;
            $isTba = (bool) ($pivot->is_tba ?? false);
            $date = $this->resolveDate($pivot, $game);
            $targetYear = ($isTba || ! $date) ? $eventYear : $date->year;
            $videoUrl = $pivot->video_url ?? null;

            [$action, $fills] = $this->decide($targetYear, $game, $pivot, $date, $videoUrl, $isTba);

            $plan[] = [
                'game' => $game,
                'name' => $game->name,
                'release_label' => ($isTba || ! $date) ? 'TBA' : $date->format('j M Y'),
                'target_year' => $targetYear,
                'has_video' => ! empty(YouTube::idFromUrl($videoUrl)),
                'action' => $action,
                'fills' => $fills,
            ];
        }

        return $plan;
    }

    /**
     * @return array{0: string, 1: list<string>}
     */
    private function decide(int $targetYear, Game $game, $pivot, ?Carbon $date, ?string $videoUrl, bool $isTba): array
    {
        $yearly = $this->sync->findYearlyList($targetYear);
        if (! $yearly) {
            return ['insert', []];
        }

        $row = $yearly->games()->where('games.id', $game->id)->first()?->pivot;
        if (! $row) {
            return ['insert', []];
        }

        $fills = [];
        if (empty($row->video_url) && ! empty($videoUrl)) {
            $fills[] = 'video_url';
        }
        if (empty($row->release_date) && ! $isTba && $date) {
            $fills[] = 'release_date';
        }
        $existingPlatforms = $row->platforms;
        if (is_string($existingPlatforms)) {
            $existingPlatforms = json_decode($existingPlatforms, true) ?? [];
        }
        if (empty($existingPlatforms) && ! empty($this->sync->resolvePlatforms($game, $pivot->platforms ?? null))) {
            $fills[] = 'platforms';
        }

        return [$fills ? 'fill' : 'skip', $fills];
    }

    private function resolveDate($pivot, Game $game): ?Carbon
    {
        $date = $pivot->release_date ?? null;

        if ($date instanceof Carbon) {
            return $date;
        }
        if (is_string($date) && $date !== '') {
            return Carbon::parse($date);
        }

        return $game->first_release_date;
    }
}
```

- [ ] **Step 4: Run to verify pass**

Run: `XDEBUG_MODE=off php artisan test --compact tests/Feature/SyncEventToYearlyCommandTest.php`
Expected: PASS (2 cases).

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/EventYearlySyncService.php tests/Feature/SyncEventToYearlyCommandTest.php
git commit -m "feat(lists): EventYearlySyncService plan() with per-game year routing"
```

---

## Task 5: `EventYearlySyncService::apply()`

**Files:**
- Modify: `app/Services/EventYearlySyncService.php`
- Modify: `tests/Feature/SyncEventToYearlyCommandTest.php`

- [ ] **Step 1: Add failing tests for `apply()`**

Append to `tests/Feature/SyncEventToYearlyCommandTest.php`:

```php
it('inserts games into the correct yearly lists, auto-creating a missing one', function () {
    [$event, $in2026, $in2028, $tba] = eventWithGames();

    $result = app(EventYearlySyncService::class)->apply($event, [$in2026->id, $in2028->id, $tba->id]);

    $list2026 = GameList::yearly()->whereYear('start_at', 2026)->first();
    $list2028 = GameList::yearly()->whereYear('start_at', 2028)->first();

    expect($list2026)->not->toBeNull()
        ->and($list2028)->not->toBeNull() // auto-created
        ->and($result['inserted'])->toBe(3)
        ->and($list2026->games()->where('games.id', $in2026->id)->exists())->toBeTrue()
        ->and($list2026->games()->where('games.id', $tba->id)->exists())->toBeTrue() // TBA -> event year
        ->and($list2028->games()->where('games.id', $in2028->id)->exists())->toBeTrue();

    $videoPivot = $list2026->games()->where('games.id', $in2026->id)->first()->pivot;
    expect($videoPivot->video_url)->toBe('https://youtu.be/dQw4w9WgXcQ');

    $tbaPivot = $list2026->games()->where('games.id', $tba->id)->first()->pivot;
    expect((bool) $tbaPivot->is_tba)->toBeTrue();
});

it('fills a missing video_url on an existing year row without overwriting curated fields', function () {
    [$event, $in2026] = eventWithGames();

    $yearly = GameList::factory()->yearly()->system()->create([
        'slug' => 'game-releases-2026',
        'start_at' => now()->setDate(2026, 1, 1),
        'end_at' => now()->setDate(2026, 12, 31),
    ]);
    $yearly->games()->attach($in2026->id, [
        'order' => 1,
        'release_date' => now()->setDate(2026, 12, 25), // curated date, must NOT change
        'platforms' => json_encode([6]),                // curated, must NOT change
        'video_url' => null,                            // missing -> should be filled
        'is_highlight' => true,                         // curated flag, must NOT change
    ]);

    $result = app(EventYearlySyncService::class)->apply($event, [$in2026->id]);

    $pivot = $yearly->games()->where('games.id', $in2026->id)->first()->pivot;
    expect($result['inserted'])->toBe(0)
        ->and($result['filled'])->toHaveKey($in2026->id)
        ->and($pivot->video_url)->toBe('https://youtu.be/dQw4w9WgXcQ')
        ->and(\Carbon\Carbon::parse($pivot->release_date)->format('Y-m-d'))->toBe('2026-12-25')
        ->and(json_decode($pivot->platforms, true))->toBe([6])
        ->and((bool) $pivot->is_highlight)->toBeTrue();
});

it('skips a game that is already complete in the year list', function () {
    [$event, $in2026] = eventWithGames();

    $yearly = GameList::factory()->yearly()->system()->create([
        'slug' => 'game-releases-2026',
        'start_at' => now()->setDate(2026, 1, 1),
        'end_at' => now()->setDate(2026, 12, 31),
    ]);
    $yearly->games()->attach($in2026->id, [
        'order' => 1,
        'release_date' => now()->setDate(2026, 9, 1),
        'platforms' => json_encode([6]),
        'video_url' => 'https://youtu.be/dQw4w9WgXcQ',
    ]);

    $result = app(EventYearlySyncService::class)->apply($event, [$in2026->id]);

    expect($result['skipped'])->toBe(1)
        ->and($result['inserted'])->toBe(0)
        ->and($yearly->games()->where('games.id', $in2026->id)->count())->toBe(1); // no duplicate
});
```

- [ ] **Step 2: Run to verify failure**

Run: `XDEBUG_MODE=off php artisan test --compact tests/Feature/SyncEventToYearlyCommandTest.php`
Expected: FAIL — `Call to undefined method ... apply()`.

- [ ] **Step 3: Add `apply()` to the service**

In `app/Services/EventYearlySyncService.php`, add this method after `plan()`:

```php
    /**
     * Apply the sync for the chosen game ids. Auto-creates missing yearly lists.
     *
     * @param  list<int>  $gameIds
     * @return array{created_years: list<int>, inserted: int, filled: array<int, list<string>>, skipped: int, errors: array<int, string>, per_year: array<int, int>}
     */
    public function apply(GameList $eventList, array $gameIds): array
    {
        $eventYear = $eventList->start_at?->year ?? now()->year;
        $result = [
            'created_years' => [],
            'inserted' => 0,
            'filled' => [],
            'skipped' => 0,
            'errors' => [],
            'per_year' => [],
        ];

        DB::transaction(function () use ($eventList, $gameIds, $eventYear, &$result) {
            foreach ($eventList->games as $game) {
                if (! in_array($game->id, $gameIds, true)) {
                    continue;
                }

                try {
                    $pivot = $game->pivot;
                    $isTba = (bool) ($pivot->is_tba ?? false);
                    $date = $this->resolveDate($pivot, $game);
                    $targetYear = ($isTba || ! $date) ? $eventYear : $date->year;

                    $existed = $this->sync->findYearlyList($targetYear) !== null;
                    $yearly = $this->sync->firstOrCreateYearlyList($targetYear);
                    if (! $existed && ! in_array($targetYear, $result['created_years'], true)) {
                        $result['created_years'][] = $targetYear;
                    }

                    $platforms = $this->sync->resolvePlatforms($game, $pivot->platforms ?? null);
                    $videoUrl = $pivot->video_url ?? null;

                    if ($yearly->games()->where('games.id', $game->id)->exists()) {
                        $filled = $this->sync->fillMissing($yearly, $game, [
                            'release_date' => $isTba ? null : $date,
                            'platforms' => $platforms,
                            'video_url' => $videoUrl,
                        ]);

                        if ($filled) {
                            $result['filled'][$game->id] = $filled;
                        } else {
                            $result['skipped']++;
                        }
                    } else {
                        $this->sync->insertGame($yearly, $game, [
                            'release_date' => $isTba ? null : $date,
                            'platforms' => $platforms,
                            'is_tba' => $isTba,
                            'is_early_access' => (bool) ($pivot->is_early_access ?? false),
                            'is_indie' => false,
                            'is_highlight' => false,
                            'genre_ids' => $this->decodeIntArray($pivot->genre_ids ?? null),
                            'primary_genre_id' => $pivot->primary_genre_id ?? null,
                            'video_url' => $videoUrl,
                        ]);
                        $result['inserted']++;
                    }

                    $result['per_year'][$targetYear] = ($result['per_year'][$targetYear] ?? 0) + 1;
                } catch (\Throwable $e) {
                    $result['errors'][$game->id] = $e->getMessage();
                }
            }
        });

        return $result;
    }

    /**
     * @return list<int>
     */
    private function decodeIntArray(mixed $value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true) ?? [];
        }

        return is_array($value) ? array_map('intval', $value) : [];
    }
```

- [ ] **Step 4: Run to verify pass**

Run: `XDEBUG_MODE=off php artisan test --compact tests/Feature/SyncEventToYearlyCommandTest.php`
Expected: PASS (5 cases).

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/EventYearlySyncService.php tests/Feature/SyncEventToYearlyCommandTest.php
git commit -m "feat(lists): EventYearlySyncService apply() with auto-create + fill-missing"
```

---

## Task 6: `SyncEventToYearly` command

**Files:**
- Create: `app/Console/Commands/SyncEventToYearly.php`
- Modify: `tests/Feature/SyncEventToYearlyCommandTest.php`

- [ ] **Step 1: Add failing command tests**

Append to `tests/Feature/SyncEventToYearlyCommandTest.php` (add `use App\Models\User;` at the top if not present — not required by these, but harmless):

```php
it('syncs all eligible games with --all', function () {
    [$event, $in2026, $in2028, $tba] = eventWithGames();

    $this->artisan('events:sync-to-yearly', ['event' => 'nacon-connect-2026', '--all' => true])
        ->assertSuccessful();

    expect(GameList::yearly()->whereYear('start_at', 2026)->first()->games()->where('games.id', $in2026->id)->exists())->toBeTrue()
        ->and(GameList::yearly()->whereYear('start_at', 2028)->first()->games()->where('games.id', $in2028->id)->exists())->toBeTrue();
});

it('accepts a numeric id as the event argument', function () {
    [$event, $in2026] = eventWithGames();

    $this->artisan('events:sync-to-yearly', ['event' => (string) $event->id, '--all' => true])
        ->assertSuccessful();

    expect(GameList::yearly()->whereYear('start_at', 2026)->first()->games()->where('games.id', $in2026->id)->exists())->toBeTrue();
});

it('fails when the events list does not exist', function () {
    $this->artisan('events:sync-to-yearly', ['event' => 'does-not-exist', '--all' => true])
        ->assertFailed();
});

it('fails when the slug resolves to a non-events list', function () {
    GameList::factory()->yearly()->system()->create(['slug' => 'game-releases-2026']);

    $this->artisan('events:sync-to-yearly', ['event' => 'game-releases-2026', '--all' => true])
        ->assertFailed();
});
```

- [ ] **Step 2: Run to verify failure**

Run: `XDEBUG_MODE=off php artisan test --compact tests/Feature/SyncEventToYearlyCommandTest.php`
Expected: FAIL — command `events:sync-to-yearly` not defined.

- [ ] **Step 3: Create the command**

Create `app/Console/Commands/SyncEventToYearly.php`:

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\GameList;
use App\Services\EventYearlySyncService;
use Illuminate\Console\Command;

use function Laravel\Prompts\multiselect;

class SyncEventToYearly extends Command
{
    protected $signature = 'events:sync-to-yearly
                            {event : Source events list — slug (e.g. nacon-connect-2026) or numeric id}
                            {--all : Sync every eligible game, skipping the interactive picker}';

    protected $description = 'Copy games (release date, platforms, genres, flags, YouTube trailer) from a system events list into the matching yearly list(s), routed per game by release year.';

    public function handle(EventYearlySyncService $service): int
    {
        $arg = (string) $this->argument('event');

        $event = ctype_digit($arg)
            ? GameList::find((int) $arg)
            : GameList::events()->where('slug', $arg)->first();

        if (! $event) {
            $this->error("No events list found for \"{$arg}\".");

            return self::FAILURE;
        }

        if (! $event->isEvents()) {
            $this->error("List \"{$event->name}\" is a {$event->list_type->value} list, not an events list.");

            return self::FAILURE;
        }

        $event->load('games');
        $plan = $service->plan($event);

        if (empty($plan)) {
            $this->warn('No games in this event list — nothing to sync.');

            return self::SUCCESS;
        }

        $selectedIds = $this->option('all')
            ? array_map(fn ($entry) => $entry['game']->id, $plan)
            : $this->promptForSelection($plan);

        if (empty($selectedIds)) {
            $this->warn('No games selected — nothing to sync.');

            return self::SUCCESS;
        }

        $this->renderSummary($service->apply($event, $selectedIds));

        return self::SUCCESS;
    }

    /**
     * @param  list<array{game: \App\Models\Game, name: string, release_label: string, target_year: int, has_video: bool, action: string, fills: list<string>}>  $plan
     * @return list<int>
     */
    private function promptForSelection(array $plan): array
    {
        $options = [];
        foreach ($plan as $entry) {
            $marker = $entry['has_video'] ? ' 🎬' : '';
            $options[(string) $entry['game']->id] = "{$entry['name']} — {$entry['release_label']} → {$entry['target_year']}{$marker}";
        }

        $selected = multiselect(
            label: 'Select games to sync into the yearly list(s)',
            options: $options,
            default: array_keys($options),
            scroll: 20,
            hint: 'Space to toggle, Enter to confirm.',
        );

        return array_map('intval', $selected);
    }

    /**
     * @param  array{created_years: list<int>, inserted: int, filled: array<int, list<string>>, skipped: int, errors: array<int, string>, per_year: array<int, int>}  $result
     */
    private function renderSummary(array $result): void
    {
        if ($result['created_years']) {
            sort($result['created_years']);
            $this->info('Created yearly list(s): '.implode(', ', $result['created_years']));
        }

        $this->info("Inserted: {$result['inserted']}");
        $this->info('Filled: '.count($result['filled']));
        $this->info("Skipped (already complete): {$result['skipped']}");

        if ($result['per_year']) {
            ksort($result['per_year']);
            $this->line('Per year:');
            foreach ($result['per_year'] as $year => $count) {
                $this->line("  {$year}: {$count}");
            }
        }

        foreach ($result['errors'] as $gameId => $message) {
            $this->error("Game #{$gameId}: {$message}");
        }
    }
}
```

- [ ] **Step 4: Run to verify pass**

Run: `XDEBUG_MODE=off php artisan test --compact tests/Feature/SyncEventToYearlyCommandTest.php`
Expected: PASS (all 11 cases in the file: 2 plan + 3 apply + 2 from Task 5 set... i.e. plan(2) + apply(3) + command(4) = 9). Expected: PASS, 9 cases.

- [ ] **Step 5: Manual smoke check (optional, against local DB)**

Run: `php artisan events:sync-to-yearly --help` and confirm the signature/description render. (Do not run a real sync against local data unless intended.)

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Console/Commands/SyncEventToYearly.php tests/Feature/SyncEventToYearlyCommandTest.php
git commit -m "feat(lists): events:sync-to-yearly command with interactive picker"
```

---

## Task 7: Finalize

- [ ] **Step 1: Pint across the change**

Run: `vendor/bin/pint --dirty --format agent`
Expected: `{"result":"pass"}` (or auto-fixes applied).

- [ ] **Step 2: Run all new/affected tests together**

Run:
```bash
XDEBUG_MODE=off php artisan test --compact \
  tests/Unit/GameListSyncServiceTest.php \
  tests/Feature/CreateSystemListCommandTest.php \
  tests/Feature/SyncIndieToYearlyTest.php \
  tests/Feature/SyncEventToYearlyCommandTest.php \
  tests/Feature/EditPivotDataTest.php \
  tests/Feature/MultiGenreTest.php
```
Expected: all PASS.

- [ ] **Step 3: Offer the full suite**

Ask the user whether to run the entire suite (`XDEBUG_MODE=off php artisan test --compact`) before finishing — it touches a shared controller method, so a full run is prudent.

---

## Self-review

- **Spec coverage:** command + arg resolution (Task 6) ✓; per-game year routing + TBA→event year (Task 4) ✓; auto-create missing year list (Tasks 1, 5) ✓; fill-missing-only non-destructive (Tasks 2, 5) ✓; interactive multiselect + `--all` (Task 6) ✓; trailer `video_url` carried (Tasks 2, 5) ✓; reuse + refactor existing callers in same PR — `CreateSystemList` (Task 1) and `syncIndieToYearlyList` (Task 3) ✓; TDD failing-test-first throughout ✓; out-of-scope items (admin UI, overwrite mode, scheduling) not built ✓.
- **Type consistency:** `findYearlyList`/`firstOrCreateYearlyList`/`resolvePlatforms`/`insertGame`/`fillMissing` signatures match across the service, the controller refactor, and `EventYearlySyncService`. Plan-entry array shape (`game,name,release_label,target_year,has_video,action,fills`) is identical in `plan()`, the command's `promptForSelection` docblock. `apply()` result shape (`created_years,inserted,filled,skipped,errors,per_year`) matches `renderSummary`'s docblock and the tests.
- **No placeholders:** every code/step is complete; commands carry `XDEBUG_MODE=off`; commits omit `Co-authored-by`.
- **Note for executor:** Task 3 Step 1 asks you to confirm the real indie route/request shape by running the baseline test first; adjust the test to the actual contract before refactoring (it is the regression guard, not a new behaviour).
