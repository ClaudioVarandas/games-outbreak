<?php

declare(strict_types=1);

namespace App\Services\Broadcasts\Formatters;

use App\Enums\PlatformEnum;
use App\Models\Game;
use App\Services\WeeklyChoicesPayload;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class TelegramMessageFormatter
{
    use EscapesMarkdownV2;

    public function format(WeeklyChoicesPayload $payload): string
    {
        if ($payload->isEmpty()) {
            return '';
        }

        $rangeLabel = $this->escape(
            $payload->windowStart->format('M j').' – '.$payload->windowEnd->format('M j'),
        );

        $lines = [
            '*🎮 Games Outbreak — This Week\'s Choices*',
            '_Week of '.$rangeLabel.'_',
            '',
        ];

        foreach ($payload->games as $game) {
            $lines[] = $this->gameLine($game);
        }

        $lines[] = '';
        $lines[] = '[See the full list →]('.$payload->ctaUrl.')';

        return implode("\n", $lines);
    }

    private function gameLine(Game $game): string
    {
        $title = $this->escape($game->name ?? '');
        $url = route('game.show', $game, absolute: true);

        $releaseDate = $this->resolveDate($game->pivot?->release_date ?? $game->first_release_date);
        $dateLabel = $releaseDate
            ? $this->escape($releaseDate->format('M j'))
            : 'TBA';

        $platforms = $this->platformsLabel($game);

        $line = "• [{$title}]({$url}) — {$dateLabel}";

        if ($platforms !== '') {
            $line .= ' · '.$this->escape($platforms);
        }

        return $line;
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

    private function resolveDate(mixed $value): ?CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            return Carbon::parse($value);
        }

        return null;
    }
}
