# Game Genres Rework Specification

## Overview

A complete rework of the game genre system to support multi-genre tagging across all system lists (monthly/highlights/indies). Key features:

- **Hybrid genre management**: IGDB genres seed the database, admins can add/edit/merge/hide custom genres
- **Multi-genre support**: Games can have up to 3 genres with a designated primary genre
- **Unified modal**: Reusable modal component for genre selection across all list contexts
- **Tom Select integration**: Searchable inline tag input for genre selection
- **Dynamic frontend**: Sidebar navigation for genre tabs with mobile-responsive horizontal tabs
- **Genre admin page**: Full CRUD, bulk operations, merge functionality, usage statistics

---

## Database Schema

### Migration: `modify_genres_table_for_rework`

Modifies the existing `genres` table:

```php
Schema::table('genres', function (Blueprint $table) {
    $table->string('slug')->unique()->after('name');
    $table->boolean('is_system')->default(false)->after('slug'); // Protected genres like "Other"
    $table->boolean('is_visible')->default(true)->after('is_system');
    $table->boolean('is_pending_review')->default(false)->after('is_visible'); // IGDB sync queue
    $table->unsignedInteger('sort_order')->default(0)->after('is_pending_review');
    $table->timestamps();

    $table->index('is_visible');
    $table->index('is_pending_review');
    $table->index('sort_order');
});
```

### Migration: `modify_game_list_game_for_multi_genres`

Modifies the existing `game_list_game` pivot table:

```php
Schema::table('game_list_game', function (Blueprint $table) {
    // Replace single indie_genre string with JSON array
    $table->dropColumn('indie_genre');
    $table->json('genre_ids')->nullable();
    $table->unsignedBigInteger('primary_genre_id')->nullable();

    $table->foreign('primary_genre_id')->references('id')->on('genres')->nullOnDelete();
});
```

### Updated Schema Overview

| Table | Column | Type | Description |
|-------|--------|------|-------------|
| `genres` | `id` | bigint | Primary key |
| `genres` | `igdb_id` | int | IGDB genre ID (nullable for custom genres) |
| `genres` | `name` | string | Display name |
| `genres` | `slug` | string | URL-friendly unique identifier |
| `genres` | `is_system` | boolean | Protected genre (cannot delete/hide) |
| `genres` | `is_visible` | boolean | Visible in selection and frontend |
| `genres` | `is_pending_review` | boolean | Queued from IGDB sync for admin approval |
| `genres` | `sort_order` | int | Admin-defined display order |
| `game_list_game` | `genre_ids` | json | Array of genre IDs (max 3) |
| `game_list_game` | `primary_genre_id` | foreignId | Primary genre for tab placement |

---

## Protected System Genre: "Other"

A special protected genre for uncategorized games:

```php
// Created via seeder or migration
Genre::create([
    'name' => 'Other',
    'slug' => 'other',
    'is_system' => true,
    'is_visible' => true,
    'sort_order' => 999999, // Always last
]);
```

**Behavior:**
- Cannot be deleted or hidden (enforced in model/controller)
- Always appears last in sidebar regardless of sort_order
- Games without assigned genres display here
- Admin can rename but not delete

---

## Model Updates

### Genre Model

```php
// app/Models/Genre.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Genre extends Model
{
    protected $fillable = [
        'igdb_id',
        'name',
        'slug',
        'is_system',
        'is_visible',
        'is_pending_review',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_visible' => 'boolean',
            'is_pending_review' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function games(): BelongsToMany
    {
        return $this->belongsToMany(Game::class, 'game_genre');
    }

    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }

    public function scopePendingReview($query)
    {
        return $query->where('is_pending_review', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function scopeNotOther($query)
    {
        return $query->where('slug', '!=', 'other');
    }

    public function isProtected(): bool
    {
        return $this->is_system;
    }

    public function canBeDeleted(): bool
    {
        if ($this->isProtected()) {
            return false;
        }

        return $this->getUsageCount() === 0;
    }

    public function getUsageCount(): int
    {
        // Count usage in game_list_game pivot (as primary or in genre_ids)
        return DB::table('game_list_game')
            ->where('primary_genre_id', $this->id)
            ->orWhereJsonContains('genre_ids', $this->id)
            ->count();
    }

    protected static function booted(): void
    {
        static::creating(function (Genre $genre) {
            if (empty($genre->slug)) {
                $genre->slug = str()->slug($genre->name);
            }
        });

        static::deleting(function (Genre $genre) {
            if ($genre->isProtected()) {
                throw new \RuntimeException('Cannot delete protected system genre.');
            }
            if ($genre->getUsageCount() > 0) {
                throw new \RuntimeException('Cannot delete genre that is in use.');
            }
        });
    }
}
```

### GameList Model Updates

