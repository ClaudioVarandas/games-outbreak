<?php

declare(strict_types=1);

namespace App\Enums;

enum DiscoverySourceKindEnum: string
{
    case Calendar = 'calendar';
    case Press = 'press';
    case Youtube = 'youtube';
    case X = 'x';

    public function label(): string
    {
        return match ($this) {
            self::Calendar => 'Calendar',
            self::Press => 'Press',
            self::Youtube => 'YouTube',
            self::X => 'X',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Calendar => 'bg-indigo-100 text-indigo-800 border border-indigo-300 dark:bg-indigo-500/10 dark:text-indigo-300 dark:border-indigo-500/30',
            self::Press => 'bg-rose-100 text-rose-800 border border-rose-300 dark:bg-rose-500/10 dark:text-rose-300 dark:border-rose-500/30',
            self::Youtube => 'bg-red-100 text-red-800 border border-red-300 dark:bg-red-500/10 dark:text-red-300 dark:border-red-500/30',
            self::X => 'bg-zinc-100 text-zinc-800 border border-zinc-300 dark:bg-zinc-500/10 dark:text-zinc-300 dark:border-zinc-500/30',
        };
    }
}
