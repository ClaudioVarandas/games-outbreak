<?php

declare(strict_types=1);

namespace App\Enums;

enum DiscoverySourcePurposeEnum: string
{
    case News = 'news';
    case Releases = 'releases';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::News => 'News',
            self::Releases => 'Releases',
            self::Both => 'News + Releases',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::News => 'bg-sky-100 text-sky-800 border border-sky-300 dark:bg-sky-500/10 dark:text-sky-300 dark:border-sky-500/30',
            self::Releases => 'bg-emerald-100 text-emerald-800 border border-emerald-300 dark:bg-emerald-500/10 dark:text-emerald-300 dark:border-emerald-500/30',
            self::Both => 'bg-violet-100 text-violet-800 border border-violet-300 dark:bg-violet-500/10 dark:text-violet-300 dark:border-violet-500/30',
        };
    }

    public function coversNews(): bool
    {
        return match ($this) {
            self::News, self::Both => true,
            self::Releases => false,
        };
    }

    public function coversReleases(): bool
    {
        return match ($this) {
            self::Releases, self::Both => true,
            self::News => false,
        };
    }
}