```php
// Add to app/Models/GameList.php - games() relationship

public function games(): BelongsToMany
{
    return $this->belongsToMany(Game::class, 'game_list_game')
        ->withPivot([
            'order',
            'release_date',
            'platforms',
            'platform_group',
            'is_highlight',
            'is_tba',
            'is_indie',
            'genre_ids',
            'primary_genre_id',
        ])
        ->withTimestamps();
}
```

---

## Routes

### Admin Genre Management Routes

```php
// routes/web.php - inside admin middleware group

// Genre Management
Route::get('/genres', [AdminGenreController::class, 'index'])->name('genres.index');
Route::post('/genres', [AdminGenreController::class, 'store'])->name('genres.store');
Route::patch('/genres/{genre}', [AdminGenreController::class, 'update'])->name('genres.update');
Route::delete('/genres/{genre}', [AdminGenreController::class, 'destroy'])->name('genres.destroy');
Route::patch('/genres/{genre}/approve', [AdminGenreController::class, 'approve'])->name('genres.approve');
Route::delete('/genres/{genre}/reject', [AdminGenreController::class, 'reject'])->name('genres.reject');
Route::patch('/genres/reorder', [AdminGenreController::class, 'reorder'])->name('genres.reorder');
Route::post('/genres/merge', [AdminGenreController::class, 'merge'])->name('genres.merge');
Route::post('/genres/bulk-remove', [AdminGenreController::class, 'bulkRemove'])->name('genres.bulk-remove');
Route::post('/genres/bulk-replace', [AdminGenreController::class, 'bulkReplace'])->name('genres.bulk-replace');
Route::post('/genres/{genre}/assign-games', [AdminGenreController::class, 'assignGames'])->name('genres.assign-games');

// Internal API for genre search (Tom Select)
Route::get('/api/genres/search', [AdminGenreController::class, 'search'])->name('api.genres.search');
```

### Modified System List Routes

```php
// Existing route modification - always show modal when adding game
Route::post('/admin/system-lists/{type}/{slug}/games', [AdminListController::class, 'addGame']);
```

---

## Controllers

### AdminGenreController

