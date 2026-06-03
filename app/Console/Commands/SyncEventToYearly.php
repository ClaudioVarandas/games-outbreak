<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\GameList;
use App\Services\EventYearlySyncService;
use Illuminate\Console\Command;

use function Laravel\Prompts\multiselect;

class SyncEventToYearly extends Command
{
    protected $signature = 'events:sync-to-yearly
                            {event : Source events list — slug (e.g. nacon-connect-2026) or numeric id}
                            {--all : Sync every eligible game, skipping the interactive picker}';

    protected $description = 'Copy games (release date, platforms, genres, flags, YouTube trailer) from a system events list into the matching yearly list(s), routed per game by release year.';

    public function handle(EventYearlySyncService $service): int
    {
        $arg = (string) $this->argument('event');

        $event = ctype_digit($arg)
            ? GameList::find((int) $arg)
            : GameList::events()->where('slug', $arg)->first();

        if (! $event) {
            $this->error("No events list found for \"{$arg}\".");

            return self::FAILURE;
        }

        if (! $event->isEvents()) {
            $this->error("List \"{$event->name}\" is a {$event->list_type->value} list, not an events list.");

            return self::FAILURE;
        }

        $event->load('games');
        $plan = $service->plan($event);

        if (empty($plan)) {
            $this->warn('No games in this event list — nothing to sync.');

            return self::SUCCESS;
        }

        $selectedIds = $this->option('all')
            ? array_map(fn ($entry) => $entry['game']->id, $plan)
            : $this->promptForSelection($plan);

        if (empty($selectedIds)) {
            $this->warn('No games selected — nothing to sync.');

            return self::SUCCESS;
        }

        $this->renderSummary($service->apply($event, $selectedIds));

        return self::SUCCESS;
    }

    /**
     * @param  list<array{game: Game, name: string, release_label: string, target_year: int, has_video: bool, action: string, fills: list<string>}>  $plan
     * @return list<int>
     */
    private function promptForSelection(array $plan): array
    {
        $options = [];
        foreach ($plan as $entry) {
            $marker = $entry['has_video'] ? ' 🎬' : '';
            $options[(string) $entry['game']->id] = "{$entry['name']} — {$entry['release_label']} → {$entry['target_year']}{$marker}";
        }

        $selected = multiselect(
            label: 'Select games to sync into the yearly list(s)',
            options: $options,
            default: array_keys($options),
            scroll: 20,
            hint: 'Space to toggle, Enter to confirm.',
        );

        return array_map('intval', $selected);
    }

    /**
     * @param  array{created_years: list<int>, inserted: int, filled: array<int, list<string>>, skipped: int, errors: array<int, string>, per_year: array<int, int>}  $result
     */
    private function renderSummary(array $result): void
    {
        if ($result['created_years']) {
            sort($result['created_years']);
            $this->info('Created yearly list(s): '.implode(', ', $result['created_years']));
        }

        $this->info("Inserted: {$result['inserted']}");
        $this->info('Filled: '.count($result['filled']));
        $this->info("Skipped (already complete): {$result['skipped']}");

        if ($result['per_year']) {
            ksort($result['per_year']);
            $this->line('Per year:');
            foreach ($result['per_year'] as $year => $count) {
                $this->line("  {$year}: {$count}");
            }
        }

        foreach ($result['errors'] as $gameId => $message) {
            $this->error("Game #{$gameId}: {$message}");
        }
    }
}
