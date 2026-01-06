<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameReleaseDate extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'date' => 'datetime',
        'is_manual' => 'boolean',
        'year' => 'integer',
        'month' => 'integer',
        'day' => 'integer',
        'region' => 'integer',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(ReleaseDateStatus::class, 'status_id');
    }

    /**
     * Get formatted release date (dd/mm/yyyy)
     */
    public function getFormattedDateAttribute(): ?string
    {
        return $this->date ? $this->date->format('d/m/Y') : null;
    }

    /**
     * Get platform name
     */
    public function getPlatformNameAttribute(): ?string
    {
        return $this->platform?->name;
    }

    /**
     * Get status name
     */
    public function getStatusNameAttribute(): ?string
    {
        return $this->status?->name;
    }

    /**
     * Get status abbreviation
     */
    public function getStatusAbbreviationAttribute(): ?string
    {
        return $this->status?->abbreviation;
    }

    /**
     * Scope to filter by platform IDs
     */
    public function scopeForPlatforms($query, array $platformIds)
    {
        return $query->whereIn('platform_id', $platformIds);
    }

    /**
     * Scope to order by date
     */
    public function scopeOrderByDate($query, $direction = 'asc')
    {
        return $query->orderBy('date', $direction);
    }
}
