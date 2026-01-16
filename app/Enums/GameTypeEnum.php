<?php

declare(strict_types=1);

namespace App\Enums;

enum GameTypeEnum: int
{
    // IGDB game_type values - https://api-docs.igdb.com/#game-type
    case MAIN = 0;
    case DLC = 1;
    case EXPANSION = 2;
    case BUNDLE = 3;
    case STANDALONE = 4;
    case MOD = 5;
    case EPISODE = 6;
    case SEASON = 7;
    case REMAKE = 8;
    case REMASTER = 9;

    public function label(): string
    {
        return match ($this) {
            self::MAIN => 'Main Game',
            self::DLC => 'DLC',
            self::EXPANSION => 'Expansion',
            self::BUNDLE => 'Bundle',
            self::STANDALONE => 'Standalone',
            self::MOD => 'Mod',
            self::EPISODE => 'Episode',
            self::SEASON => 'Season',
            self::REMAKE => 'Remake',
            self::REMASTER => 'Remaster',
        };
    }

    public function colorClass(): string
    {
        return match ($this) {
            self::MAIN => 'bg-orange-600/80',
            self::DLC => 'bg-orange-600/80',
            self::EXPANSION => 'bg-orange-600/80',
            self::BUNDLE => 'bg-pink-600/80',
            self::STANDALONE => 'bg-yellow-600/80',
            self::MOD => 'bg-green-600/80',
            self::EPISODE => 'bg-cyan-600/80',
            self::SEASON => 'bg-indigo-600/80',
            self::REMAKE => 'bg-red-600/80',
            self::REMASTER => 'bg-yellow-500/80',
        };
    }

    public static function fromValue(int $value): ?self
    {
        return self::tryFrom($value);
    }
}
