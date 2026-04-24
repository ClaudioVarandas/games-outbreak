<?php

declare(strict_types=1);

namespace App\Enums;

enum VideoCategoryColorPresetEnum: string
{
    case NeonOrange = '#ff8a2a';
    case NeonCyan = '#63f3ff';
    case NeonPurple = '#7c3aed';
    case Lavender = '#c4b5fd';
    case Green = '#86efac';
    case Pink = '#f9a8d4';
    case Amber = '#ffbe7b';
    case Blue = '#93c5fd';

    public function label(): string
    {
        return match ($this) {
            self::NeonOrange => 'Neon Orange',
            self::NeonCyan => 'Neon Cyan',
            self::NeonPurple => 'Neon Purple',
            self::Lavender => 'Lavender',
            self::Green => 'Green',
            self::Pink => 'Pink',
            self::Amber => 'Amber',
            self::Blue => 'Blue',
        };
    }
}
