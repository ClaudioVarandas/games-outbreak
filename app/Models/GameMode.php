<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class GameMode extends Model
{
    protected $guarded = ['id'];

    public function games(): BelongsToMany
    {
        return $this->belongsToMany(Game::class, 'game_mode');
    }
}
