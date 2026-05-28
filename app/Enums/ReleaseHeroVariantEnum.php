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

    public function icon(): string
    {
        return match ($this) {
            self::Success => 'check-circle',
            self::Upcoming => 'clock',
            self::EarlyAccess => 'beaker',
            self::Tba => 'question-mark-circle',
        };
    }

    public function ringClass(): string
    {
        return match ($this) {
            self::Success => 'border-green-400/50 bg-green-400/10',
            self::EarlyAccess => 'border-orange-400/50 bg-orange-400/10',
            self::Upcoming => 'border-cyan-300/50 bg-cyan-300/10',
            self::Tba => 'border-slate-400/50 bg-slate-400/10',
        };
    }

    public function barClass(): string
    {
        return match ($this) {
            self::Success => 'bg-green-400',
            self::EarlyAccess => 'bg-orange-400',
            self::Upcoming => 'bg-cyan-300',
            self::Tba => 'bg-slate-400',
        };
    }
}
