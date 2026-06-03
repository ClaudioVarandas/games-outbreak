# Year-tagged TBA Games — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let an admin tag a TBA game with a `release_year` so event lists show dedicated "2027"/"2028" sections inside the TBA area, and the events→yearly sync routes those games into the correct yearly list.

**Architecture:** A nullable `release_year` pivot column (set only when `is_tba`). `GameList::groupGamesByMonth()` subdivides the TBA bucket by year **for events lists only**. `EventYearlySyncService` resolves a TBA game's target year from `release_year`. The CLI command file is untouched — routing is all in the service.

**Tech Stack:** Laravel 12, PHP 8.4, Vue 3, Pest 4.

**Prerequisite / branching:** Depends on the events→yearly sync (`EventYearlySyncService`, `GameListSyncService`) and the `video_url` pivot work — both already merged to `main`. Branch off `main`. (The unrelated `fix/releases-yearly-trailer` branch is independent.)

**Project rules (MANDATORY):**
- Tests with Xdebug off: `XDEBUG_MODE=off php artisan test --compact <path>`.
- `vendor/bin/pint --dirty --format agent` before each commit.
- Conventional Commits; **no `Co-authored-by` trailer.**
- PHP 8.4: `declare(strict_types=1)` on new class files, curly braces, explicit return types.
- DB-touching tests live in `tests/Feature/` (this project binds the DB TestCase there, not `tests/Unit`).

---

## File structure

| File | Responsibility | Action |
|---|---|---|
| `database/migrations/<ts>_add_release_year_to_game_list_game_table.php` | `release_year` pivot column | Create |
| `app/Models/GameList.php` | `withPivot('release_year')`; TBA-by-year grouping | Modify |
| `app/Http/Controllers/AdminListController.php` | validate/persist/return `release_year` | Modify |
| `app/Services/GameListSyncService.php` | `insertGame()` carries `release_year` | Modify |
| `app/Services/EventYearlySyncService.php` | target-year resolution uses `release_year` | Modify |
| `resources/js/components/GameFormModal.vue` | "Year" field when TBA | Modify |
| `resources/js/components/GameEditModals.vue` | carry `release_year` in/out | Modify |
| `resources/js/components/AddGameToList.vue` | send `release_year` on add | Modify |
| Tests | persistence, admin, grouping, sync | Create |
| **`app/Console/Commands/SyncEventToYearly.php`** | — | **UNCHANGED** |
| **`resources/views/lists/show.blade.php`** | renders sections generically | **UNCHANGED** |

---

## Task 1: `release_year` pivot column + model

**Files:**
- Create: `database/migrations/<ts>_add_release_year_to_game_list_game_table.php`
- Modify: `app/Models/GameList.php` (`games()` `withPivot`)
- Test: `tests/Feature/ReleaseYearPivotTest.php`

- [ ] **Step 1: Create the failing test**

Run: `php artisan make:test --pest ReleaseYearPivotTest --no-interaction`
Replace with:
```php
<?php

use App\Models\Game;
use App\Models\GameList;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('persists and exposes release_year on the game_list_game pivot', function () {
    $list = GameList::factory()->events()->system()->create(['slug' => 'evt']);
    $game = Game::factory()->create();

    $list->games()->attach($game->id, ['order' => 1, 'is_tba' => true, 'release_year' => 2027]);

    expect((int) $list->games()->where('games.id', $game->id)->first()->pivot->release_year)->toBe(2027);
});
```

- [ ] **Step 2: Run — verify FAIL**

Run: `XDEBUG_MODE=off php artisan test --compact tests/Feature/ReleaseYearPivotTest.php`
Expected: FAIL — unknown column `release_year` (or pivot value null).

- [ ] **Step 3: Create the migration**

