<?php

declare(strict_types=1);

namespace App\Enums;

enum PlatformGroupEnum: string
{
    case MULTIPLATFORM = 'multiplatform';
    case PLAYSTATION = 'playstation';
    case NINTENDO = 'nintendo';
    case XBOX = 'xbox';
    case MOBILE = 'mobile';
    case PC = 'pc';

    public function label(): string
    {
        return match ($this) {
            self::MULTIPLATFORM => 'Multiplatform',
            self::PLAYSTATION => 'PlayStation',
            self::NINTENDO => 'Nintendo',
            self::XBOX => 'Xbox',
            self::MOBILE => 'Mobile',
            self::PC => 'PC',
        };
    }

    public function colorClass(): string
    {
        return match ($this) {
            self::MULTIPLATFORM => 'bg-purple-600',
            self::PLAYSTATION => 'bg-blue-600',
            self::NINTENDO => 'bg-red-600',
            self::XBOX => 'bg-green-600',
            self::MOBILE => 'bg-yellow-600',
            self::PC => 'bg-cyan-600',
        };
    }

    /**
     * Get the IGDB platform IDs that belong to this group
     */
    public function platformIds(): array
    {
        return match ($this) {
            self::MULTIPLATFORM => [6, 167, 169, 130, 48, 49, 508], // PC, PS5, Xbox X/S, Switch, PS4, Xbox One, Switch 2
            self::PLAYSTATION => [48, 167], // PS4, PS5
            self::NINTENDO => [130, 508], // Switch, Switch 2
            self::XBOX => [49, 169], // Xbox One, Xbox X/S
            self::MOBILE => [34, 39], // Android, iOS
            self::PC => [6, 3, 14], // PC, Linux, macOS
        };
    }

    /**
     * Auto-suggest a platform group based on game's platform IDs
     */
    public static function suggestFromPlatforms(array $platformIds): self
    {
        $platformIds = array_map('intval', $platformIds);

        $playstation = [48, 167];
        $nintendo = [130, 508];
        $xbox = [49, 169];
        $mobile = [34, 39];
        $pc = [6, 3, 14];

        $hasPlayStation = count(array_intersect($platformIds, $playstation)) > 0;
        $hasNintendo = count(array_intersect($platformIds, $nintendo)) > 0;
        $hasXbox = count(array_intersect($platformIds, $xbox)) > 0;
        $hasMobile = count(array_intersect($platformIds, $mobile)) > 0;
        $hasPc = count(array_intersect($platformIds, $pc)) > 0;

        // Count how many major platform families the game is on
        $familyCount = ($hasPlayStation ? 1 : 0) + ($hasNintendo ? 1 : 0) + ($hasXbox ? 1 : 0) + ($hasPc ? 1 : 0);

        // If on multiple major platforms, it's multiplatform
        if ($familyCount >= 2) {
            return self::MULTIPLATFORM;
        }

        // Single platform family
        if ($hasPlayStation && ! $hasNintendo && ! $hasXbox && ! $hasPc) {
            return self::PLAYSTATION;
        }

        if ($hasNintendo && ! $hasPlayStation && ! $hasXbox && ! $hasPc) {
            return self::NINTENDO;
        }

        if ($hasXbox && ! $hasPlayStation && ! $hasNintendo && ! $hasPc) {
            return self::XBOX;
        }

        if ($hasPc && ! $hasPlayStation && ! $hasNintendo && ! $hasXbox) {
            return self::PC;
        }

        // Mobile only
        if ($hasMobile && ! $hasPlayStation && ! $hasNintendo && ! $hasXbox && ! $hasPc) {
            return self::MOBILE;
        }

        // Default to multiplatform
        return self::MULTIPLATFORM;
    }

    /**
     * Get display order for tabs
     */
    public function order(): int
    {
        return match ($this) {
            self::MULTIPLATFORM => 1,
            self::PLAYSTATION => 2,
            self::NINTENDO => 3,
            self::XBOX => 4,
            self::PC => 5,
            self::MOBILE => 6,
        };
    }

    /**
     * Get all cases ordered for display
     */
    public static function orderedCases(): array
    {
        $cases = self::cases();
        usort($cases, fn ($a, $b) => $a->order() <=> $b->order());

        return $cases;
    }
}
