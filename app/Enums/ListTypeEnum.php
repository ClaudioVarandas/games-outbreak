<?php

declare(strict_types=1);

namespace App\Enums;

enum ListTypeEnum: string
{
    case REGULAR = 'regular';
    case BACKLOG = 'backlog';
    case WISHLIST = 'wishlist';
    case YEARLY = 'yearly';
    case SEASONED = 'seasoned';
    case EVENTS = 'events';

    public function label(): string
    {
        return match ($this) {
            self::REGULAR => 'Regular',
            self::BACKLOG => 'Backlog',
            self::WISHLIST => 'Wishlist',
            self::YEARLY => 'Yearly',
            self::SEASONED => 'Seasoned',
            self::EVENTS => 'Events',
        };
    }

    public function colorClass(): string
    {
        return match ($this) {
            self::REGULAR => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
            self::BACKLOG => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
            self::WISHLIST => 'bg-pink-100 text-pink-800 dark:bg-pink-900 dark:text-pink-200',
            self::YEARLY => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
            self::SEASONED => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
            self::EVENTS => 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900 dark:text-cyan-200',
        };
    }

    public function isUniquePerUser(): bool
    {
        return match ($this) {
            self::REGULAR => false,
            self::BACKLOG => true,
            self::WISHLIST => true,
            self::YEARLY => false,
            self::SEASONED => false,
            self::EVENTS => false,
        };
    }

    public function isSystemListType(): bool
    {
        return match ($this) {
            self::YEARLY => true,
            self::SEASONED => true,
            self::EVENTS => true,
            default => false,
        };
    }

    public static function fromValue(string $value): ?self
    {
        return match ($value) {
            'regular' => self::REGULAR,
            'backlog' => self::BACKLOG,
            'wishlist' => self::WISHLIST,
            'yearly' => self::YEARLY,
            'seasoned' => self::SEASONED,
            'events' => self::EVENTS,
            default => null,
        };
    }

    public function toSlug(): string
    {
        return match ($this) {
            self::REGULAR => 'regular',
            self::BACKLOG => 'backlog',
            self::WISHLIST => 'wishlist',
            self::YEARLY => 'yearly',
            self::SEASONED => 'seasoned',
            self::EVENTS => 'events',
        };
    }

    public static function fromSlug(string $slug): ?self
    {
        return match ($slug) {
            'regular' => self::REGULAR,
            'backlog' => self::BACKLOG,
            'wishlist' => self::WISHLIST,
            'yearly' => self::YEARLY,
            'seasoned' => self::SEASONED,
            'events' => self::EVENTS,
            default => null,
        };
    }
}
