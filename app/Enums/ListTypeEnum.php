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

    public function label(): string
    {
        return match ($this) {
            self::REGULAR => 'Regular',
            self::BACKLOG => 'Backlog',
            self::WISHLIST => 'Wishlist',
            self::MONTHLY => 'Monthly',
            self::SEASONED => 'Seasoned',
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
        };
    }

    public function isSystemListType(): bool
    {
        return match ($this) {
            self::MONTHLY => true,
            self::SEASONED => true,
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
            default => null,
        };
    }
}







