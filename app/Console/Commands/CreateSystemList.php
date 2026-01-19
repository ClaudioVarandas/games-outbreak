<?php

namespace App\Console\Commands;

use App\Enums\ListTypeEnum;
use App\Models\GameList;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateSystemList extends Command
{
    protected $signature = 'system-list:create {type : Type of list (indie, highlights)} {year : Year to create the list for}';

    protected $description = 'Create a yearly system list (indie or highlights). Only one list per type is allowed per year.';

    public function handle(): int
    {
        $type = $this->argument('type');
        $year = (int) $this->argument('year');

        if ($year < 2000 || $year > 2100) {
            $this->error('Invalid year. Please enter a year between 2000 and 2100.');

            return Command::FAILURE;
        }

        return match ($type) {
            'indie' => $this->createIndieList($year),
            'highlights' => $this->createHighlightsList($year),
            default => $this->invalidType($type),
        };
    }

    private function createIndieList(int $year): int
    {
        $this->info("Creating indie list for year: {$year}");

        $startOfYear = Carbon::create($year, 1, 1)->startOfDay();
        $endOfYear = Carbon::create($year, 12, 31)->endOfDay();

        $existingList = GameList::where('list_type', ListTypeEnum::INDIE_GAMES->value)
            ->where('is_system', true)
            ->whereBetween('start_at', [$startOfYear, $endOfYear])
            ->first();

        if ($existingList) {
            $this->error("An indie list already exists for {$year}: '{$existingList->name}' (ID: {$existingList->id})");

            return Command::FAILURE;
        }

        $listName = "Indies {$year}";
        $slug = $this->generateUniqueSlug($listName, ListTypeEnum::INDIE_GAMES);

        $gameList = GameList::create([
            'user_id' => 1,
            'name' => $listName,
            'description' => "Indie game picks from {$year}",
            'slug' => $slug,
            'is_public' => true,
            'is_system' => true,
            'is_active' => true,
            'list_type' => ListTypeEnum::INDIE_GAMES->value,
            'start_at' => $startOfYear,
            'end_at' => $endOfYear,
        ]);

        $this->info("Created: {$listName} (ID: {$gameList->id}, Slug: {$slug})");
        $this->line("  Start: {$startOfYear->format('Y-m-d')} | End: {$endOfYear->format('Y-m-d')}");

        return Command::SUCCESS;
    }

    private function createHighlightsList(int $year): int
    {
        $this->info("Creating highlights list for year: {$year}");

        $startOfYear = Carbon::create($year, 1, 1)->startOfDay();
        $endOfYear = Carbon::create($year, 12, 31)->endOfDay();

        $existingList = GameList::where('list_type', ListTypeEnum::HIGHLIGHTS->value)
            ->where('is_system', true)
            ->whereBetween('start_at', [$startOfYear, $endOfYear])
            ->first();

        if ($existingList) {
            $this->error("A highlights list already exists for {$year}: '{$existingList->name}' (ID: {$existingList->id})");

            return Command::FAILURE;
        }

        $listName = "Highlights {$year}";
        $slug = $this->generateUniqueSlug($listName, ListTypeEnum::HIGHLIGHTS);

        $gameList = GameList::create([
            'user_id' => 1,
            'name' => $listName,
            'description' => "Top game picks from {$year}",
            'slug' => $slug,
            'is_public' => true,
            'is_system' => true,
            'is_active' => true,
            'list_type' => ListTypeEnum::HIGHLIGHTS->value,
            'start_at' => $startOfYear,
            'end_at' => $endOfYear,
        ]);

        $this->info("Created: {$listName} (ID: {$gameList->id}, Slug: {$slug})");
        $this->line("  Start: {$startOfYear->format('Y-m-d')} | End: {$endOfYear->format('Y-m-d')}");

        return Command::SUCCESS;
    }

    private function invalidType(string $type): int
    {
        $this->error("Invalid type '{$type}'. Valid types are: indie, highlights");

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