```php
// app/Http/Controllers/AdminGenreController.php
namespace App\Http\Controllers;

use App\Models\Genre;
use App\Models\GameList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminGenreController extends Controller
{
    public function index()
    {
        $genres = Genre::query()
            ->withCount([
                'games as total_game_count',
            ])
            ->ordered()
            ->get()
            ->map(function ($genre) {
                $genre->indie_list_count = $this->getListTypeUsageCount($genre, 'indie');
                $genre->monthly_list_count = $this->getListTypeUsageCount($genre, 'monthly');
                return $genre;
            });

        $pendingGenres = Genre::pendingReview()->get();

        return view('admin.genres.index', compact('genres', 'pendingGenres'));
    }

    public function store(StoreGenreRequest $request)
    {
        Genre::create($request->validated());

        return back()->with('success', 'Genre created successfully.');
    }

    public function update(UpdateGenreRequest $request, Genre $genre)
    {
        $genre->update($request->validated());

        return back()->with('success', 'Genre updated successfully.');
    }

    public function destroy(Genre $genre)
    {
        if (!$genre->canBeDeleted()) {
            return back()->with('error', 'Cannot delete this genre.');
        }

        $genre->delete();

        return back()->with('success', 'Genre deleted successfully.');
    }

    public function approve(Genre $genre)
    {
        $genre->update(['is_pending_review' => false, 'is_visible' => true]);

        return back()->with('success', 'Genre approved.');
    }

    public function reject(Genre $genre)
    {
        if ($genre->is_pending_review) {
            $genre->delete();
        }

        return back()->with('success', 'Genre rejected and removed.');
    }

    public function reorder(Request $request)
    {
        $order = $request->validate(['order' => 'required|array'])['order'];

        foreach ($order as $index => $genreId) {
            Genre::where('id', $genreId)->update(['sort_order' => $index]);
        }

        return response()->json(['success' => true]);
    }

    public function merge(Request $request)
    {
        $data = $request->validate([
            'source_genre_id' => 'required|exists:genres,id',
            'target_genre_id' => 'required|exists:genres,id|different:source_genre_id',
        ]);

        $source = Genre::findOrFail($data['source_genre_id']);
        $target = Genre::findOrFail($data['target_genre_id']);

        if ($source->isProtected()) {
            return back()->with('error', 'Cannot merge a protected genre.');
        }

        DB::transaction(function () use ($source, $target) {
            // Update all pivot records: replace source with target in genre_ids
            DB::table('game_list_game')
                ->whereJsonContains('genre_ids', $source->id)
                ->get()
                ->each(function ($row) use ($source, $target) {
                    $genreIds = json_decode($row->genre_ids, true) ?? [];
                    $genreIds = array_map(fn($id) => $id === $source->id ? $target->id : $id, $genreIds);
                    $genreIds = array_unique($genreIds);

                    DB::table('game_list_game')
                        ->where('id', $row->id)
                        ->update(['genre_ids' => json_encode(array_values($genreIds))]);
                });

            // Update primary_genre_id references
            DB::table('game_list_game')
                ->where('primary_genre_id', $source->id)
                ->update(['primary_genre_id' => $target->id]);

            // Delete source genre
            $source->delete();
        });

        return back()->with('success', "Genre '{$source->name}' merged into '{$target->name}'.");
    }

    public function bulkRemove(Request $request)
    {
        $data = $request->validate([
            'genre_id' => 'required|exists:genres,id',
            'list_id' => 'required|exists:game_lists,id',
        ]);

        $genre = Genre::findOrFail($data['genre_id']);
        $list = GameList::findOrFail($data['list_id']);

        DB::table('game_list_game')
            ->where('game_list_id', $list->id)
            ->whereJsonContains('genre_ids', $genre->id)
            ->get()
            ->each(function ($row) use ($genre) {
                $genreIds = json_decode($row->genre_ids, true) ?? [];
                $genreIds = array_filter($genreIds, fn($id) => $id !== $genre->id);

                $primaryGenreId = $row->primary_genre_id === $genre->id ? null : $row->primary_genre_id;

                DB::table('game_list_game')
                    ->where('id', $row->id)
                    ->update([
                        'genre_ids' => json_encode(array_values($genreIds)),
                        'primary_genre_id' => $primaryGenreId,
                    ]);
            });

        return back()->with('success', "Genre removed from all games in '{$list->name}'.");
    }

    public function bulkReplace(Request $request)
    {
        $data = $request->validate([
            'source_genre_id' => 'required|exists:genres,id',
            'target_genre_id' => 'required|exists:genres,id',
            'list_id' => 'required|exists:game_lists,id',
        ]);

        // Similar to merge but scoped to a specific list
        // Implementation follows merge pattern but with list_id filter

        return back()->with('success', 'Genres replaced successfully.');
    }

    public function assignGames(Request $request, Genre $genre)
    {
        $data = $request->validate([
            'game_ids' => 'required|array',
            'game_ids.*' => 'exists:games,id',
            'list_id' => 'required|exists:game_lists,id',
        ]);

        foreach ($data['game_ids'] as $gameId) {
            $pivot = DB::table('game_list_game')
                ->where('game_list_id', $data['list_id'])
                ->where('game_id', $gameId)
                ->first();

            if ($pivot) {
                $genreIds = json_decode($pivot->genre_ids, true) ?? [];
                if (!in_array($genre->id, $genreIds) && count($genreIds) < 3) {
                    $genreIds[] = $genre->id;
                    DB::table('game_list_game')
                        ->where('id', $pivot->id)
                        ->update(['genre_ids' => json_encode($genreIds)]);
                }
            }
        }

        return back()->with('success', 'Games assigned to genre.');
    }

    public function search(Request $request)
    {
        $query = $request->get('q', '');

        $genres = Genre::visible()
            ->where('is_pending_review', false)
            ->where('name', 'like', "%{$query}%")
            ->ordered()
            ->limit(20)
            ->get(['id', 'name', 'slug']);

        return response()->json($genres);
    }

    private function getListTypeUsageCount(Genre $genre, string $listType): int
    {
        return DB::table('game_list_game')
            ->join('game_lists', 'game_list_game.game_list_id', '=', 'game_lists.id')
            ->where('game_lists.type', $listType)
            ->where(function ($q) use ($genre) {
                $q->where('primary_genre_id', $genre->id)
                    ->orWhereJsonContains('genre_ids', $genre->id);
            })
            ->count();
    }
}
```

### AdminListController Updates

Key changes to `addGame()` method to support modal flow:

```php
// Modify addGame() to accept genre data
public function addGame(Request $request, string $type, string $slug)
{
    $validated = $request->validate([
        'game_id' => 'required|integer',
        'release_date' => 'nullable|date',
        'platforms' => 'nullable|array',
        'platform_group' => 'nullable|string',
        'is_tba' => 'boolean',
        'genre_ids' => 'nullable|array|max:3',
        'genre_ids.*' => 'exists:genres,id',
        'primary_genre_id' => 'nullable|exists:genres,id',
    ]);

    // ... existing logic ...

    $pivotData = [
        'order' => $maxOrder + 1,
        'release_date' => $validated['release_date'] ?? $game->first_release_date,
        'platforms' => json_encode($platforms),
        'platform_group' => $platformGroup,
        'is_tba' => $validated['is_tba'] ?? false,
        'genre_ids' => json_encode($validated['genre_ids'] ?? []),
        'primary_genre_id' => $validated['primary_genre_id'] ?? null,
    ];

    $list->games()->attach($game->id, $pivotData);

    // ... rest of method ...
}
```

---

## Unified Modal Component

### Blade Component: `add-game-modal`

