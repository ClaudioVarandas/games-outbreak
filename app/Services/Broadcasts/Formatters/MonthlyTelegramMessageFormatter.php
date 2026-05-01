<?php

declare(strict_types=1);

namespace App\Services\Broadcasts\Formatters;

use App\Enums\PlatformEnum;
use App\Models\Game;
use App\Services\MonthlyChoicesPayload;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class MonthlyTelegramMessageFormatter
{
    use EscapesMarkdownV2;

    /**
     * Telegram sendMessage hard limit is 4096 chars; reserve headroom for
     * MarkdownV2 escapes, parse_mode round-trips, and minor accounting drift.
     */
    public const MAX_CHARS_PER_MESSAGE = 3800;

    public function format(MonthlyChoicesPayload $payload): string
    {
        $messages = $this->formatMessages($payload);

        if ($messages === []) {
            return '';
        }

        return implode("\n\n— — — — —\n\n", $messages);
    }

    /**
     * @return list<string>
     */
    public function formatMessages(MonthlyChoicesPayload $payload, int $maxChars = self::MAX_CHARS_PER_MESSAGE): array
    {
        if ($payload->isEmpty()) {
            return [];
        }

        $headerTitle = $this->headerTitle($payload);
        $monthLabel = $this->escape($payload->windowStart->format('F Y'));
        $footerLine = '[See the full list →]('.$payload->ctaUrl.')';

        $gameLines = [];
        foreach ($payload->games as $game) {
            $gameLines[] = $this->gameLine($game);
        }

        $bins = $this->binPackLines($gameLines, $headerTitle, $monthLabel, $footerLine, $maxChars);
        $totalParts = count($bins);

        $messages = [];
        foreach ($bins as $i => $lines) {
            $partsLabel = $totalParts > 1 ? ' · Part '.($i + 1).'/'.$totalParts : '';

            $msgLines = [
                $headerTitle,
                '_'.$monthLabel.$this->escape($partsLabel).'_',
                '',
                ...$lines,
            ];

            if ($i === $totalParts - 1) {
                $msgLines[] = '';
                $msgLines[] = $footerLine;
            }

            $messages[] = implode("\n", $msgLines);
        }

        return $messages;
    }

    private function headerTitle(MonthlyChoicesPayload $payload): string
    {
        $base = $payload->isCurrent
            ? 'This Month\'s Choices'
            : 'Next Month\'s Choices';

        return $payload->isPreview
            ? '*🎮 Games Outbreak — PREVIEW — '.$base.'*'
            : '*🎮 Games Outbreak — '.$base.'*';
    }

    /**
     * @param  list<string>  $gameLines
     * @return list<list<string>>
     */
    private function binPackLines(array $gameLines, string $headerTitle, string $monthLabel, string $footerLine, int $maxChars): array
    {
        $bins = [];
        $current = [];
        $currentLen = 0;
        $headerLen = $this->headerWeight($headerTitle, $monthLabel);
        $footerLen = strlen($footerLine) + 2;

        foreach ($gameLines as $line) {
            $lineWeight = strlen($line) + 1;

            if ($current !== [] && $headerLen + $currentLen + $lineWeight + $footerLen > $maxChars) {
                $bins[] = $current;
                $current = [];
                $currentLen = 0;
            }

            $current[] = $line;
            $currentLen += $lineWeight;
        }

        if ($current !== []) {
            $bins[] = $current;
        }

        return $bins;
    }

    private function headerWeight(string $headerTitle, string $monthLabel): int
    {
        return strlen($headerTitle) + strlen($monthLabel) + 16;
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
