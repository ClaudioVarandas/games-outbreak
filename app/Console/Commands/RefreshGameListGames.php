<?php

namespace App\Console\Commands;

use App\Models\GameList;
use App\Services\GameListRefreshService;
use Illuminate\Console\Command;

class RefreshGameListGames extends Command
{
    protected $signature = 'igdb:gamelist:refresh
                            {game_list_id : The ID of the game list to refresh}
                            {--force : Force refresh even if games were recently synced}';

    protected $description = 'Refresh all games in a game list by fetching latest data from IGDB';

    public function handle(GameListRefreshService $refreshService): int
    {
        $gameListId = $this->argument('game_list_id');
        $force = $this->option('force');

        $gameList = GameList::with('games')->find($gameListId);

        if (! $gameList) {
            $this->error("Game list with ID {$gameListId} not found.");

            return self::FAILURE;
        }

        $this->info("Refreshing games in list: {$gameList->name}");
        $this->info('Total games: '.$gameList->games->count());

        if ($gameList->games->isEmpty()) {
            $this->warn('No games found in this list.');

            return self::SUCCESS;
        }

        $bar = null;

        $refreshService->refreshList($gameList, $force, function (string $event, ?int $total, $game) use (&$bar) {
            if ($event === 'start') {
                $bar = $this->output->createProgressBar($total);
                $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%%  %message%');
                $bar->setMessage('');
                $bar->start();

                return;
            }

            if ($event === 'advance' && $bar) {
                $bar->setMessage((string) ($game->name ?? ''));
                $bar->advance();

                return;
            }

            if ($event === 'finish' && $bar) {
                $bar->finish();
                $this->newLine();
            }
        });

        $this->info('Game list refresh completed!');

        return self::SUCCESS;
    }
}
