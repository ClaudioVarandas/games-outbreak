<?php

declare(strict_types=1);

namespace App\Enums;

enum DiscoverySourceOriginEnum: string
{
    case Seed = 'seed';
    case Admin = 'admin';
    case Agent = 'agent';

    public function label(): string
    {
        return match ($this) {
            self::Seed => 'Seed',
            self::Admin => 'Admin',
            self::Agent => 'Agent',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Seed => 'bg-gray-100 text-gray-700 border border-gray-300 dark:bg-gray-500/10 dark:text-gray-300 dark:border-gray-500/30',
            self::Admin => 'bg-blue-100 text-blue-800 border border-blue-300 dark:bg-blue-500/10 dark:text-blue-300 dark:border-blue-500/30',
            self::Agent => 'bg-fuchsia-100 text-fuchsia-800 border border-fuchsia-300 dark:bg-fuchsia-500/10 dark:text-fuchsia-300 dark:border-fuchsia-500/30',
        };
    }
}
