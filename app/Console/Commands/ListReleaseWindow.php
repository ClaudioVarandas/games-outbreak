<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\IgdbService;
use App\Support\IgdbCandidate;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ListReleaseWindow extends Command
{
    private const MAX_WINDOW_DAYS = 92;

    protected $signature = 'games:release-window
                            {window : Date window - YYYY-MM month shorthand, or YYYY-MM-DD..YYYY-MM-DD}
                            {--platforms= : Comma-separated IGDB platform ids to filter on}
                            {--limit=200 : Maximum number of candidates in the output}';

    protected $description = 'List IGDB games releasing inside a date window, ranked by hypes (discovery cross-check)';

    public function handle(IgdbService $igdbService): int
    {
        $window = $this->parseWindow((string) $this->argument('window'));

        if ($window === null) {
            $this->error('Invalid window. Use YYYY-MM or YYYY-MM-DD..YYYY-MM-DD.');

            return self::FAILURE;
        }

        [$from, $to] = $window;

        if ($from->diffInDays($to) > self::MAX_WINDOW_DAYS) {
            $this->error(sprintf('Window too large: spans over %d days (max %d).', (int) $from->diffInDays($to), self::MAX_WINDOW_DAYS));

            return self::FAILURE;
        }

        $platformIds = collect(explode(',', (string) $this->option('platforms')))
            ->map(fn (string $id): int => (int) trim($id))
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        $candidates = collect($igdbService->fetchReleaseWindowCandidates($from, $to, $platformIds))
            ->map(fn (array $game): array => IgdbCandidate::format($game, includeHypes: true))
            ->sortByDesc('hypes')
            ->values()
            ->take(max(1, (int) $this->option('limit')));

        $this->line((string) json_encode([
            'window' => ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')],
            'candidates' => $candidates->all(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}|null
     */
    private function parseWindow(string $window): ?array
    {
        if (preg_match('/^(\d{4})-(\d{2})$/', $window, $matches) === 1) {
            $start = Carbon::createFromDate((int) $matches[1], (int) $matches[2], 1)->startOfDay();

            return checkdate((int) $matches[2], 1, (int) $matches[1])
                ? [$start, $start->copy()->endOfMonth()->endOfDay()]
                : null;
        }

        if (preg_match('/^(\d{4}-\d{2}-\d{2})\.\.(\d{4}-\d{2}-\d{2})$/', $window, $matches) === 1) {
            try {
                $from = Carbon::createFromFormat('Y-m-d', $matches[1])->startOfDay();
                $to = Carbon::createFromFormat('Y-m-d', $matches[2])->endOfDay();
            } catch (\Exception) {
                return null;
            }

            return $from->lessThanOrEqualTo($to) ? [$from, $to] : null;
        }

        return null;
    }
}