Run: `php artisan make:migration add_release_year_to_game_list_game_table --no-interaction`
Replace the generated file with:
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
            $table->unsignedSmallInteger('release_year')->nullable()->after('is_tba');
        });
    }

    public function down(): void
    {
        Schema::table('game_list_game', function (Blueprint $table) {
            $table->dropColumn('release_year');
        });
    }
};
```

- [ ] **Step 4: Add `release_year` to the relationship**

In `app/Models/GameList.php`, the `games()` method `withPivot(...)` currently ends with `'primary_genre_id', 'video_url'`. Add `'release_year'`:
```php
            ->withPivot('order', 'release_date', 'platforms', 'platform_group', 'is_highlight', 'is_tba', 'is_early_access', 'is_indie', 'genre_ids', 'primary_genre_id', 'video_url', 'release_year')
```

- [ ] **Step 5: Migrate + run the test**

Run: `XDEBUG_MODE=off php artisan migrate`
Then: `XDEBUG_MODE=off php artisan test --compact tests/Feature/ReleaseYearPivotTest.php`
Expected: PASS.

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations app/Models/GameList.php tests/Feature/ReleaseYearPivotTest.php
git commit -m "feat(lists): add release_year to game_list_game pivot"
```

---

## Task 2: Admin backend — validate / persist (only when TBA) / return `release_year`

**Files:**
- Modify: `app/Http/Controllers/AdminListController.php` (`addGame`, `updateGamePivotData`, `getGameGenres`)
- Test: `tests/Feature/AdminListReleaseYearTest.php`

- [ ] **Step 1: Create the failing tests**

Run: `php artisan make:test --pest AdminListReleaseYearTest --no-interaction`
Replace with:
```php
<?php

use App\Models\Game;
use App\Models\GameList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function releaseYearListAndGame(): array
{
    $admin = User::factory()->create(['is_admin' => true]);
    $list = GameList::factory()->events()->system()->create([
        'user_id' => $admin->id,
        'slug' => 'nacon-connect-2026',
    ]);
    $game = Game::factory()->create(['igdb_id' => 777111]);

    return [$admin, $list, $game];
}

it('stores release_year when adding a TBA game', function () {
    [$admin, $list, $game] = releaseYearListAndGame();

    $this->actingAs($admin)
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post('/admin/system-lists/events/nacon-connect-2026/games', [
            'game_id' => 777111,
            'is_tba' => true,
            'release_year' => 2027,
        ])
        ->assertJson(['success' => true]);

    expect((int) $list->games()->where('game_id', $game->id)->first()->pivot->release_year)->toBe(2027);
});

it('ignores release_year when the game is not TBA', function () {
    [$admin, $list, $game] = releaseYearListAndGame();

    $this->actingAs($admin)
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post('/admin/system-lists/events/nacon-connect-2026/games', [
            'game_id' => 777111,
            'is_tba' => false,
            'release_year' => 2027,
        ])
        ->assertJson(['success' => true]);

    expect($list->games()->where('game_id', $game->id)->first()->pivot->release_year)->toBeNull();
});

it('updates and clears release_year via the pivot endpoint', function () {
    [$admin, $list, $game] = releaseYearListAndGame();
    $list->games()->attach($game->id, ['order' => 1, 'is_tba' => true, 'release_year' => 2027]);

    // Edit to non-TBA → release_year cleared.
    $this->actingAs($admin)
        ->patchJson('/admin/system-lists/events/nacon-connect-2026/games/'.$game->id.'/pivot', [
            'is_tba' => false,
            'release_date' => '2026-09-01',
        ])
        ->assertJson(['success' => true]);

    expect($list->games()->where('game_id', $game->id)->first()->pivot->release_year)->toBeNull();
});

it('returns release_year from the genres endpoint', function () {
    [$admin, $list, $game] = releaseYearListAndGame();
    $list->games()->attach($game->id, ['order' => 1, 'is_tba' => true, 'release_year' => 2028]);

    $this->actingAs($admin)
        ->getJson('/admin/system-lists/events/nacon-connect-2026/games/'.$game->id.'/genres')
        ->assertJson(['release_year' => 2028]);
});

it('rejects an out-of-range release_year', function () {
    [$admin, $list, $game] = releaseYearListAndGame();

    $this->actingAs($admin)
        ->withHeaders(['Accept' => 'application/json'])
        ->post('/admin/system-lists/events/nacon-connect-2026/games', [
            'game_id' => 777111,
            'is_tba' => true,
            'release_year' => 1850,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['release_year']);
});
```

