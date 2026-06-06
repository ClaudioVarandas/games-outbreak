<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\GameList;
use App\Services\GameListPivotSuggester;
use App\Support\PivotChange;
use Illuminate\Console\Command;

use function Laravel\Prompts\multiselect;

class SyncGameListPivot extends Command
{
    protected $signature = 'igdb:gamelist:sync-pivot
                            {game_list_id : Game list — numeric id or slug}
                            {--accept-all : Apply every suggested change without prompting}';

    protected $description = 'Review and apply IGDB-derived pivot changes (release / early access / platforms / genres) for the games in a list, picked from a single checklist.';

    public function handle(GameListPivotSuggester $suggester): int
    {
        $arg = (string) $this->argument('game_list_id');

        $list = ctype_digit($arg)
            ? GameList::find((int) $arg)
            : GameList::where('slug', $arg)->first();

        if (! $list) {
            $this->error("Game list \"{$arg}\" not found.");

            return self::FAILURE;
        }

        $list->load(['games.releaseDates.status', 'games.platforms', 'games.genres']);

        if ($list->games->isEmpty()) {
            $this->warn('No games in this list.');

            return self::SUCCESS;
        }

        /** @var list<array{key: string, game: Game, change: PivotChange}> $rows */
        $rows = [];
        foreach ($list->games as $game) {
            foreach ($suggester->changesFor($game) as $change) {
                $rows[] = ['key' => 'c'.count($rows), 'game' => $game, 'change' => $change];
            }
        }

        if ($rows === []) {
            $this->info('Every pivot already matches IGDB — nothing to sync.');

            return self::SUCCESS;
        }

        $selectedKeys = $this->option('accept-all')
            ? array_column($rows, 'key')
            : multiselect(
                label: 'Check the changes to apply',
                options: $this->columnarOptions($rows),
                default: [],
                scroll: 20,
                hint: 'Space to toggle, Enter to confirm. Nothing checked = no changes.',
            );

        if (empty($selectedKeys)) {
            $this->info('No changes selected — nothing applied.');

            return self::SUCCESS;
        }

        $applied = $this->apply($list, $rows, array_map('strval', $selectedKeys));

        $this->info("Applied {$applied} change(s) across the list.");

        return self::SUCCESS;
    }

    /**
     * Column-aligned checklist labels so the multiselect reads like a table:
     * "Game        Field       Current → Suggested". Keys stay non-numeric so
     * the options array is not treated as a list by Laravel Prompts.
     *
     * @param  list<array{key: string, game: Game, change: PivotChange}>  $rows
     * @return array<string, string>
     */
    private function columnarOptions(array $rows): array
    {
        $gameWidth = max(array_map(fn (array $row) => mb_strlen($row['game']->name), $rows));
        $fieldWidth = max(array_map(fn (array $row) => mb_strlen($row['change']->label), $rows));
        $currentWidth = max(array_map(fn (array $row) => mb_strlen($row['change']->current), $rows));

        $options = [];
        foreach ($rows as $row) {
            $options[$row['key']] = sprintf(
                '%s   %s   %s → %s',
                str_pad($row['game']->name, $gameWidth),
                str_pad($row['change']->label, $fieldWidth),
                str_pad($row['change']->current, $currentWidth),
                $row['change']->suggested,
            );
        }

        return $options;
    }

    /**
     * @param  list<array{key: string, game: Game, change: PivotChange}>  $rows
     * @param  list<string>  $selectedKeys
     */
    private function apply(GameList $list, array $rows, array $selectedKeys): int
    {
        $selected = array_filter($rows, fn (array $row) => in_array($row['key'], $selectedKeys, true));

        $byGame = [];
        foreach ($selected as $row) {
            $byGame[$row['game']->id]['game'] = $row['game'];
            $byGame[$row['game']->id]['payload'][] = $row['change']->pivot;
        }

        $applied = 0;
        foreach ($byGame as $entry) {
            $game = $entry['game'];
            $merged = array_merge(...$entry['payload']);

            $payload = $this->guardEarlyAccess($game, $merged);
            $droppedEarlyAccess = array_key_exists('is_early_access', $merged)
                && ! array_key_exists('is_early_access', $payload);

            if ($payload === []) {
                continue;
            }

            $list->games()->updateExistingPivot($game->id, $payload);
            $applied += count($entry['payload']) - ($droppedEarlyAccess ? 1 : 0);
        }

        return $applied;
    }

    /**
     * Early Access requires a concrete, non-TBA release date. Drop the flag if
     * the resulting pivot would not have one.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function guardEarlyAccess(Game $game, array $payload): array
    {
        if (($payload['is_early_access'] ?? false) !== true) {
            return $payload;
        }

        $finalDate = array_key_exists('release_date', $payload) ? $payload['release_date'] : $game->pivot->release_date;
        $finalTba = array_key_exists('is_tba', $payload) ? $payload['is_tba'] : (bool) $game->pivot->is_tba;

        if (! $finalDate || $finalTba) {
            unset($payload['is_early_access']);
            $this->warn("Skipped Early Access for \"{$game->name}\" — needs a concrete release date.");
        }

        return $payload;
    }
}
