<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\GameListSyncService;
use Illuminate\Console\Command;

class CreateSystemList extends Command
{
    protected $signature = 'system-list:create {type : Type of list (yearly, seasoned, events)} {year : Year to create the list for}';

    protected $description = 'Create a yearly system list. Only one yearly list is allowed per year.';

    public function handle(GameListSyncService $sync): int
    {
        $type = $this->argument('type');
        $year = (int) $this->argument('year');

        if ($year < 2000 || $year > 2100) {
            $this->error('Invalid year. Please enter a year between 2000 and 2100.');

            return Command::FAILURE;
        }

        return match ($type) {
            'yearly' => $this->createYearlyList($year, $sync),
            default => $this->invalidType($type),
        };
    }

    private function createYearlyList(int $year, GameListSyncService $sync): int
    {
        $this->info("Creating yearly list for year: {$year}");

        if ($existing = $sync->findYearlyList($year)) {
            $this->error("A yearly list already exists for {$year}: '{$existing->name}' (ID: {$existing->id})");

            return Command::FAILURE;
        }

        $gameList = $sync->firstOrCreateYearlyList($year);

        $this->info("Created: {$gameList->name} (ID: {$gameList->id}, Slug: {$gameList->slug})");
        $this->line("  Start: {$gameList->start_at->format('Y-m-d')} | End: {$gameList->end_at->format('Y-m-d')}");

        return Command::SUCCESS;
    }

    private function invalidType(string $type): int
    {
        $this->error("Invalid type '{$type}'. Valid types are: yearly");

        return Command::FAILURE;
    }
}
