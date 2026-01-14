<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SteamGameData extends Model
{
    use HasFactory;

    protected $table = 'steam_game_data';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'average_forever' => 'integer',
            'average_2weeks' => 'integer',
            'median_forever' => 'integer',
            'median_2weeks' => 'integer',
            'ccu' => 'integer',
            'price' => 'integer',
            'score_rank' => 'integer',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function getPriceFormattedAttribute(): ?string
    {
        if ($this->price === null) {
            return null;
        }

        if ($this->price === 0) {
            return 'Free';
        }

        return '$'.number_format($this->price / 100, 2);
    }

    public function getOwnersRangeAttribute(): ?array
    {
        if (! $this->owners) {
            return null;
        }

        $parts = array_map('trim', explode('..', $this->owners));

        if (count($parts) !== 2) {
            return null;
        }

        return [
            'min' => (int) str_replace(',', '', $parts[0]),
            'max' => (int) str_replace(',', '', $parts[1]),
        ];
    }

    public function getAveragePlaytimeHoursAttribute(): ?float
    {
        if ($this->average_forever === null) {
            return null;
        }

        return round($this->average_forever / 60, 1);
    }
}
