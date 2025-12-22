<?php
declare(strict_types=1);

namespace App\Enums;

enum PlatformEnum: int
{
    // IGDB ID's
    case PC = 6;
    case PS5 = 167;
    case XBOX_SX = 169;
    case SWITCH = 130;
    case PS4 = 48;
    case XBOX_ONE = 49;
    case SWITCH2 = 508;

    // Add more as needed

    public function label(): string
    {
        return match ($this) {
            self::PC => 'PC',
            self::PS5 => 'PS5',
            self::XBOX_SX => 'Xbox',
            self::SWITCH => 'Switch',
            self::PS4 => 'PS4',
            self::XBOX_ONE => 'Xbox One',
            self::SWITCH2 => 'Switch 2',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PC => 'gray',
            self::PS5 => 'blue',
            self::XBOX_SX => 'green',
            self::SWITCH => 'red',
            self::PS4 => 'blue',
            self::XBOX_ONE => 'green',
            self::SWITCH2 => 'red',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::PC => '🖥️',
            self::PS5, self::PS4 => '🎮',
            self::XBOX_SX, self::XBOX_ONE => '🎮',
            self::SWITCH, self::SWITCH2 => '🎮',
            default => '🎮',
        };
    }

    // Optional: get by IGDB ID
    public static function fromIgdbId(int $id): ?self
    {
        return collect(self::cases())->firstWhere('value', $id);
    }
}
