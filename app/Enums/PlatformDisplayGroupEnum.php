<?php

declare(strict_types=1);

namespace App\Enums;

enum PlatformDisplayGroupEnum: string
{
    case Computer = 'computer';
    case CurrentGen = 'current_gen';
    case Mobile = 'mobile';
    case LastGen = 'last_gen';

    public function label(): string
    {
        return match ($this) {
            self::Computer => 'Computer',
            self::CurrentGen => 'Current Gen',
            self::Mobile => 'Mobile',
            self::LastGen => 'Previous Gen',
        };
    }

    public function order(): int
    {
        return match ($this) {
            self::Computer => 1,
            self::CurrentGen => 2,
            self::Mobile => 3,
            self::LastGen => 4,
        };
    }
}
