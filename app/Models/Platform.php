<?php
declare(strict_types=1);

namespace App\Models;

use App\Enums\PlatformEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Platform extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function games(): BelongsToMany
    {
        return $this->belongsToMany(Game::class, 'game_platform');
    }

    public function getAbbreviationAttribute(): string
    {
        return PlatformEnum::fromIgdbId($this->igdb_id)?->label() ?? $this->name;
    }

    public function getColorAttribute(): string
    {
        return PlatformEnum::fromIgdbId($this->igdb_id)?->color() ?? 'gray';
    }
}
