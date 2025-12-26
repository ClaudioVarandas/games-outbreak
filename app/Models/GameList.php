<?php

namespace App\Models;

use App\Enums\ListTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder;

class GameList extends Model
{
    use HasFactory;
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
        'list_type',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'is_system' => 'boolean',
        'is_active' => 'boolean',
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
            ->withPivot('order', 'release_date')
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

    public function isSpecialList(): bool
    {
        return $this->isBacklog() || $this->isWishlist();
    }

    public function canBeDeleted(): bool
    {
        return !$this->isSpecialList();
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

    public function canBeRenamed(): bool
    {
        return !$this->isSpecialList();
    }
}
