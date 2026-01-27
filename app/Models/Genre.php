<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

class Genre extends Model
{
    use HasFactory;

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

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_visible', true);
    }

    public function scopePendingReview(Builder $query): Builder
    {
        return $query->where('is_pending_review', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function scopeNotOther(Builder $query): Builder
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
