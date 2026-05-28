<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\Game;

enum GameHeroSectionEnum: string
{
    case Scores = 'scores';
    case Screenshots = 'screenshots';
    case ReleaseDates = 'release-dates';
    case SimilarGames = 'similar-games';

    public function label(): string
    {
        return match ($this) {
            self::Scores => 'Scores',
            self::Screenshots => 'Screenshots',
            self::ReleaseDates => 'Release Dates',
            self::SimilarGames => 'Similar Games',
        };
    }

    public function isVisibleFor(Game $game): bool
    {
        return match ($this) {
            self::Scores => (bool) ($game->metacritic_score || $game->steam_review_percent !== null || $game->igdb_aggregated_rating),
            self::Screenshots => is_array($game->screenshots) && count($game->screenshots) > 0,
            self::ReleaseDates, self::SimilarGames => true,
        };
    }
}