```php
// resources/views/components/admin/system-lists/add-game-modal.blade.php

@props([
    'listType' => null,      // monthly, indie, highlights, seasoned
    'game' => null,          // Game model if editing existing
    'pivotData' => null,     // Existing pivot data if editing
    'showPlatforms' => true, // Show platform selection
    'showGenres' => true,    // Show genre selection
    'requireGenres' => false,// Require at least primary genre
])

<div
    x-data="addGameModal({
        listType: '{{ $listType }}',
        gameId: {{ $game?->id ?? 'null' }},
        existingGenreIds: {{ json_encode($pivotData['genre_ids'] ?? []) }},
        existingPrimaryGenreId: {{ $pivotData['primary_genre_id'] ?? 'null' }},
        gameIgdbGenres: {{ json_encode($game?->genres->pluck('id')->toArray() ?? []) }},
        requireGenres: {{ $requireGenres ? 'true' : 'false' }},
    })"
    x-show="isOpen"
    x-cloak
    class="fixed inset-0 z-50 overflow-y-auto"
    @keydown.escape.window="close()"
>
    <!-- Modal backdrop -->
    <div class="fixed inset-0 bg-black/50" @click="close()"></div>

    <!-- Modal content -->
    <div class="relative min-h-screen flex items-center justify-center p-4">
        <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-lg w-full p-6">
            <h3 class="text-lg font-semibold mb-4" x-text="title"></h3>

            <form @submit.prevent="submit()">
                <!-- Primary Genre (separate single-select) -->
                @if($showGenres)
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">
                        Primary Genre <span class="text-red-500" x-show="requireGenres">*</span>
                    </label>
                    <select
                        x-model="primaryGenreId"
                        class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700"
                        :required="requireGenres"
                    >
                        <option value="">Select primary genre...</option>
                        <template x-for="genre in availableGenres" :key="genre.id">
                            <option :value="genre.id" x-text="genre.name"></option>
                        </template>
                    </select>
                </div>

                <!-- Secondary Genres (Tom Select multi-input) -->
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">
                        Additional Genres (max 2)
                    </label>
                    <div x-ref="genreInput"></div>
                    <p class="text-xs text-gray-500 mt-1">
                        Type to search. Selected: <span x-text="selectedGenreIds.length"></span>/2
                    </p>
                </div>
                @endif

                <!-- Release Date -->
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">Release Date</label>
                    <input
                        type="date"
                        x-model="releaseDate"
                        :disabled="isTba"
                        class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700"
                    >
                    <label class="flex items-center mt-2 text-sm">
                        <input type="checkbox" x-model="isTba" class="mr-2">
                        TBA (To Be Announced)
                    </label>
                </div>

                <!-- Platform Selection (for highlights) -->
                @if($showPlatforms && $listType === 'highlights')
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">Platform Group</label>
                    <select
                        x-model="platformGroup"
                        class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700"
                    >
                        @foreach(\App\Enums\PlatformGroupEnum::cases() as $group)
                            <option value="{{ $group->value }}">{{ $group->label() }}</option>
                        @endforeach
                    </select>
                </div>
                @endif

                <!-- Actions -->
                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" @click="close()" class="px-4 py-2 text-gray-600 hover:text-gray-800">
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700"
                        :disabled="isSubmitting || (requireGenres && !primaryGenreId)"
                    >
                        <span x-show="!isSubmitting">Save</span>
                        <span x-show="isSubmitting">Saving...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
```

### Alpine.js Component

```javascript
// resources/js/components/add-game-modal.js

import TomSelect from 'tom-select';

export default function addGameModal(config) {
    return {
        isOpen: false,
        isSubmitting: false,
        title: 'Add Game',

        // Form data
        primaryGenreId: config.existingPrimaryGenreId,
        selectedGenreIds: config.existingGenreIds || [],
        releaseDate: null,
        isTba: false,
        platformGroup: null,

        // Config
        listType: config.listType,
        gameId: config.gameId,
        requireGenres: config.requireGenres,
        gameIgdbGenres: config.gameIgdbGenres,

        // Tom Select instance
        tomSelect: null,
        availableGenres: [],

        async init() {
            // Fetch available genres
            const response = await fetch('/admin/api/genres/search');
            this.availableGenres = await response.json();

            // Pre-populate with game's IGDB genres if adding new
            if (!config.existingGenreIds?.length && config.gameIgdbGenres?.length) {
                this.selectedGenreIds = config.gameIgdbGenres.slice(0, 2);
                if (config.gameIgdbGenres[0]) {
                    this.primaryGenreId = config.gameIgdbGenres[0];
                }
            }

            this.$nextTick(() => this.initTomSelect());
        },

        initTomSelect() {
            if (this.tomSelect) this.tomSelect.destroy();

            this.tomSelect = new TomSelect(this.$refs.genreInput, {
                valueField: 'id',
                labelField: 'name',
                searchField: 'name',
                maxItems: 2,
                options: this.availableGenres,
                items: this.selectedGenreIds,
                create: false, // Select only, no inline create
                load: async (query, callback) => {
                    const response = await fetch(`/admin/api/genres/search?q=${encodeURIComponent(query)}`);
                    callback(await response.json());
                },
                onChange: (values) => {
                    this.selectedGenreIds = values.map(v => parseInt(v));
                },
            });
        },

        open(options = {}) {
            this.title = options.title || 'Add Game';
            this.gameId = options.gameId || this.gameId;
            this.releaseDate = options.releaseDate || null;
            this.isTba = options.isTba || false;
            this.platformGroup = options.platformGroup || null;
            this.isOpen = true;
        },

        close() {
            this.isOpen = false;
        },

        async submit() {
            this.isSubmitting = true;

            const allGenreIds = [
                this.primaryGenreId,
                ...this.selectedGenreIds.filter(id => id !== this.primaryGenreId)
            ].filter(Boolean).slice(0, 3);

            const data = {
                game_id: this.gameId,
                genre_ids: allGenreIds,
                primary_genre_id: this.primaryGenreId,
                release_date: this.isTba ? null : this.releaseDate,
                is_tba: this.isTba,
                platform_group: this.platformGroup,
            };

            try {
                // Emit event or call endpoint based on context
                this.$dispatch('game-modal-submit', data);
                this.close();
            } finally {
                this.isSubmitting = false;
            }
        },
    };
}
```

