<?php

declare(strict_types=1);

namespace App\Services\Broadcasts\Formatters;

use App\Enums\PlatformEnum;
use App\Models\Game;
use App\Services\WeeklyChoicesPayload;

class XTweetFormatter
{
    private const int MAX_LENGTH = 280;

    /** X shortens any URL to a 23-char t.co link regardless of input length. */
    private const int SHORTENED_URL_LENGTH = 23;

    public function format(WeeklyChoicesPayload $payload): string
    {
        if ($payload->isEmpty()) {
            return '';
        }

        $header = "🎮 This week on Games Outbreak\n";
        $count = $payload->count();
        $intro = "{$count} curated releases:\n";
        $footer = "\nFull list → ".$payload->ctaUrl;

        $fixedLength = mb_strlen($header) + mb_strlen($intro) + mb_strlen($footer) - mb_strlen($payload->ctaUrl) + self::SHORTENED_URL_LENGTH;
        $budget = self::MAX_LENGTH - $fixedLength;

        $lines = [];
        $shown = 0;
        foreach ($payload->games as $game) {
            $line = $this->gameLine($game);
            $lineLength = mb_strlen($line) + 1;

            if ($shown > 0 && $lineLength > $budget) {
                break;
            }

            $lines[] = $line;
            $budget -= $lineLength;
            $shown++;
        }

        $remaining = $count - $shown;
        if ($remaining > 0) {
            $moreLine = "+ {$remaining} more";
            $moreLength = mb_strlen($moreLine) + 1;

            while ($lines !== [] && $moreLength > $budget) {
                $dropped = array_pop($lines);
                $budget += mb_strlen($dropped) + 1;
                $remaining++;
                $moreLine = "+ {$remaining} more";
                $moreLength = mb_strlen($moreLine) + 1;
            }

            $lines[] = $moreLine;
        }

        return $header.$intro.implode("\n", $lines).$footer;
    }

    private function gameLine(Game $game): string
    {
        $title = $game->name ?? '';
        $platforms = $this->platformsLabel($game);

        return $platforms === ''
            ? "• {$title}"
            : "• {$title} — {$platforms}";
    }

    private function platformsLabel(Game $game): string
    {
        $ids = $this->platformIds($game);

        if ($ids === []) {
            return '';
        }

        return collect($ids)
            ->map(fn (int $id) => PlatformEnum::tryFrom($id)?->label())
            ->filter()
            ->unique()
            ->take(3)
            ->implode('/');
    }

    /**
     * @return list<int>
     */
    private function platformIds(Game $game): array
    {
        $pivotPlatforms = $game->pivot?->platforms;

        if (is_string($pivotPlatforms)) {
            $decoded = json_decode($pivotPlatforms, true);
            if (is_array($decoded) && $decoded !== []) {
                return array_map('intval', $decoded);
            }
        }

        if (is_array($pivotPlatforms) && $pivotPlatforms !== []) {
            return array_map('intval', $pivotPlatforms);
        }

        return $game->platforms
            ->pluck('igdb_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
