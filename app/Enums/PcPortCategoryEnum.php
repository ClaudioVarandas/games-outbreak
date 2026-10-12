<?php

declare(strict_types=1);

namespace App\Enums;

enum PcPortCategoryEnum: string
{
    case PcPort = 'pc_port';
    case Mod = 'mod';
    case Radar = 'radar';

    public function label(): string
    {
        return match ($this) {
            self::PcPort => 'PC port',
            self::Mod => 'Mod',
            self::Radar => 'Radar',
        };
    }

    public function isNativePort(): bool
    {
        return $this === self::PcPort;
    }
}
