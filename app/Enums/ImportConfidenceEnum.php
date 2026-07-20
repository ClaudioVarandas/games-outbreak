<?php

declare(strict_types=1);

namespace App\Enums;

enum ImportConfidenceEnum: string
{
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';

    public function label(): string
    {
        return match ($this) {
            self::High => 'High',
            self::Medium => 'Medium',
            self::Low => 'Low',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::High => 'bg-green-100 text-green-800 border border-green-300 dark:bg-green-500/10 dark:text-green-300 dark:border-green-500/30',
            self::Medium => 'bg-amber-100 text-amber-800 border border-amber-300 dark:bg-amber-500/10 dark:text-amber-300 dark:border-amber-500/30',
            self::Low => 'bg-red-100 text-red-800 border border-red-300 dark:bg-red-500/10 dark:text-red-300 dark:border-red-500/30',
        };
    }

    public function dotClass(): string
    {
        return match ($this) {
            self::High => 'bg-green-500',
            self::Medium => 'bg-amber-500',
            self::Low => 'bg-red-500',
        };
    }
}
