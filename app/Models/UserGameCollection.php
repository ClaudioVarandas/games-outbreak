<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserGameCollection extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'cover_image_path',
        'privacy_playing',
        'privacy_played',
        'privacy_backlog',
        'privacy_wishlist',
    ];

    protected function casts(): array
    {
        return [
            'privacy_playing' => 'boolean',
            'privacy_played' => 'boolean',
            'privacy_backlog' => 'boolean',
            'privacy_wishlist' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isStatusPublic(string $status): bool
    {
        return match ($status) {
            'playing' => $this->privacy_playing,
            'played' => $this->privacy_played,
            'backlog' => $this->privacy_backlog,
            'wishlist' => $this->privacy_wishlist,
            default => true,
        };
    }

    public function getCoverImageUrlAttribute(): ?string
    {
        if (! $this->cover_image_path) {
            return null;
        }

        return asset('storage/'.$this->cover_image_path);
    }
}