---

## Frontend: Indie Games Page Rework

### Sidebar Navigation (Desktop)

```blade
{{-- resources/views/indie-games/index.blade.php --}}

<div class="flex flex-col lg:flex-row gap-6">
    {{-- Genre Sidebar (Desktop) --}}
    <aside class="hidden lg:block w-64 flex-shrink-0">
        <nav class="sticky top-4 space-y-1">
            <h3 class="font-semibold text-sm text-gray-500 uppercase tracking-wider mb-3">Genres</h3>

            @foreach($genres as $genre)
                <a
                    href="{{ route('indie-games.index', ['year' => $year, 'genre' => $genre->slug]) }}"
                    class="block px-3 py-2 rounded-lg transition-colors
                        {{ $currentGenre === $genre->slug
                            ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-200'
                            : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800' }}"
                >
                    {{ $genre->name }}
                    <span class="text-xs text-gray-500">({{ $genre->game_count }})</span>
                </a>
            @endforeach

            {{-- Other always last --}}
            <a
                href="{{ route('indie-games.index', ['year' => $year, 'genre' => 'other']) }}"
                class="block px-3 py-2 rounded-lg transition-colors
                    {{ $currentGenre === 'other'
                        ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-200'
                        : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800' }}"
            >
                Other
                <span class="text-xs text-gray-500">({{ $otherCount }})</span>
            </a>
        </nav>
    </aside>

    {{-- Mobile: Horizontal Scrollable Tabs --}}
    <div class="lg:hidden overflow-x-auto -mx-4 px-4 pb-2">
        <div class="flex gap-2 min-w-max">
            @foreach($genres as $genre)
                <a
                    href="{{ route('indie-games.index', ['year' => $year, 'genre' => $genre->slug]) }}"
                    class="px-4 py-2 rounded-full text-sm whitespace-nowrap
                        {{ $currentGenre === $genre->slug
                            ? 'bg-indigo-600 text-white'
                            : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300' }}"
                >
                    {{ $genre->name }}
                </a>
            @endforeach
            <a
                href="{{ route('indie-games.index', ['year' => $year, 'genre' => 'other']) }}"
                class="px-4 py-2 rounded-full text-sm whitespace-nowrap
                    {{ $currentGenre === 'other'
                        ? 'bg-indigo-600 text-white'
                        : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300' }}"
            >
                Other
            </a>
        </div>
    </div>

    {{-- Game Grid --}}
    <main class="flex-1">
        {{-- Games grouped by month --}}
        @foreach($gamesByMonth as $month => $games)
            <section class="mb-8">
                <h2 class="text-xl font-bold mb-4">{{ $month }}</h2>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    @foreach($games as $game)
                        <x-game-card :game="$game" variant="simple" />
                    @endforeach
                </div>
            </section>
        @endforeach
    </main>
</div>
```

### Controller Updates

```php
// app/Http/Controllers/IndieGamesController.php

public function index(Request $request)
{
    $year = $request->get('year', now()->year);
    $genreSlug = $request->get('genre');

    // Get visible genres ordered by admin sort_order, "Other" always last
    $genres = Genre::visible()
        ->where('is_pending_review', false)
        ->notOther()
        ->ordered()
        ->withCount(['games as game_count' => function ($q) use ($year) {
            // Count games in this year's indie list with this genre as primary
            $q->whereHas('gameLists', function ($q2) use ($year) {
                $q2->where('type', 'indie')
                   ->whereYear('game_lists.start_date', $year);
            });
        }])
        ->get();

    // Get games for selected genre
    $query = $this->getIndieListForYear($year)->games();

    if ($genreSlug && $genreSlug !== 'other') {
        $genre = Genre::where('slug', $genreSlug)->first();
        if ($genre) {
            $query->wherePivot('primary_genre_id', $genre->id);
        }
    } elseif ($genreSlug === 'other') {
        $query->wherePivotNull('primary_genre_id');
    }

    $games = $query->orderByPivot('release_date')->get();
    $gamesByMonth = $this->groupGamesByMonth($games);

    // Count for "Other"
    $otherCount = $this->getIndieListForYear($year)
        ->games()
        ->wherePivotNull('primary_genre_id')
        ->count();

    return view('indie-games.index', compact(
        'genres',
        'games',
        'gamesByMonth',
        'year',
        'genreSlug',
        'otherCount'
    ))->with('currentGenre', $genreSlug);
}
```

