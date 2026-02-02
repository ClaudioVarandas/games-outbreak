<?php

namespace App\Console\Commands;

use App\Enums\ListTypeEnum;
use App\Models\GameList;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateSystemList extends Command
{
    protected $signature = 'system-list:create {type : Type of list (yearly, seasoned, events)} {year : Year to create the list for}';

    protected $description = 'Create a yearly system list. Only one yearly list is allowed per year.';

    public function handle(): int
    {
        $type = $this->argument('type');
        $year = (int) $this->argument('year');

        if ($year < 2000 || $year > 2100) {
            $this->error('Invalid year. Please enter a year between 2000 and 2100.');

            return Command::FAILURE;
        }

        return match ($type) {
            'yearly' => $this->createYearlyList($year),
            default => $this->invalidType($type),
        };
    }

    private function createYearlyList(int $year): int
    {
        $this->info("Creating yearly list for year: {$year}");

        $startOfYear = Carbon::create($year, 1, 1)->startOfDay();
        $endOfYear = Carbon::create($year, 12, 31)->endOfDay();

        $existingList = GameList::where('list_type', ListTypeEnum::YEARLY->value)
            ->where('is_system', true)
            ->whereBetween('start_at', [$startOfYear, $endOfYear])
            ->first();

        if ($existingList) {
            $this->error("A yearly list already exists for {$year}: '{$existingList->name}' (ID: {$existingList->id})");

            return Command::FAILURE;
        }

        $listName = "Game Releases {$year}";
        $slug = $this->generateUniqueSlug($listName, ListTypeEnum::YEARLY);

        $gameList = GameList::create([
            'user_id' => 1,
            'name' => $listName,
            'description' => "Curated game releases for {$year}",
            'slug' => $slug,
            'is_public' => true,
            'is_system' => true,
            'is_active' => true,
            'list_type' => ListTypeEnum::YEARLY->value,
            'start_at' => $startOfYear,
            'end_at' => $endOfYear,
        ]);

        $this->info("Created: {$listName} (ID: {$gameList->id}, Slug: {$slug})");
        $this->line("  Start: {$startOfYear->format('Y-m-d')} | End: {$endOfYear->format('Y-m-d')}");

        return Command::SUCCESS;
    }

    private function invalidType(string $type): int
    {
        $this->error("Invalid type '{$type}'. Valid types are: yearly");

        return Command::FAILURE;
    }

    private function generateUniqueSlug(string $name, ListTypeEnum $listType): string
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $counter = 1;

        while (GameList::where('slug', $slug)->where('list_type', $listType->value)->exists()) {
            $slug = $originalSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
