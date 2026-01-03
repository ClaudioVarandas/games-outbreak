<?php
declare(strict_types=1);

namespace App\Enums;

enum ListTypeEnum: string
{
    case REGULAR = 'regular';
    case BACKLOG = 'backlog';
    case WISHLIST = 'wishlist';
    case MONTHLY = 'monthly';
    case SEASONED = 'seasoned';
    case INDIE_GAMES = 'indie-games';

    public function label(): string
    {
        return match ($this) {
            self::REGULAR => 'Regular',
            self::BACKLOG => 'Backlog',
            self::WISHLIST => 'Wishlist',
            self::MONTHLY => 'Monthly',
            self::SEASONED => 'Seasoned',
            self::INDIE_GAMES => 'Indie Games',
        };
    }

    public function isUniquePerUser(): bool
    {
        return match ($this) {
            self::REGULAR => false,
            self::BACKLOG => true,
            self::WISHLIST => true,
            self::MONTHLY => false,
            self::SEASONED => false,
            self::INDIE_GAMES => false,
        };
    }

    public function isSystemListType(): bool
    {
        return match ($this) {
            self::MONTHLY => true,
            self::SEASONED => true,
            self::INDIE_GAMES => true,
            default => false,
        };
    }

    public static function fromValue(string $value): ?self
    {
        return match ($value) {
            'regular' => self::REGULAR,
            'backlog' => self::BACKLOG,
            'wishlist' => self::WISHLIST,
            'monthly' => self::MONTHLY,
            'seasoned' => self::SEASONED,
            'indie-games' => self::INDIE_GAMES,
            default => null,
        };
    }

    public function toSlug(): string
    {
        return match ($this) {
            self::REGULAR => 'regular',
            self::BACKLOG => 'backlog',
            self::WISHLIST => 'wishlist',
            self::MONTHLY => 'monthly',
            self::SEASONED => 'seasoned',
            self::INDIE_GAMES => 'indie',
        };
    }

    public static function fromSlug(string $slug): ?self
    {
        return match ($slug) {
            'regular' => self::REGULAR,
            'backlog' => self::BACKLOG,
            'wishlist' => self::WISHLIST,
            'monthly' => self::MONTHLY,
            'seasoned' => self::SEASONED,
            'indie' => self::INDIE_GAMES,
            default => null,
        };
    }
}







