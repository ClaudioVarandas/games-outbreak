<?php

declare(strict_types=1);

namespace App\Enums;

enum PcPortMetricEnum: string
{
    case Entries = 'entries';
    case NativePorts = 'native';
    case Playable = 'playable';
    case Platforms = 'platforms';

    public function label(): string
    {
        return match ($this) {
            self::Entries => 'entries',
            self::NativePorts => 'native ports',
            self::Playable => 'playable',
            self::Platforms => 'platforms',
        };
    }

    public function tileClasses(): string
    {
        return match ($this) {
            self::Entries => 'border-cyan-300/30 bg-gradient-to-br from-cyan-300/10 to-transparent',
            self::NativePorts => 'border-fuchsia-400/30 bg-gradient-to-br from-fuchsia-400/10 to-transparent',
            self::Playable => 'border-emerald-400/30 bg-gradient-to-br from-emerald-400/10 to-transparent',
            self::Platforms => 'border-orange-400/30 bg-gradient-to-br from-orange-400/10 to-transparent',
        };
    }

    public function valueClasses(): string
    {
        return match ($this) {
            self::Entries => 'text-cyan-300',
            self::NativePorts => 'text-fuchsia-400',
            self::Playable => 'text-emerald-300',
            self::Platforms => 'text-orange-400',
        };
    }
}