- [ ] **Step 2: Run — verify FAIL**

Run: `XDEBUG_MODE=off php artisan test --compact tests/Feature/AdminListReleaseYearTest.php`

- [ ] **Step 3: `addGame` — validate + persist (only when TBA)**

In `addGame()`, add to the `$request->validate([...])` array (after the `video_url` line):
```php
            'video_url' => ['nullable', 'string', $this->youtubeUrlRule()],
            'release_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
```
Then in the `$list->games()->attach($game->id, [...])` array (after `'video_url' => ...`):
```php
            'video_url' => $request->input('video_url') ?: null,
            'release_year' => $isTba ? ($request->integer('release_year') ?: null) : null,
```
(`$isTba` is already computed just above the attach call.)

- [ ] **Step 4: `updateGamePivotData` — validate + persist (clear when not TBA)**

Add to its `$request->validate([...])` (after the `video_url` line):
```php
            'video_url' => ['nullable', 'string', $this->youtubeUrlRule()],
            'release_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
```
Then, immediately after the existing `video_url` `$request->has(...)` block and before `$list->games()->updateExistingPivot(...)`, add:
```php
        if ($request->has('video_url')) {
            $pivotUpdate['video_url'] = $request->input('video_url') ?: null;
        }

        $pivotUpdate['release_year'] = $isTba ? ($request->integer('release_year') ?: null) : null;

        $list->games()->updateExistingPivot($game->id, $pivotUpdate);
```
(`$isTba` is computed earlier in the method. Setting it unconditionally means a TBA→non-TBA edit clears the year — desired.)

- [ ] **Step 5: `getGameGenres` — return it**

In the `return response()->json([...])`, after `'video_url' => $pivotData->video_url ?? null,`:
```php
            'video_url' => $pivotData->video_url ?? null,
            'release_year' => $pivotData->release_year ?? null,
```

- [ ] **Step 6: Run — verify PASS (5 cases)**

Run: `XDEBUG_MODE=off php artisan test --compact tests/Feature/AdminListReleaseYearTest.php`

- [ ] **Step 7: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/AdminListController.php tests/Feature/AdminListReleaseYearTest.php
git commit -m "feat(admin): validate/persist/return per-game release_year (TBA only)"
```

---

## Task 3: Event-page grouping — TBA-by-year (events lists only)

**Files:**
- Modify: `app/Models/GameList.php` (`groupGamesByMonth()`)
- Test: `tests/Feature/GroupGamesByMonthTest.php`

- [ ] **Step 1: Create the failing tests**

Run: `php artisan make:test --pest GroupGamesByMonthTest --no-interaction`
Replace with:
```php
<?php

use App\Models\Game;
use App\Models\GameList;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('subdivides the TBA area by release_year on events lists', function () {
    $list = GameList::factory()->events()->system()->create([
        'slug' => 'evt-2026',
        'start_at' => now()->setDate(2026, 6, 2),
        'end_at' => now()->setDate(2026, 6, 2),
    ]);

    $dated = Game::factory()->create(['name' => 'Dated']);
    $y2027 = Game::factory()->create(['name' => 'Future27']);
    $y2028 = Game::factory()->create(['name' => 'Future28']);
    $plain = Game::factory()->create(['name' => 'Unknown']);

    $list->games()->attach($dated->id, ['order' => 1, 'release_date' => now()->setDate(2026, 9, 1)]);
    $list->games()->attach($y2027->id, ['order' => 2, 'is_tba' => true, 'release_year' => 2027]);
    $list->games()->attach($y2028->id, ['order' => 3, 'is_tba' => true, 'release_year' => 2028]);
    $list->games()->attach($plain->id, ['order' => 4, 'is_tba' => true]);

    $grouped = $list->fresh('games')->groupGamesByMonth();
    $keys = array_keys($grouped);

    expect($grouped)->toHaveKey('tba-2027')
        ->and($grouped['tba-2027']['label'])->toBe('2027')
        ->and($grouped)->toHaveKey('tba-2028')
        ->and($grouped)->toHaveKey('tba')
        ->and($grouped)->toHaveKey('2026-09')
        // ordering: generic tba, then year buckets ascending, then months
        ->and(array_search('tba', $keys, true))->toBeLessThan(array_search('tba-2027', $keys, true))
        ->and(array_search('tba-2027', $keys, true))->toBeLessThan(array_search('tba-2028', $keys, true))
        ->and(array_search('tba-2028', $keys, true))->toBeLessThan(array_search('2026-09', $keys, true));
});