---

## NPM Dependencies

```bash
npm install tom-select
```

Add to `resources/js/app.js`:
```javascript
import TomSelect from 'tom-select';
window.TomSelect = TomSelect;

// Import CSS
import 'tom-select/dist/css/tom-select.css';
```

---

## UI Mockups

### Genre Admin Page

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  Genre Management                                            [+ Add Genre]   │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  ┌─────────────────────────────────────────────────────────────────────────┐│
│  │ PENDING REVIEW (from IGDB sync)                                 3 items ││
│  ├─────────────────────────────────────────────────────────────────────────┤│
│  │ Souls-like            [✓ Approve] [✗ Reject]                           ││
│  │ Boomer Shooter        [✓ Approve] [✗ Reject]                           ││
│  │ Extraction Shooter    [✓ Approve] [✗ Reject]                           ││
│  └─────────────────────────────────────────────────────────────────────────┘│
│                                                                              │
│  ═══════════════════════════════════════════════════════════════════════════│
│  ACTIVE GENRES                                         [Bulk Actions ▼]     │
│  ═══════════════════════════════════════════════════════════════════════════│
│                                                                              │
│  │ ⋮⋮ │ Name           │ Indie Uses │ Monthly Uses │ Total │ Actions      │ │
│  ├────┼────────────────┼────────────┼──────────────┼───────┼──────────────┤ │
│  │ ⋮⋮ │ Metroidvania   │    45      │     12       │   57  │ [Edit] [🔗]  │ │
│  │ ⋮⋮ │ Roguelike      │    38      │     8        │   46  │ [Edit] [🔗]  │ │
│  │ ⋮⋮ │ Platformer     │    32      │     15       │   47  │ [Edit] [🔗]  │ │
│  │ ⋮⋮ │ Action         │    28      │     22       │   50  │ [Edit] [🔗]  │ │
│  │ ⋮⋮ │ RPG            │    25      │     18       │   43  │ [Edit] [🔗]  │ │
│  │ ...                                                                      │ │
│  │ ── │ Other (system) │    12      │     0        │   12  │ [Edit]       │ │
│  └──────────────────────────────────────────────────────────────────────────┘│
│                                                                              │
│  Drag to reorder. "Other" is always last.                                   │
│                                                                              │
│  ┌─────────────────────────────────────────────────────────────────────────┐│
│  │ BULK ACTIONS                                                            ││
│  ├─────────────────────────────────────────────────────────────────────────┤│
│  │                                                                         ││
│  │ [Merge Genres]     Source: [Select...▼]  →  Target: [Select...▼]       ││
│  │                                                                         ││
│  │ [Remove from List] Genre: [Select...▼]   List: [Select...▼] [Execute]  ││
│  │                                                                         ││
│  │ [Replace in List]  Replace [Genre A▼] with [Genre B▼] in [List▼]       ││
│  │                                                                         ││
│  └─────────────────────────────────────────────────────────────────────────┘│
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Add Game Modal (from search)

```
┌─────────────────────────────────────────────────────────────────┐
│  Add Game to List                                         [X]   │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  Primary Genre *                                                 │
│  [Metroidvania                                            ▼]    │
│                                                                  │
│  Additional Genres (max 2)                                       │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │ [Platformer ×] [Action ×]  |  Type to search...            ││
│  └─────────────────────────────────────────────────────────────┘│
│  Selected: 2/2                                                  │
│                                                                  │
│  ─────────────────────────────────────────────────────────────  │
│                                                                  │
│  Release Date                                                    │
│  [2026-03-15    ]                                               │
│  [ ] TBA (To Be Announced)                                      │
│                                                                  │
│  Platform Group (for highlights only)                           │
│  [Multiplatform                                           ▼]    │
│                                                                  │
│                                     [Cancel]  [Save]            │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### Frontend Indie Page (Desktop)

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  [Header]                                                                    │
├─────────────────────────────────────────────────────────────────────────────┤
│  [Releases Nav: News | Highlights | Monthly | Indie | Seasoned]             │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  INDIE GAMES 2026                               [Year: 2026 ▼]              │
│                                                                              │
│  ┌──────────────┬───────────────────────────────────────────────────────────┤
│  │              │                                                            │
│  │  GENRES      │  METROIDVANIA                                             │
│  │  ──────────  │  ════════════════════════════════════════════════════════ │
│  │              │                                                            │
│  │ ● Metroidva. │  JANUARY                                                  │
│  │   Roguelike  │  ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐         │
│  │   Platformer │  │ [Game]  │ │ [Game]  │ │ [Game]  │ │ [Game]  │         │
│  │   Action     │  │  Cover  │ │  Cover  │ │  Cover  │ │  Cover  │         │
│  │   Adventure  │  │         │ │         │ │         │ │         │         │
│  │   RPG        │  └─────────┘ └─────────┘ └─────────┘ └─────────┘         │
│  │   Simulation │   Hollow Silks  Axiom 2    ...         ...                │
│  │   Horror     │                                                            │
│  │   Strategy   │  FEBRUARY                                                 │
│  │   ──────────  │  ┌─────────┐ ┌─────────┐                                 │
│  │   Other      │  │ [Game]  │ │ [Game]  │                                 │
│  │              │  │  Cover  │ │  Cover  │                                 │
│  │              │  └─────────┘ └─────────┘                                 │
│  │              │                                                            │
│  └──────────────┴───────────────────────────────────────────────────────────┘
└─────────────────────────────────────────────────────────────────────────────┘
```

