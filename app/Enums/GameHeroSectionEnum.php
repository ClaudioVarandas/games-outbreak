<?php

declare(strict_types=1);

namespace App\Enums;

enum GameHeroSectionEnum: string
{
    case About = 'about';
    case Scores = 'scores';
    case Screenshots = 'screenshots';
    case ReleaseDates = 'release-dates';
    case SimilarGames = 'similar-games';

    public function label(): string
    {
        return match ($this) {
            self::About => 'About',
            self::Scores => 'Scores',
            self::Screenshots => 'Screenshots',
            self::ReleaseDates => 'Release Dates',
            self::SimilarGames => 'Similar Games',
        };
    }
}
