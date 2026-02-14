<?php

namespace App\Models;

use App\Enums\ListTypeEnum;
use App\Enums\PlatformEnum;
use Carbon\Carbon;
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
        'event_data',
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
        'event_data' => 'array',
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
            ->withPivot('order', 'release_date', 'platforms', 'platform_group', 'is_highlight', 'is_tba', 'is_indie', 'genre_ids', 'primary_genre_id')
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

    public function scopeYearly(Builder $query): Builder
    {
        return $query->where('list_type', ListTypeEnum::YEARLY->value);
    }

    public function scopeSeasoned(Builder $query): Builder
    {
        return $query->where('list_type', ListTypeEnum::SEASONED->value);
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

    public function isYearly(): bool
    {
        return $this->list_type === ListTypeEnum::YEARLY;
    }

    public function isSeasoned(): bool
    {
        return $this->list_type === ListTypeEnum::SEASONED;
    }

    public function isEvents(): bool
    {
        return $this->list_type === ListTypeEnum::EVENTS;
    }

    public function canHaveHighlights(): bool
    {
        return $this->isYearly();
    }

    public function canMarkAsIndie(): bool
    {
        return $this->isYearly() || $this->isSeasoned();
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
            if (str_starts_with($this->og_image_path, '/')) {
                return asset($this->og_image_path);
            }

            return asset('storage/list-og-images/'.$this->og_image_path);
        }

        // Fallback to first game's cover
        return $this->games->first()?->getCoverUrl();
    }

    /**
     * Get event time as Carbon instance with timezone
     */
    public function getEventTime(): ?\Carbon\Carbon
    {
        $eventTime = $this->event_data['event_time'] ?? null;
        $eventTimezone = $this->event_data['event_timezone'] ?? 'UTC';

        if (! $eventTime) {
            return null;
        }

        return \Carbon\Carbon::parse($eventTime, $eventTimezone);
    }

    /**
     * Get event timezone string
     */
    public function getEventTimezone(): ?string
    {
        return $this->event_data['event_timezone'] ?? null;
    }

    /**
     * Get event about text
     */
    public function getEventAbout(): ?string
    {
        return $this->event_data['about'] ?? null;
    }

    /**
     * Get social links array
     */
    public function getSocialLinks(): array
    {
        return $this->event_data['social_links'] ?? [];
    }

    /**
     * Get video URL
     */
    public function getVideoUrl(): ?string
    {
        return $this->event_data['video_url'] ?? null;
    }

    /**
     * Get video embed URL (converts YouTube/Twitch URLs to embed format)
     */
    public function getVideoEmbedUrl(): ?string
    {
        $url = $this->getVideoUrl();

        if (! $url) {
            return null;
        }

        // YouTube: https://www.youtube.com/watch?v=VIDEO_ID or https://youtu.be/VIDEO_ID
        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return 'https://www.youtube.com/embed/'.$matches[1];
        }

        // Twitch: https://www.twitch.tv/CHANNEL or https://www.twitch.tv/videos/VIDEO_ID
        if (preg_match('/twitch\.tv\/videos\/(\d+)/', $url, $matches)) {
            return 'https://player.twitch.tv/?video='.$matches[1].'&parent='.parse_url(config('app.url'), PHP_URL_HOST);
        }

        if (preg_match('/twitch\.tv\/([a-zA-Z0-9_]+)/', $url, $matches)) {
            return 'https://player.twitch.tv/?channel='.$matches[1].'&parent='.parse_url(config('app.url'), PHP_URL_HOST);
        }

        return null;
    }

    /**
     * Check if event has video
     */
    public function hasVideo(): bool
    {
        return ! empty($this->getVideoUrl());
    }

    /**
     * Check if event has social links
     */
    public function hasSocialLinks(): bool
    {
        $links = $this->getSocialLinks();

        return ! empty(array_filter($links));
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
            // Get platforms from pivot if set, otherwise from game
            $pivotPlatforms = $game->pivot->platforms ?? null;
            if ($pivotPlatforms && is_string($pivotPlatforms)) {
                $pivotPlatforms = json_decode($pivotPlatforms, true);
            }

            if (! empty($pivotPlatforms)) {
                // Use pivot platforms (array of igdb_ids)
                foreach ($pivotPlatforms as $igdbId) {
                    $enum = PlatformEnum::fromIgdbId($igdbId);
                    if ($enum) {
                        $platforms[$igdbId] = [
                            'id' => $igdbId,
                            'igdb_id' => $igdbId,
                            'name' => $enum->label(),
                            'color' => $enum->color(),
                            'count' => ($platforms[$igdbId]['count'] ?? 0) + 1,
                        ];
                    }
                }
            } else {
                // Fallback to game platforms
                foreach ($game->platforms as $platform) {
                    $enum = PlatformEnum::fromIgdbId($platform->igdb_id);
                    $platforms[$platform->id] = [
                        'id' => $platform->id,
                        'igdb_id' => $platform->igdb_id,
                        'name' => $enum?->label() ?? $platform->name,
                        'color' => $enum?->color() ?? 'gray',
                        'count' => ($platforms[$platform->id]['count'] ?? 0) + 1,
                    ];
                }
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

    public function groupGamesByMonth(?int $filterMonth = null): array
    {
        $gamesByMonth = [];

        foreach ($this->games as $game) {
            if ($game->pivot->is_tba) {
                if ($filterMonth !== null) {
                    continue;
                }
                $monthKey = 'tba';
                $monthLabel = 'To Be Announced';
                $monthNumber = null;
            } else {
                $releaseDate = $game->pivot->release_date ?? $game->first_release_date;
                if ($releaseDate && is_string($releaseDate)) {
                    $releaseDate = Carbon::parse($releaseDate);
                }

                if (! $releaseDate) {
                    if ($filterMonth !== null) {
                        continue;
                    }
                    $monthKey = 'tba';
                    $monthLabel = 'To Be Announced';
                    $monthNumber = null;
                } else {
                    $monthNumber = (int) $releaseDate->month;

                    if ($filterMonth !== null && $monthNumber !== $filterMonth) {
                        continue;
                    }

                    $monthKey = $releaseDate->format('Y-m');
                    $monthLabel = $releaseDate->format('F Y');
                }
            }

            if (! isset($gamesByMonth[$monthKey])) {
                $gamesByMonth[$monthKey] = [
                    'label' => $monthLabel,
                    'month_number' => $monthNumber ?? null,
                    'games' => [],
                ];
            }

            $gamesByMonth[$monthKey]['games'][] = $game;
        }

        uksort($gamesByMonth, function ($a, $b) {
            if ($a === 'tba') {
                return -1;
            }
            if ($b === 'tba') {
                return 1;
            }

            return $a <=> $b;
        });

        return $gamesByMonth;
    }

    /**
     * Convert games to array format for Alpine.js filtering
     */
    public function getGamesForFiltering(): array
    {
        return $this->games->map(function ($game) {
            // Get platforms from pivot if set, otherwise from game
            $pivotPlatforms = $game->pivot->platforms ?? null;
            if ($pivotPlatforms && is_string($pivotPlatforms)) {
                $pivotPlatforms = json_decode($pivotPlatforms, true);
            }

            $platforms = collect();
            if (! empty($pivotPlatforms)) {
                // Use pivot platforms (array of igdb_ids)
                foreach ($pivotPlatforms as $igdbId) {
                    $enum = PlatformEnum::fromIgdbId($igdbId);
                    if ($enum) {
                        $platforms->push([
                            'id' => $igdbId,
                            'igdb_id' => $igdbId,
                            'name' => $enum->label(),
                            'color' => $enum->color(),
                            'priority' => PlatformEnum::getPriority($igdbId),
                        ]);
                    }
                }
            } else {
                // Fallback to game platforms
                $platforms = $game->platforms->map(function ($p) {
                    $enum = PlatformEnum::fromIgdbId($p->igdb_id);

                    return [
                        'id' => $p->id,
                        'igdb_id' => $p->igdb_id,
                        'name' => $enum?->label() ?? $p->name,
                        'color' => $enum?->color() ?? 'gray',
                        'priority' => PlatformEnum::getPriority($p->igdb_id),
                    ];
                });
            }

            return [
                'id' => $game->id,
                'uuid' => $game->uuid,
                'name' => $game->name,
                'slug' => $game->slug,
                'cover_url' => $game->getCoverUrl(),
                'release_date' => $game->pivot->release_date ?? $game->first_release_date?->format('Y-m-d'),
                'release_date_formatted' => $game->pivot->release_date
                    ? \Carbon\Carbon::parse($game->pivot->release_date)->format('M j, Y')
                    : $game->first_release_date?->format('M j, Y') ?? 'TBA',
                'platforms' => $platforms->sortBy('priority')->values()->toArray(),
                'platform_group' => $game->pivot->platform_group ?? null,
                'is_highlight' => (bool) ($game->pivot->is_highlight ?? false),
                'is_indie' => (bool) ($game->pivot->is_indie ?? false),
                'is_tba' => (bool) ($game->pivot->is_tba ?? false),
                'genres' => $game->genres->map(fn ($g) => [
                    'id' => $g->id,
                    'name' => $g->name,
                ])->toArray(),
                'genre_ids' => json_decode($game->pivot->genre_ids ?? '[]', true) ?: [],
                'primary_genre_id' => $game->pivot->primary_genre_id ?? null,
                'game_type' => [
                    'id' => $game->getGameTypeEnum()->value,
                    'name' => $game->getGameTypeEnum()->label(),
                    'color' => $game->getGameTypeEnum()->colorClass(),
                ],
                'modes' => $game->gameModes->map(fn ($m) => [
                    'id' => $m->id,
                    'name' => $m->name,
                ])->toArray(),
                'perspectives' => $game->playerPerspectives->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                ])->toArray(),
            ];
        })->toArray();
    }
}
