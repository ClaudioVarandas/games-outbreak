<?php

namespace App\Console\Commands;

use App\Enums\ListTypeEnum;
use App\Models\GameList;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateYearlyHighlightsList extends Command
{
    protected $signature = 'highlights:create-yearly {--year= : Year to create highlights list for (defaults to current year)}';

    protected $description = 'Create a yearly highlights list. Only one highlights list is allowed per year.';

    public function handle(): int
    {
        $year = $this->option('year') ?? date('Y');
        $year = (int) $year;

        if ($year < 2000 || $year > 2100) {
            $this->error('Invalid year. Please enter a year between 2000 and 2100.');

            return Command::FAILURE;
        }

        $this->info("Creating highlights list for year: {$year}");

        // Check if a highlights list already exists for this year
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
        $slug = $this->generateUniqueSlug($listName);

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

    private function generateUniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $counter = 1;

        while (GameList::where('slug', $slug)->where('list_type', ListTypeEnum::HIGHLIGHTS->value)->exists()) {
            $slug = $originalSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