it('does NOT subdivide TBA by year on non-events (yearly) lists', function () {
    $list = GameList::factory()->yearly()->system()->create([
        'slug' => 'yr-2027',
        'start_at' => now()->setDate(2027, 1, 1),
        'end_at' => now()->setDate(2027, 12, 31),
    ]);
    $game = Game::factory()->create();
    $list->games()->attach($game->id, ['order' => 1, 'is_tba' => true, 'release_year' => 2027]);

    $grouped = $list->fresh('games')->groupGamesByMonth();

    expect($grouped)->toHaveKey('tba')
        ->and($grouped)->not->toHaveKey('tba-2027');
});
```

- [ ] **Step 2: Run — verify FAIL**

Run: `XDEBUG_MODE=off php artisan test --compact tests/Feature/GroupGamesByMonthTest.php`

- [ ] **Step 3: Update the `is_tba` branch**

In `app/Models/GameList.php` `groupGamesByMonth()`, replace the `is_tba` branch:
```php
            if ($game->pivot->is_tba) {
                if ($filterMonth !== null) {
                    continue;
                }
                $monthKey = 'tba';
                $monthLabel = 'To Be Announced';
                $monthNumber = null;
            } else {
```
with:
```php
            if ($game->pivot->is_tba) {
                if ($filterMonth !== null) {
                    continue;
                }
                $year = $this->isEvents() ? ($game->pivot->release_year ?? null) : null;
                if ($year) {
                    $monthKey = 'tba-'.$year;
                    $monthLabel = (string) $year;
                } else {
                    $monthKey = 'tba';
                    $monthLabel = 'To Be Announced';
                }
                $monthNumber = null;
            } else {
```
(The `else` branch, the out-of-year skip, and `$filterMonth` handling are unchanged.)

- [ ] **Step 4: Update the `uksort` comparator**

Replace the existing `uksort($gamesByMonth, function ($a, $b) {...});` with:
```php
        uksort($gamesByMonth, function ($a, $b) {
            $rank = function (string $key): array {
                if ($key === 'tba') {
                    return [0, 0];
                }
                if (str_starts_with($key, 'tba-')) {
                    return [0, (int) substr($key, 4)];
                }

                return [1, $key];
            };

            return $rank($a) <=> $rank($b);
        });
```
(TBA region first: generic `tba`, then `tba-{year}` ascending; month keys keep their current ascending order.)

- [ ] **Step 5: Run — verify PASS (2 cases)**

Run: `XDEBUG_MODE=off php artisan test --compact tests/Feature/GroupGamesByMonthTest.php`

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/GameList.php tests/Feature/GroupGamesByMonthTest.php
git commit -m "feat(events): subdivide TBA area by release_year on event lists"
```

---

## Task 4: Sync routing by `release_year`

**Files:**
- Modify: `app/Services/GameListSyncService.php` (`insertGame` carries `release_year`)
- Modify: `app/Services/EventYearlySyncService.php` (`plan()` + `apply()` target-year)
- Test: `tests/Feature/SyncEventToYearlyReleaseYearTest.php`

- [ ] **Step 1: Create the failing tests**

Run: `php artisan make:test --pest SyncEventToYearlyReleaseYearTest --no-interaction`
Replace with:
```php
<?php

use App\Models\Game;
use App\Models\GameList;
use App\Models\User;
use App\Services\EventYearlySyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    User::factory()->create(); // user_id = 1 owner for auto-created yearly lists
});

function eventForReleaseYear(): GameList
{
    return GameList::factory()->events()->system()->create([
        'slug' => 'evt-2026',
        'start_at' => now()->setDate(2026, 6, 2),
        'end_at' => now()->setDate(2026, 6, 2),
    ]);
}

it('routes a TBA game tagged with a release_year into that year\'s list', function () {
    $event = eventForReleaseYear();
    $game = Game::factory()->create();
    $event->games()->attach($game->id, ['order' => 1, 'is_tba' => true, 'release_year' => 2027]);
    $event = $event->fresh('games');

    $result = app(EventYearlySyncService::class)->apply($event, [$game->id]);

    $list2027 = GameList::yearly()->whereYear('start_at', 2027)->first();
    expect($list2027)->not->toBeNull()
        ->and($result['inserted'])->toBe(1);

    $pivot = $list2027->games()->where('games.id', $game->id)->first()->pivot;
    expect((bool) $pivot->is_tba)->toBeTrue()
        ->and((int) $pivot->release_year)->toBe(2027);
});

it('routes a plain TBA game (no year) to the event year', function () {
    $event = eventForReleaseYear();
    $game = Game::factory()->create();
    $event->games()->attach($game->id, ['order' => 1, 'is_tba' => true]);
    $event = $event->fresh('games');

    app(EventYearlySyncService::class)->apply($event, [$game->id]);

    expect(GameList::yearly()->whereYear('start_at', 2026)->first()?->games()->where('games.id', $game->id)->exists())->toBeTrue()
        ->and(GameList::yearly()->whereYear('start_at', 2027)->exists())->toBeFalse();
});

it('plan() reports the tagged year as the target', function () {
    $event = eventForReleaseYear();
    $game = Game::factory()->create();
    $event->games()->attach($game->id, ['order' => 1, 'is_tba' => true, 'release_year' => 2028]);
    $event = $event->fresh('games');

    $entry = collect(app(EventYearlySyncService::class)->plan($event))->firstWhere(fn ($p) => $p['game']->id === $game->id);

    expect($entry['target_year'])->toBe(2028)
        ->and($entry['release_label'])->toBe('TBA');
});
```

- [ ] **Step 2: Run — verify FAIL** (games route to the event year 2026, not 2027/2028)

Run: `XDEBUG_MODE=off php artisan test --compact tests/Feature/SyncEventToYearlyReleaseYearTest.php`

- [ ] **Step 3: `insertGame` carries `release_year`**

In `app/Services/GameListSyncService.php`, update the `insertGame` docblock `@param` shape to include `release_year?: int|null`, and add to the `attach(...)` array (after `'video_url' => ...`):
```php
            'video_url' => $attrs['video_url'] ?? null,
            'release_year' => $attrs['release_year'] ?? null,
```

- [ ] **Step 4: `EventYearlySyncService::plan()` target year**

Replace, in `plan()`:
```php
            $targetYear = ($isTba || ! $date) ? $eventYear : $date->year;
```
with:
```php
            $year = $pivot->release_year;
            $targetYear = match (true) {
                ! $isTba && $date !== null => $date->year,
                $year !== null => (int) $year,
                default => $eventYear,
            };
```

- [ ] **Step 5: `EventYearlySyncService::apply()` target year + carry `release_year`**

In `apply()`, replace:
```php
                    $targetYear = ($isTba || ! $date) ? $eventYear : $date->year;
```
with:
```php
                    $year = $pivot->release_year;
                    $targetYear = match (true) {
                        ! $isTba && $date !== null => $date->year,
                        $year !== null => (int) $year,
                        default => $eventYear,
                    };
```
Then in the `insertGame(...)` call inside `apply()`, add `release_year` to the attrs (after `'video_url' => $videoUrl,`):
```php
                            'video_url' => $videoUrl,
                            'release_year' => $pivot->release_year ?? null,
```

- [ ] **Step 6: Run — verify PASS (3 cases) + the existing sync suite still green**

Run: `XDEBUG_MODE=off php artisan test --compact tests/Feature/SyncEventToYearlyReleaseYearTest.php tests/Feature/SyncEventToYearlyCommandTest.php`

- [ ] **Step 7: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/GameListSyncService.php app/Services/EventYearlySyncService.php tests/Feature/SyncEventToYearlyReleaseYearTest.php
git commit -m "feat(lists): route TBA games to their tagged release_year on sync"
```

---

## Task 5: Admin Vue modal — "Year" field when TBA

No JS test runner; verified by build + the Task 2 backend contract. Make all three edits, build, manual-verify.

**Files:** `resources/js/components/GameFormModal.vue`, `GameEditModals.vue`, `AddGameToList.vue`

- [ ] **Step 1: `GameFormModal.vue` — prop**

In `defineProps({...})`, after the `initialVideoUrl` prop, add:
```js
  initialVideoUrl: {
    type: String,
    default: ''
  },
  initialReleaseYear: {
    type: [Number, String],
    default: ''
  }
});
```

- [ ] **Step 2: `GameFormModal.vue` — formData**

Update the `formData` ref to add `releaseYear`:
```js
const formData = ref({
  releaseDate: '',
  platforms: [],
  primaryGenreId: '',
  secondaryGenreIds: [],
  isTba: false,
  isEarlyAccess: false,
  videoUrl: '',
  releaseYear: ''
});
```

- [ ] **Step 3: `GameFormModal.vue` — the Year field (shown when TBA)**

In the template, immediately after the TBA `<label>…TBA (To Be Announced)…</label>` block (the one containing `id="game-form-tba"`), insert:
```html
              <div v-if="formData.isTba" class="pl-6">
                <label class="block text-sm font-medium text-gray-300 mb-2">
                  Year (optional)
                </label>
                <input
                  v-model.number="formData.releaseYear"
                  type="number"
                  min="2000"
                  max="2100"
                  placeholder="2027"
                  class="w-40 px-4 py-2 rounded-lg bg-gray-700 text-white border border-gray-600 focus:outline-none focus:ring-2"
                  :class="modeStyles.focusRing"
                >
                <p class="mt-1 text-xs text-gray-400">
                  Groups this TBA game under a year section on event lists (e.g. 2027).
                </p>
              </div>
