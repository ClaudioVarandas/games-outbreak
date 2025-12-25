<?php
declare(strict_types=1);

namespace App\Enums;

enum GameTypeEnum: int
{
    case MAIN = 0;
    case DLC = 1;
    case EXPANSION = 2;
    case PORT = 3;
    case STANDALONE = 4;
    case BUNDLE = 5;
    case REMAKE = 8;
    case REMASTER = 9;
    case EXPANDED = 10;

    public function label(): string
    {
        return match ($this) {
            self::MAIN => 'Main Game',
            self::DLC => 'DLC',
            self::EXPANSION => 'Expansion',
            self::PORT => 'Port',
            self::STANDALONE => 'Standalone',
            self::BUNDLE => 'Bundle',
            self::REMAKE => 'Remake',
            self::REMASTER => 'Remaster',
            self::EXPANDED => 'Expanded',
        };
    }

    public function colorClass(): string
    {
        return match ($this) {
            self::MAIN => 'bg-green-600/80',
            self::DLC => 'bg-orange-600/80',
            self::EXPANSION => 'bg-teal-600/80',
            self::PORT => 'bg-blue-600/80',
            self::STANDALONE => 'bg-yellow-600/80',
            self::BUNDLE => 'bg-pink-600/80',
            self::REMAKE => 'bg-red-600/80',
            self::REMASTER => 'bg-yellow-500/80',
            self::EXPANDED => 'bg-purple-600/80',
        };
    }

    public static function fromValue(int $value): ?self
    {
        return match ($value) {
            0 => self::MAIN,
            1 => self::DLC,
            2 => self::EXPANSION,
            3 => self::PORT,
            4 => self::STANDALONE,
            5 => self::BUNDLE,
            8 => self::REMAKE,
            9 => self::REMASTER,
            10 => self::EXPANDED,
            default => null,
        };
    }
}

