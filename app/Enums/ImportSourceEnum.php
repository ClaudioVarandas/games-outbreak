<?php

declare(strict_types=1);

namespace App\Enums;

enum ImportSourceEnum: string
{
    case Igdb = 'igdb';
    case Steam = 'steam';
    case Press = 'press';
    case Web = 'web';

    private const FALLBACK_BADGE_CLASS = 'bg-gray-100 text-gray-700 border border-gray-300 dark:bg-gray-500/10 dark:text-gray-300 dark:border-gray-500/30';

    public function label(): string
    {
        return match ($this) {
            self::Igdb => 'IGDB',
            self::Steam => 'Steam',
            self::Press => 'Press',
            self::Web => 'Web',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Igdb => 'bg-purple-100 text-purple-800 border border-purple-300 dark:bg-purple-500/10 dark:text-purple-300 dark:border-purple-500/30',
            self::Steam => 'bg-sky-100 text-sky-800 border border-sky-300 dark:bg-sky-500/10 dark:text-sky-300 dark:border-sky-500/30',
            self::Press => 'bg-rose-100 text-rose-800 border border-rose-300 dark:bg-rose-500/10 dark:text-rose-300 dark:border-rose-500/30',
            self::Web => self::FALLBACK_BADGE_CLASS,
        };
    }

    public static function labelFor(string $source): string
    {
        return self::tryFrom($source)?->label() ?? ucfirst($source);
    }

    public static function badgeClassFor(string $source): string
    {
        return self::tryFrom($source)?->badgeClass() ?? self::FALLBACK_BADGE_CLASS;
    }
}
