<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder;

class GameList extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'description',
        'slug',
        'is_public',
        'is_system',
        'is_active',
        'start_at',
        'end_at',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'is_system' => 'boolean',
        'is_active' => 'boolean',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function games(): BelongsToMany
    {
        return $this->belongsToMany(Game::class, 'game_list_game')
            ->withPivot('order')
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

    public function canBeEditedBy(?User $user): bool
    {
        if (!$user) {
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
}
