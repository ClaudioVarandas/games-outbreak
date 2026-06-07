<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\GameList;
use App\Services\EventImportService;
use Carbon\Carbon;
use Illuminate\Console\Command;

use function Laravel\Prompts\select;

class ImportIgdbEvent extends Command
{
    protected $signature = 'igdb:events:import
                            {event : IGDB numeric event id or a search term (event name)}
                            {--update : Non-interactive: update the matching list (fail if none exists)}
                            {--create : Non-interactive: create a new list (fail if one already exists)}
                            {--no-games : Import the event metadata only, skip the game sync}
                            {--accept-all : Non-interactive: auto-pick the single best match and accept prompts}
                            {--public= : Override is_public (yes/no)}';

    protected $description = 'Import an IGDB event (metadata + games) into an events list. Re-runnable: existing lists are updated, only newly-appeared games are added.';

    public function handle(EventImportService $service): int
    {
        $event = $this->resolveEvent($service, (string) $this->argument('event'));

        if ($event === null) {
            return self::FAILURE;
        }

        $existing = $service->findExistingList($event);

        $action = $this->decideAction($event, $existing);

        if ($action === 'abort') {
            return self::FAILURE;
        }

        if ($action === 'noop') {
            $this->info('Nothing to do.');

            return self::SUCCESS;
        }

        $list = $service->createOrUpdateList($event, $existing, $this->overrides());

        $this->info(sprintf(
            '%s events list "%s" (id %d, slug %s, igdb_event_id %d).',
            $existing ? 'Updated' : 'Created',
            $list->name,
            $list->id,
            $list->slug,
            $list->igdb_event_id,
        ));

        if ($this->option('no-games')) {
            return self::SUCCESS;
        }

        $report = $service->syncGames($list, $event);

        $this->info(sprintf(
            'Games: added %d, skipped %d, failed %d. Trailers set: %d.',
            $report['added'],
            $report['skipped'],
            $report['failed'],
            $report['videos_set'],
        ));

        foreach ($report['errors'] as $igdbId => $message) {
            $this->warn("  IGDB game #{$igdbId}: {$message}");
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveEvent(EventImportService $service, string $arg): ?array
    {
        if (ctype_digit($arg)) {
            $event = $service->fetchEvent((int) $arg);

            if ($event === null) {
                $this->error("No IGDB event found with id {$arg}.");
            }

            return $event;
        }

        $matches = $service->searchEvents($arg);

        if ($matches === []) {
            $this->error("No IGDB events match \"{$arg}\".");

            return null;
        }

        $chosenId = (count($matches) === 1 || $this->option('accept-all'))
            ? (int) $matches[0]['id']
            : (int) select(
                label: 'Pick the IGDB event',
                options: $this->eventOptions($matches),
                scroll: 15,
            );

        return $service->fetchEvent($chosenId);
    }

    /**
     * @param  list<array<string, mixed>>  $matches
     * @return array<string, string>
     */
    private function eventOptions(array $matches): array
    {
        $options = [];

        foreach ($matches as $match) {
            $date = isset($match['start_time'])
                ? Carbon::createFromTimestamp($match['start_time'])->format('Y-m-d')
                : 'TBA';
            $options[(string) $match['id']] = ($match['name'] ?? 'Untitled Event')." ({$date})";
        }

        return $options;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function decideAction(array $event, ?GameList $existing): string
    {
        if ($this->option('create')) {
            if ($existing) {
                $this->error("An events list for this IGDB event already exists (id {$existing->id}).");

                return 'abort';
            }

            return 'create';
        }

        if ($this->option('update')) {
            if (! $existing) {
                $this->error('No existing events list to update for this IGDB event.');

                return 'abort';
            }

            return 'update';
        }

        if ($this->option('accept-all')) {
            return $existing ? 'update' : 'create';
        }

        if ($existing) {
            return $this->confirm("Events list \"{$existing->name}\" already exists. Update it?", true)
                ? 'update'
                : 'noop';
        }

        $name = $event['name'] ?? 'Untitled Event';

        return $this->confirm("Create new events list for \"{$name}\"?", true)
            ? 'create'
            : 'noop';
    }

    /**
     * @return array<string, mixed>
     */
    private function overrides(): array
    {
        $public = $this->option('public');

        if ($public === null) {
            return [];
        }

        return ['is_public' => in_array(strtolower(trim($public)), ['1', 'true', 'yes', 'y', 'on'], true)];
    }
}