```

- [ ] **Step 4: `GameFormModal.vue` — resetForm, toggles, submit, watcher**

In `resetForm()`'s `formData.value = {...}`, add `releaseYear`:
```js
    isEarlyAccess: props.initialIsEarlyAccess,
    videoUrl: props.initialVideoUrl || '',
    releaseYear: props.initialReleaseYear || ''
  };
```
Update `onTbaToggle` to clear the year when TBA is switched off:
```js
const onTbaToggle = () => {
  if (formData.value.isTba) {
    formData.value.isEarlyAccess = false;
  } else {
    formData.value.releaseYear = '';
  }
};
```
Update `onEarlyAccessToggle` (which turns TBA off) to also clear it — add `formData.value.releaseYear = '';` inside its `if (formData.value.isEarlyAccess) {` block:
```js
const onEarlyAccessToggle = () => {
  if (formData.value.isEarlyAccess) {
    formData.value.isTba = false;
    formData.value.releaseYear = '';
    if (!formData.value.releaseDate && props.suggestedEarlyAccessDate) {
      formData.value.releaseDate = props.suggestedEarlyAccessDate;
    }
  }
};
```
In `handleSubmit()`'s `emit('submit', {...})`, add `releaseYear` (only meaningful when TBA):
```js
    videoUrl: formData.value.videoUrl || null,
    releaseYear: formData.value.isTba ? (formData.value.releaseYear || null) : null
  });