### Frontend Indie Page (Mobile)

```
┌─────────────────────────────────────┐
│  [Header]                            │
├─────────────────────────────────────┤
│                                      │
│  INDIE GAMES 2026      [2026 ▼]     │
│                                      │
│ ◄ [Metroid.][Rogue.][Platf.][Action]►│
│   ════════                           │
│                                      │
│  JANUARY                             │
│  ┌─────────┐ ┌─────────┐            │
│  │ [Game]  │ │ [Game]  │            │
│  │  Cover  │ │  Cover  │            │
│  └─────────┘ └─────────┘            │
│   Hollow Silks  Axiom 2             │
│                                      │
│  ┌─────────┐ ┌─────────┐            │
│  │ [Game]  │ │ [Game]  │            │
│  └─────────┘ └─────────┘            │
│                                      │
│  FEBRUARY                            │
│  ...                                 │
│                                      │
└─────────────────────────────────────┘
```

---

## Migration Strategy

### Phase 1: Schema Updates
1. Run migration to add new columns to `genres` table
2. Run migration to add `genre_ids` and `primary_genre_id` to `game_list_game`
3. Drop `indie_genre` column from `game_list_game` (existing data will be destroyed)
4. Create "Other" system genre via seeder
5. Add initial curated genres (current config + common genres like souls-like)

### Phase 2: Code Deployment
1. Deploy updated models and controllers
2. Deploy genre admin page
3. Deploy updated modals with Tom Select
4. Deploy frontend sidebar navigation
5. Remove `config/system-lists.php` genre configuration (now DB-driven)
6. Remove legacy genre grouping code from controllers

---

## Initial Genre Seed List

Curated hybrid list (existing config + common additions):

```php
// database/seeders/GenreSeeder.php

$genres = [
    // From current config
    ['name' => 'Metroidvania', 'slug' => 'metroidvania'],
    ['name' => 'Roguelike', 'slug' => 'roguelike'],
    ['name' => 'Platformer', 'slug' => 'platformer'],
    ['name' => 'Adventure', 'slug' => 'adventure'],
    ['name' => 'Action', 'slug' => 'action'],
    ['name' => 'RPG', 'slug' => 'rpg'],
    ['name' => 'Simulation', 'slug' => 'simulation'],
    ['name' => 'Strategy', 'slug' => 'strategy'],
    ['name' => 'Horror', 'slug' => 'horror'],
    ['name' => 'Beat-em-up', 'slug' => 'beat-em-up'],
    ['name' => 'Shooter', 'slug' => 'shooter'],

    // Common additions
    ['name' => 'Souls-like', 'slug' => 'souls-like'],
    ['name' => 'Puzzle', 'slug' => 'puzzle'],
    ['name' => 'Racing', 'slug' => 'racing'],
    ['name' => 'Sports', 'slug' => 'sports'],
    ['name' => 'Fighting', 'slug' => 'fighting'],
    ['name' => 'Visual Novel', 'slug' => 'visual-novel'],
    ['name' => 'City Builder', 'slug' => 'city-builder'],
    ['name' => 'Tower Defense', 'slug' => 'tower-defense'],
    ['name' => 'Survival', 'slug' => 'survival'],
    ['name' => 'Farming Sim', 'slug' => 'farming-sim'],

    // System genre (protected, always last)
    ['name' => 'Other', 'slug' => 'other', 'is_system' => true, 'sort_order' => 999999],
];
```

---

## Files to Create

