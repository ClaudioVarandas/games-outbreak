<?php

namespace App\Models;

use App\Enums\ListTypeEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class GameList extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'og_image_path',
        'sections',
        'auto_section_by_genre',
        'tags',
        'slug',
        'is_public',
        'is_system',
        'is_active',
        'start_at',
        'end_at',
        'list_type',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'is_system' => 'boolean',
        'is_active' => 'boolean',
        'auto_section_by_genre' => 'boolean',
        'sections' => 'array',
        'tags' => 'array',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'list_type' => ListTypeEnum::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function games(): BelongsToMany
    {
        return $this->belongsToMany(Game::class, 'game_list_game')
            ->withPivot('order', 'release_date', 'platforms')
            ->withTimestamps()
            ->orderByPivot('order');
    }

    // Scopes
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    public function scopePrivate(Builder $query): Builder
    {
        return $query->where('is_public', false);
    }

    public function scopeSystem(Builder $query): Builder
    {
        return $query->where('is_system', true);
    }

    public function scopeUserLists(Builder $query): Builder
    {
        return $query->where('is_system', false);
    }

    public function scopeRegular(Builder $query): Builder
    {
        return $query->where('list_type', ListTypeEnum::REGULAR->value);
    }

    public function scopeBacklog(Builder $query): Builder
    {
        return $query->where('list_type', ListTypeEnum::BACKLOG->value);
    }

    public function scopeWishlist(Builder $query): Builder
    {
        return $query->where('list_type', ListTypeEnum::WISHLIST->value);
    }

    public function scopeMonthly(Builder $query): Builder
    {
        return $query->where('list_type', ListTypeEnum::MONTHLY->value);
    }

    public function scopeSeasoned(Builder $query): Builder
    {
        return $query->where('list_type', ListTypeEnum::SEASONED->value);
    }

    public function scopeIndieGames(Builder $query): Builder
    {
        return $query->where('list_type', ListTypeEnum::INDIE_GAMES->value);
    }

    public function scopeEvents(Builder $query): Builder
    {
        return $query->where('list_type', ListTypeEnum::EVENTS->value);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('end_at')
                    ->orWhere('end_at', '>', now());
            })
            ->where(function ($q) {
                $q->whereNull('start_at')
                    ->orWhere('start_at', '<=', now());
            });
    }

    // Methods
    public function isSystem(): bool
    {
        return $this->is_system;
    }

    public function isRegular(): bool
    {
        return $this->list_type === ListTypeEnum::REGULAR;
    }

    public function isBacklog(): bool
    {
        return $this->list_type === ListTypeEnum::BACKLOG;
    }

    public function isWishlist(): bool
    {
        return $this->list_type === ListTypeEnum::WISHLIST;
    }

    public function isMonthly(): bool
    {
        return $this->list_type === ListTypeEnum::MONTHLY;
    }

    public function isSeasoned(): bool
    {
        return $this->list_type === ListTypeEnum::SEASONED;
    }

    public function isIndieGames(): bool
    {
        return $this->list_type === ListTypeEnum::INDIE_GAMES;
    }

    public function isEvents(): bool
    {
        return $this->list_type === ListTypeEnum::EVENTS;
    }

    public function isSpecialList(): bool
    {
        return $this->isBacklog() || $this->isWishlist();
    }

    public function canBeDeleted(): bool
    {
        return ! $this->isSpecialList();
    }

    public function canBeEditedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        // Admins can edit any list
        if ($user->isAdmin()) {
            return true;
        }

        // Non-admins cannot edit system lists
        if ($this->is_system) {
            return false;
        }

        // Users can edit their own lists (check for null user_id)
        return $this->user_id !== null && $this->user_id === $user->id;
    }

    public function canBeRenamed(): bool
    {
        return ! $this->isSpecialList();
    }

    /**
     * Get the OG image URL for social sharing
     */
    public function getOgImageUrlAttribute(): ?string
    {
        if ($this->og_image_path) {
            return asset($this->og_image_path);
        }

        // Fallback to first game's cover
        $firstGame = $this->games->first();

        return $firstGame?->getCoverUrl();
    }

    /**
     * Get computed sections for display
     * Returns admin-defined sections, auto-genre sections, or empty array
     */
    public function getComputedSections(): array
    {
        // If admin-defined sections exist, use them
        if (! empty($this->sections)) {
            return $this->buildSectionsFromDefinition($this->sections);
        }

        // If auto-section by genre is enabled, group by genre
        if ($this->auto_section_by_genre) {
            return $this->buildSectionsByGenre();
        }

        // Return empty (flat list)
        return [];
    }

    /**
     * Build sections from admin-defined section structure
     */
    private function buildSectionsFromDefinition(array $sectionDef): array
    {
        $gameMap = $this->games->keyBy('id');
        $sections = [];

        foreach ($sectionDef as $section) {
            $sectionGames = collect($section['game_ids'] ?? [])
                ->map(fn ($id) => $gameMap->get($id))
                ->filter()
                ->values();

            if ($sectionGames->isNotEmpty()) {
                $sections[] = [
                    'id' => $section['id'] ?? str()->uuid()->toString(),
                    'name' => $section['name'] ?? 'Untitled Section',
                    'games' => $sectionGames,
                ];
            }
        }

        return $sections;
    }

    /**
     * Build sections automatically by grouping games by their primary genre
     */
    private function buildSectionsByGenre(): array
    {
        $gamesByGenre = [];

        foreach ($this->games as $game) {
            $primaryGenre = $game->genres->first();
            $genreName = $primaryGenre?->name ?? 'Other';
            $genreId = $primaryGenre?->id ?? 0;

            if (! isset($gamesByGenre[$genreId])) {
                $gamesByGenre[$genreId] = [
                    'id' => 'genre-'.$genreId,
                    'name' => $genreName,
                    'games' => collect(),
                ];
            }

            $gamesByGenre[$genreId]['games']->push($game);
        }

        // Sort sections by name and convert games to array
        return collect($gamesByGenre)
            ->sortBy('name')
            ->map(fn ($section) => [
                'id' => $section['id'],
                'name' => $section['name'],
                'games' => $section['games']->values(),
            ])
            ->values()
            ->toArray();
    }

    /**
     * Get unique filter options from all games in this list
     */
    public function getFilterOptions(): array
    {
        $platforms = [];
        $genres = [];
        $gameTypes = [];
        $modes = [];
        $perspectives = [];

        foreach ($this->games as $game) {
            foreach ($game->platforms as $platform) {
                $platforms[$platform->id] = [
                    'id' => $platform->id,
                    'igdb_id' => $platform->igdb_id,
                    'name' => $platform->name,
                    'count' => ($platforms[$platform->id]['count'] ?? 0) + 1,
                ];
            }

            foreach ($game->genres as $genre) {
                $genres[$genre->id] = [
                    'id' => $genre->id,
                    'name' => $genre->name,
                    'count' => ($genres[$genre->id]['count'] ?? 0) + 1,
                ];
            }

            // Game type from enum
            $gameType = $game->getGameTypeEnum();
            $gameTypes[$gameType->value] = [
                'id' => $gameType->value,
                'name' => $gameType->label(),
                'count' => ($gameTypes[$gameType->value]['count'] ?? 0) + 1,
            ];

            foreach ($game->gameModes as $mode) {
                $modes[$mode->id] = [
                    'id' => $mode->id,
                    'name' => $mode->name,
                    'count' => ($modes[$mode->id]['count'] ?? 0) + 1,
                ];
            }

            foreach ($game->playerPerspectives as $perspective) {
                $perspectives[$perspective->id] = [
                    'id' => $perspective->id,
                    'name' => $perspective->name,
                    'count' => ($perspectives[$perspective->id]['count'] ?? 0) + 1,
                ];
            }
        }

        return [
            'platforms' => collect($platforms)->sortByDesc('count')->values()->toArray(),
            'genres' => collect($genres)->sortByDesc('count')->values()->toArray(),
            'gameTypes' => collect($gameTypes)->sortByDesc('count')->values()->toArray(),
            'modes' => collect($modes)->sortByDesc('count')->values()->toArray(),
            'perspectives' => collect($perspectives)->sortByDesc('count')->values()->toArray(),
        ];
    }

    /**
     * Convert games to array format for Alpine.js filtering
     */
    public function getGamesForFiltering(): array
    {
        return $this->games->map(fn ($game) => [
            'id' => $game->id,
            'name' => $game->name,
            'slug' => $game->slug,
            'cover_url' => $game->getCoverUrl(),
            'release_date' => $game->pivot->release_date ?? $game->first_release_date?->format('Y-m-d'),
            'release_date_formatted' => $game->pivot->release_date
                ? \Carbon\Carbon::parse($game->pivot->release_date)->format('M j, Y')
                : $game->first_release_date?->format('M j, Y') ?? 'TBA',
            'platforms' => $game->platforms->map(fn ($p) => [
                'id' => $p->id,
                'igdb_id' => $p->igdb_id,
                'name' => $p->name,
            ])->toArray(),
            'genres' => $game->genres->map(fn ($g) => [
                'id' => $g->id,
                'name' => $g->name,
            ])->toArray(),
            'game_type' => [
                'id' => $game->getGameTypeEnum()->value,
                'name' => $game->getGameTypeEnum()->label(),
            ],
            'modes' => $game->gameModes->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->name,
            ])->toArray(),
            'perspectives' => $game->playerPerspectives->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
            ])->toArray(),
        ])->toArray();
    }
}
