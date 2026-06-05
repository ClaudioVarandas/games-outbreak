<?php

declare(strict_types=1);

namespace App\Enums;

use Illuminate\Support\Collection;

enum PlatformEnum: int
{
    // IGDB ID's
    case LINUX = 3; //
    case MACOS = 14; //
    case PC = 6;
    case PS5 = 167;
    case XBOX_SX = 169;
    case SWITCH = 130;
    case PS4 = 48;
    case XBOX_ONE = 49;
    case SWITCH2 = 508;
    case ANDROID = 34; //
    case IOS = 39; //

    // Add more as needed

    public function label(): string
    {
        return match ($this) {
            self::PC => 'PC',
            self::PS5 => 'PS5',
            self::XBOX_SX => 'Xbox X/S',
            self::SWITCH => 'Switch',
            self::PS4 => 'PS4',
            self::XBOX_ONE => 'Xbox One',
            self::SWITCH2 => 'Switch 2',
            self::LINUX => 'Linux',
            self::MACOS => 'macOS',
            self::ANDROID => 'Android',
            self::IOS => 'iOS',
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
            self::LINUX => 'gray',
            self::MACOS => 'gray',
            self::ANDROID => 'green',
            self::IOS => 'gray',
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

    public function group(): PlatformDisplayGroupEnum
    {
        return match ($this) {
            self::PC, self::LINUX, self::MACOS => PlatformDisplayGroupEnum::Computer,
            self::PS5, self::XBOX_SX, self::SWITCH2 => PlatformDisplayGroupEnum::CurrentGen,
            self::ANDROID, self::IOS => PlatformDisplayGroupEnum::Mobile,
            self::PS4, self::XBOX_ONE, self::SWITCH => PlatformDisplayGroupEnum::LastGen,
        };
    }

    public function isDefaultSelection(): bool
    {
        return match ($this) {
            self::PC, self::PS5, self::XBOX_SX, self::SWITCH2 => true,
            default => false,
        };
    }

    // Optional: get by IGDB ID
    public static function fromIgdbId(int $id): ?self
    {
        return collect(self::cases())->firstWhere('value', $id);
    }

    /**
     * Active platforms for the game form picker, ordered by display group then priority.
     * Each entry is tagged with its display group and whether it is a default selection.
     *
     * @return Collection<int, array{id: int, label: string, color: string, group: string, default: bool}>
     */
    public static function displayList(): Collection
    {
        return self::getActivePlatforms()
            ->sortBy(fn (self $enum): int => $enum->group()->order() * 100 + self::getPriority($enum->value))
            ->map(fn (self $enum): array => [
                'id' => $enum->value,
                'label' => $enum->label(),
                'color' => $enum->color(),
                'group' => $enum->group()->value,
                'default' => $enum->isDefaultSelection(),
            ])
            ->values();
    }

    /**
     * Get only active platforms for frontend display
     * Reads from config/platforms.php to determine which platforms are active
     */
    public static function getActivePlatforms(): Collection
    {
        $activeIds = config('platforms.active', []);

        return collect(self::cases())
            ->filter(fn ($enum) => in_array($enum->value, $activeIds))
            ->keyBy(fn ($enum) => $enum->value);
    }

    public static function getActivePlatformsValues(): array
    {
        return self::getActivePlatforms()->pluck('value')->values()->all();
    }

    /**
     * Get platform priority for sorting (lower number = higher priority)
     * Reads from config/platforms.php priority array
     */
    public static function getPriority(int $igdbId): int
    {
        $priorities = config('platforms.priority', []);

        return $priorities[$igdbId] ?? 999;
    }
}