| File | Purpose |
|------|---------|
| `database/migrations/xxx_modify_genres_table_for_rework.php` | Add new columns to genres |
| `database/migrations/xxx_modify_game_list_game_for_multi_genres.php` | Add genre_ids/primary_genre_id |
| `database/seeders/GenreSeeder.php` | Initial genre population |
| `app/Http/Controllers/AdminGenreController.php` | Genre admin CRUD + bulk ops |
| `app/Http/Requests/StoreGenreRequest.php` | Genre create validation |
| `app/Http/Requests/UpdateGenreRequest.php` | Genre update validation |
| `resources/views/admin/genres/index.blade.php` | Genre admin page |
| `resources/views/components/admin/system-lists/add-game-modal.blade.php` | Unified modal |
| `resources/js/components/add-game-modal.js` | Alpine.js modal logic |
| `tests/Feature/GenreManagementTest.php` | Genre admin tests |
| `tests/Feature/MultiGenreTest.php` | Multi-genre assignment tests |

## Files to Modify

| File | Changes |
|------|---------|
| `app/Models/Genre.php` | Add new fields, scopes, protection logic |
| `app/Models/GameList.php` | Update pivot fields in relationship |
| `app/Http/Controllers/AdminListController.php` | Accept genre data in addGame(), toggle methods |
| `app/Http/Controllers/IndieGamesController.php` | Use DB genres instead of config, sidebar logic |
| `app/Services/IgdbService.php` | Queue new genres for review instead of auto-add |
| `resources/views/indie-games/index.blade.php` | Sidebar navigation + mobile tabs |
| `resources/views/admin/system-lists/edit.blade.php` | Integrate unified modal |
| `resources/views/components/admin/system-lists/game-grid.blade.php` | Use unified modal |
| `resources/views/components/admin/system-lists/game-search.blade.php` | Trigger modal on add |
| `resources/views/components/header.blade.php` | Add Genres link to admin nav |
| `resources/js/app.js` | Import Tom Select, register modal component |
| `routes/web.php` | Add genre admin routes |
| `config/system-lists.php` | Remove hardcoded genres (after migration) |

---

## Implementation Priority

1. **Modal/Input First** (quick visible progress)
   - Tom Select integration
   - Unified modal component
   - Update addGame() to accept genres
   - Update existing modals to use new component

2. **Genre Admin Page**
   - CRUD operations
   - Bulk operations
   - Pending review queue
   - Usage statistics

3. **Frontend Rework**
   - Sidebar navigation
   - Mobile horizontal tabs
   - Dynamic genre loading from DB

4. **Data Migration**
   - CSV export tool
   - CSV import tool
   - Legacy column cleanup

---

## Testing Plan

```php
// tests/Feature/GenreManagementTest.php

describe('Genre CRUD', function () {
    it('allows admin to create a genre');
    it('auto-generates slug from name');
    it('allows admin to update genre name');
    it('prevents deletion of genre in use');
    it('prevents deletion of system genre');
    it('allows deletion of unused genre');
});

describe('Genre Merge', function () {
    it('merges source genre into target');
    it('updates all pivot records with source genre');
    it('deletes source genre after merge');
    it('prevents merging system genre');
});

describe('Genre Review Queue', function () {
    it('shows pending genres from IGDB sync');
    it('approves pending genre');
    it('rejects and deletes pending genre');
});

describe('Bulk Operations', function () {
    it('removes genre from all games in list');
    it('replaces genre A with B in list');
    it('assigns genre to multiple games');
});

// tests/Feature/MultiGenreTest.php

describe('Multi-Genre Assignment', function () {
    it('allows assigning up to 3 genres');
    it('prevents assigning more than 3 genres');
    it('requires primary genre when adding to indie list');
    it('pre-populates with game IGDB genres');
});

describe('Modal Integration', function () {
    it('shows modal when adding game from search');
    it('shows modal when toggling indie status');
    it('saves genre data to pivot table');
});

describe('Frontend Display', function () {
    it('displays games in primary genre tab');
    it('shows Other tab for unassigned games');
    it('orders genres by admin sort order');
    it('shows Other tab last');
});
```

---

## Design Decisions Summary

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Genre source | Hybrid (IGDB + custom) | Flexibility with good defaults |
| Data model | JSON array on pivot | Simpler queries, adequate for max 3 genres |
| Pre-population | IGDB genres auto-filled | Reduces admin work, maintains accuracy |
| Primary genre | Separate single-select field | Clear distinction from secondary genres |
| Genre limit | 3 (1 primary + 2 secondary) | Forces meaningful categorization |
| Tab display | Primary genre only | Avoids duplication, clear organization |
| Tag input | Tom Select | Mature library, good UX, searchable |
| Modal reuse | Shared base + variants | Maximum flexibility, clean code |
| Search add flow | Always show modal | Consistent UX, complete data entry |
| Migration | CSV export/import | Admin control, offline review |
| Frontend nav | Sidebar (desktop) / Tabs (mobile) | Best UX per viewport |
| "Other" genre | Protected system genre | Always available, cannot be deleted |
| IGDB sync | Queue for review | Controlled additions, no auto-pollution |
| Delete behavior | Hard delete | Simple, genres only deletable when unused |
| Removal from list | Delete assignments | Clean data, no orphan records |