```
After the `initialVideoUrl` watcher, add:
```js
watch(() => props.initialReleaseYear, (val) => {
  if (props.show) {
    formData.value.releaseYear = val || '';
  }
});
```

- [ ] **Step 5: `GameEditModals.vue` — carry in/out**

- Add a ref after `initialVideoUrl`: `const initialReleaseYear = ref('');`
- In `openModal()`, after `initialVideoUrl.value = data.video_url || '';`: `initialReleaseYear.value = data.release_year || '';`
- In `closeModal()`, after `initialVideoUrl.value = '';`: `initialReleaseYear.value = '';`
- Pass the prop on `<GameFormModal>` after `:initial-video-url="initialVideoUrl"`: `:initial-release-year="initialReleaseYear"`
- In `performEdit()` body, after `video_url: formData.videoUrl || null,`: `release_year: formData.releaseYear || null,`

- [ ] **Step 6: `AddGameToList.vue` — send on add**

In `handleFormSubmit()`, after the `video_url` append block, add:
```js
    if (formData.videoUrl) {
      submitData.append('video_url', formData.videoUrl);
    }

    if (formData.releaseYear) {
      submitData.append('release_year', formData.releaseYear);
    }
```

- [ ] **Step 7: Build**

Run: `nvm use 24 && npm run build`
Expected: Vite build succeeds.

- [ ] **Step 8: Manual verification**

1. Admin → an events list edit page. Add/edit a game, check **TBA** → a **"Year (optional)"** field appears. Enter `2027`, save.
2. Reopen the edit modal → the year is pre-filled. Uncheck TBA → field hides; save → year cleared.
3. Open the public event list → a **"2027"** section shows the game.
4. Run `php artisan events:sync-to-yearly <event-slug> --all` → the game lands in the 2027 yearly list (TBA bucket).

- [ ] **Step 9: Commit**

```bash
git add resources/js/components/GameFormModal.vue resources/js/components/GameEditModals.vue resources/js/components/AddGameToList.vue
git commit -m "feat(admin): Year field for TBA games in the list-item modal"
```

---

## Task 6: Finalize

- [ ] **Step 1: Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 2: Run all new + affected tests**

```bash
XDEBUG_MODE=off php artisan test --compact \
  tests/Feature/ReleaseYearPivotTest.php \
  tests/Feature/AdminListReleaseYearTest.php \
  tests/Feature/GroupGamesByMonthTest.php \
  tests/Feature/SyncEventToYearlyReleaseYearTest.php \
  tests/Feature/SyncEventToYearlyCommandTest.php \
  tests/Feature/AdminListVideoUrlTest.php
