<?php

namespace App\Enums;

use App\Models\User;

enum NewsVisibilityEnum: string
{
    case Public = 'public';
    case AdminOnly = 'admin';
    case Disabled = 'disabled';

    public static function current(): self
    {
        return self::tryFrom((string) GlobalFeatureEnum::News->value()) ?? self::Disabled;
    }

    public function allows(?User $user): bool
    {
        return match ($this) {
            self::Public => true,
            self::AdminOnly => $user?->isAdmin() ?? false,
            self::Disabled => false,
        };
    }
}
