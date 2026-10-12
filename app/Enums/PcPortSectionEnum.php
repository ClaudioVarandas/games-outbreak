<?php

declare(strict_types=1);

namespace App\Enums;

enum PcPortSectionEnum: string
{
    case NativePorts = 'pc_port';
    case Tools = 'tools';
    case Mods = 'mod';
    case Radar = 'radar';

    public function label(): string
    {
        return match ($this) {
            self::NativePorts => 'Native ports',
            self::Tools => 'Multi & tools',
            self::Mods => 'Mods & enhancements',
            self::Radar => 'Radar',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::NativePorts => 'Recompilations and ports for PC.',
            self::Tools => 'Launchers and projects spanning several games.',
            self::Mods => 'Projects that complement the games.',
            self::Radar => 'Decompilations and future port candidates; not playable native ports.',
        };
    }

    public function flagLabel(): ?string
    {
        return match ($this) {
            self::NativePorts => null,
            self::Tools => 'Multi / tools',
            self::Mods => 'Mod',
            self::Radar => 'Radar',
        };
    }

    public static function fromCategory(PcPortCategoryEnum $category): self
    {
        return match ($category) {
            PcPortCategoryEnum::PcPort => self::NativePorts,
            PcPortCategoryEnum::Mod => self::Mods,
            PcPortCategoryEnum::Radar => self::Radar,
        };
    }
}
