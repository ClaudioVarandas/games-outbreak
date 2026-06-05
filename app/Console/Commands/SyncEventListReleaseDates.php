<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\GameList;
use App\Services\EventReleaseDateSuggester;
use App\Support\ReleaseDateSuggestion;
use Carbon\Carbon;
use Illuminate\Console\Command;

use function Laravel\Prompts\select;
use function Laravel\Prompts\table;

class SyncEventListReleaseDates extends Command
{
    protected $signature = 'igdb:gamelist:events:sync-dates
                            {game_list_id : Events list — numeric id or slug}
                            {--accept-all : Apply every suggested change without prompting}';

    protected $description = 'Sync each event-list game pivot (release_date / is_tba / release_year) from its IGDB-synced release dates, by confirmation.';

    public function handle(EventReleaseDateSuggester $suggester): int
    {
        $arg = (string) $this->argument('game_list_id');

        $list = ctype_digit($arg)
            ? GameList::find((int) $arg)
            : GameList::where('slug', $arg)->first();

        if (! $list) {
            $this->error("Game list \"{$arg}\" not found.");

            return self::FAILURE;
        }

        if (! $list->isEvents()) {
            $this->error("List \"{$list->name}\" is a {$list->list_type->value} list, not an events list.");

            return self::FAILURE;
        }

        $list->load('games.releaseDates');

        if ($list->games->isEmpty()) {
            $this->warn('No games in this list.');

            return self::SUCCESS;
        }

        $acceptAll = (bool) $this->option('accept-all');
        $applied = 0;
        $skipped = 0;

        foreach ($list->games as $game) {
            $suggestion = $suggester->suggest($game);

            if (! $this->differs($game, $suggestion)) {
                $skipped++;

                continue;
            }

            if ($game->pivot->is_early_access && $suggestion->isTba) {
                $this->warn("Skipping \"{$game->name}\" — Early Access cannot be TBA.");
                $skipped++;

                continue;
            }

            if (! $acceptAll) {
                table(['Field', 'Current', 'Suggested (IGDB)'], $this->diffRows($game, $suggestion));

                $choice = select(
                    label: "Update \"{$game->name}\"?",
                    options: ['yes' => 'Yes', 'no' => 'No', 'all' => 'Yes to all', 'quit' => 'Quit'],
                    default: 'yes',
                );

                if ($choice === 'quit') {
                    break;
                }

                if ($choice === 'no') {
                    $skipped++;

                    continue;
                }

                if ($choice === 'all') {
                    $acceptAll = true;
                }
            }

            $list->games()->updateExistingPivot($game->id, $suggestion->toPivot());
            $applied++;
            $this->line("✓ {$game->name} → {$suggestion->label()}");
        }

        $this->info("Done. Updated {$applied}, skipped {$skipped}.");

        return self::SUCCESS;
    }

    private function differs(Game $game, ReleaseDateSuggestion $suggestion): bool
    {
        $pivot = $game->pivot;
        $currentTba = (bool) $pivot->is_tba;
        $currentYear = $pivot->release_year !== null ? (int) $pivot->release_year : null;
        $currentDate = $pivot->release_date ? Carbon::parse($pivot->release_date)->toDateString() : null;

        if ($suggestion->isTba) {
            return ! $currentTba
                || $currentYear !== $suggestion->releaseYear
                || $currentDate !== null;
        }

        return $currentTba
            || $currentDate !== $suggestion->releaseDate?->toDateString()
            || $currentYear !== null;
    }

    /**
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private function diffRows(Game $game, ReleaseDateSuggestion $suggestion): array
    {
        $pivot = $game->pivot;

        $current = $pivot->is_tba
            ? 'TBA'.($pivot->release_year ? ' '.$pivot->release_year : '')
            : ($pivot->release_date ? Carbon::parse($pivot->release_date)->format('M j, Y') : '—');

        return [['Release', $current, $suggestion->label()]];
    }
}
