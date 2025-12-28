<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PlayerPerspective extends Model
{
    protected $fillable = [
        'igdb_id',
        'name',
    ];

    public function games(): BelongsToMany
    {
        return $this->belongsToMany(Game::class, 'game_player_perspective')
            ->withTimestamps();
    }
}
