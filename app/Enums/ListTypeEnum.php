<?php
declare(strict_types=1);

namespace App\Enums;

enum ListTypeEnum: string
{
    case REGULAR = 'regular';
    case BACKLOG = 'backlog';
    case WISHLIST = 'wishlist';

    public function label(): string
    {
        return match ($this) {
            self::REGULAR => 'Regular',
            self::BACKLOG => 'Backlog',
            self::WISHLIST => 'Wishlist',
        };
    }

    public function isUniquePerUser(): bool
    {
        return match ($this) {
            self::REGULAR => false,
            self::BACKLOG => true,
            self::WISHLIST => true,
        };
    }

    public static function fromValue(string $value): ?self
    {
        return match ($value) {
            'regular' => self::REGULAR,
            'backlog' => self::BACKLOG,
            'wishlist' => self::WISHLIST,
            default => null,
        };
    }
}



