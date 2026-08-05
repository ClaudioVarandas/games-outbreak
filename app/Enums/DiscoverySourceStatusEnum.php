<?php

declare(strict_types=1);

namespace App\Enums;

enum DiscoverySourceStatusEnum: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Disabled = 'disabled';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Active => 'Active',
            self::Disabled => 'Disabled',
            self::Rejected => 'Rejected',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'bg-amber-100 text-amber-800 border border-amber-300 dark:bg-amber-500/10 dark:text-amber-300 dark:border-amber-500/30',
            self::Active => 'bg-green-100 text-green-800 border border-green-300 dark:bg-green-500/10 dark:text-green-300 dark:border-green-500/30',
            self::Disabled => 'bg-gray-100 text-gray-700 border border-gray-300 dark:bg-gray-500/10 dark:text-gray-300 dark:border-gray-500/30',
            self::Rejected => 'bg-red-100 text-red-800 border border-red-300 dark:bg-red-500/10 dark:text-red-300 dark:border-red-500/30',
        };
    }

    public function isUsable(): bool
    {
        return $this === self::Active;
    }
}
