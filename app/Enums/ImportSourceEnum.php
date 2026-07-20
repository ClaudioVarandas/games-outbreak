<?php

declare(strict_types=1);

namespace App\Enums;

enum ImportSourceEnum: string
{
    case Igdb = 'igdb';
    case Steam = 'steam';
    case Press = 'press';
    case Web = 'web';

    private const FALLBACK_BADGE_CLASS = 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300';

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
            self::Igdb => 'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300',
            self::Steam => 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300',
            self::Press => 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300',
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
