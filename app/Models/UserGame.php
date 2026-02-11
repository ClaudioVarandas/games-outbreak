<?php

namespace App\Models;

use App\Enums\UserGameStatusEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserGame extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'game_id',
        'status',
        'is_wishlisted',
        'time_played',
        'rating',
        'sort_order',
        'added_at',
        'status_changed_at',
        'wishlisted_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => UserGameStatusEnum::class,
            'is_wishlisted' => 'boolean',
            'time_played' => 'decimal:1',
            'rating' => 'integer',
            'sort_order' => 'integer',
            'added_at' => 'datetime',
            'status_changed_at' => 'datetime',
            'wishlisted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    // Scopes

    public function scopePlaying(Builder $query): Builder
    {
        return $query->where('status', UserGameStatusEnum::Playing);
    }

    public function scopePlayed(Builder $query): Builder
    {
        return $query->where('status', UserGameStatusEnum::Played);
    }

    public function scopeBacklog(Builder $query): Builder
    {
        return $query->where('status', UserGameStatusEnum::Backlog);
    }

    public function scopeWishlisted(Builder $query): Builder
    {
        return $query->where('is_wishlisted', true);
    }

    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    // Helpers

    public function getFormattedTimePlayed(): ?string
    {
        if ($this->time_played === null) {
            return null;
        }

        $hours = (int) $this->time_played;
        $minutes = (int) round(($this->time_played - $hours) * 60);

        if ($hours > 0 && $minutes > 0) {
            return "{$hours}h {$minutes}m";
        }

        if ($hours > 0) {
            return "{$hours}h";
        }

        return "{$minutes}m";
    }
}
