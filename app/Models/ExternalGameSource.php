<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ExternalGameSource extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function games(): BelongsToMany
    {
        return $this->belongsToMany(Game::class, 'game_external_sources')
            ->withPivot(['external_uid', 'external_url', 'sync_status', 'retry_count', 'last_synced_at'])
            ->withTimestamps();
    }

    public function getStoreUrlAttribute(): ?string
    {
        return match ($this->igdb_id) {
            1 => 'https://store.steampowered.com/app/',
            5 => 'https://www.gog.com/game/',
            26 => 'https://store.epicgames.com/p/',
            default => null,
        };
    }
}
