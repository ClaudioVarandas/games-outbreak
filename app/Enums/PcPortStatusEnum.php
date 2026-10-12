<?php

declare(strict_types=1);

namespace App\Enums;

enum PcPortStatusEnum: string
{
    case Released = 'released';
    case Complete = 'complete';
    case Playable = 'playable';
    case Active = 'active';
    case Beta = 'beta';
    case AdvancedWip = 'advanced_wip';
    case Wip = 'wip';
    case Discovered = 'discovered';

    public function label(): string
    {
        return match ($this) {
            self::Released => 'Released',
            self::Complete => 'Complete',
            self::Playable => 'Playable',
            self::Active => 'Active',
            self::Beta => 'Beta',
            self::AdvancedWip => 'Advanced WIP',
            self::Wip => 'In development',
            self::Discovered => 'Discovered',
        };
    }

    public function pillClasses(): string
    {
        return match ($this) {
            self::Released, self::Complete, self::Playable => 'border-emerald-400/30 bg-emerald-400/15 text-emerald-300',
            self::Active, self::Beta => 'border-sky-400/30 bg-sky-400/15 text-sky-300',
            self::AdvancedWip, self::Wip => 'border-amber-400/30 bg-amber-400/15 text-amber-300',
            self::Discovered => 'border-slate-400/25 bg-slate-400/15 text-slate-400',
        };
    }

    public function isPlayable(): bool
    {
        return in_array($this, [self::Released, self::Complete, self::Playable], true);
    }
}