```
Expected: all PASS.

- [ ] **Step 3: Offer the full suite**

Ask the user whether to run the entire suite (`XDEBUG_MODE=off php artisan test --compact`) — it touches the shared `GameList::groupGamesByMonth()` (used by event, yearly, and releases pages) and `AdminListController`, so a full run is prudent.

---

## Self-review

- **Spec coverage:** `release_year` column (T1) ✓; admin validate/persist-only-when-TBA/return (T2) ✓; events-only TBA-by-year grouping + ordering (T3) ✓; sync routing by `release_year` + `insertGame` carry (T4) ✓; Year-field-when-TBA modal wiring (T5) ✓; command file & `lists/show.blade.php` untouched ✓; yearly/seasoned displays unaffected (gated `isEvents()`, T3 second test) ✓; TDD failing-first throughout ✓.
- **Type/name consistency:** wire key `release_year` everywhere over HTTP/DB; Vue prop `initial-release-year`↔`initialReleaseYear`, `formData.releaseYear`. `groupGamesByMonth` keys `tba` / `tba-{year}` / `Y-m` consistent between the branch and the `uksort` rank function. `insertGame` `release_year` attr matches `apply()`'s call and the pivot column.
- **No placeholders:** every step has full code; commands carry `XDEBUG_MODE=off` / `nvm use 24`; commits omit `Co-authored-by`.
