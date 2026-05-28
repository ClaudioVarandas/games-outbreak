<?php

declare(strict_types=1);

namespace App\Enums;

enum ReleaseHeroVariantEnum: string
{
    case Success = 'success';
    case Upcoming = 'upcoming';
    case EarlyAccess = 'early_access';
    case Tba = 'tba';

    public function colorClass(): string
    {
        return match ($this) {
            self::Success => 'text-green-400',
            self::EarlyAccess => 'text-orange-400',
            self::Upcoming => 'text-cyan-300',
            self::Tba => 'text-slate-400',
        };
    }
}
