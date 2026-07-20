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
            self::High => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
            self::Medium => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
            self::Low => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
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
