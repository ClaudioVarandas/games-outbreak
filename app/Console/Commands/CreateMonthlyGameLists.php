<?php

namespace App\Console\Commands;

use App\Models\GameList;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateMonthlyGameLists extends Command
{
    protected $signature = 'games:lists:create-monthly {--year= : Year to create lists for (defaults to current year)} {--type=monthly : List type (monthly or indie)}';

    protected $description = 'Create monthly game lists for a given year. Creates 12 lists (one for each month) with system, public, and active flags set.';

    public function handle(): int
    {
        $year = $this->option('year');
        $type = $this->option('type');

        if (!$year) {
            $year = date('Y');
        }

        $year = (int) $year;

        if ($year < 2000 || $year > 2100) {
            $this->error('Invalid year. Please enter a year between 2000 and 2100.');
            return Command::FAILURE;
        }

        // Validate and map type option
        $listType = match (strtolower($type)) {
            'monthly' => \App\Enums\ListTypeEnum::MONTHLY,
            'indie', 'indie-games' => \App\Enums\ListTypeEnum::INDIE_GAMES,
            default => null,
        };

        if ($listType === null) {
            $this->error("Invalid type. Please use 'monthly' or 'indie'.");
            return Command::FAILURE;
        }

        $typeLabel = $listType->label();
        $this->info("Creating {$typeLabel} game lists for year: {$year}");
        $this->newLine();

        $createdCount = 0;
        $skippedCount = 0;

        for ($month = 1; $month <= 12; $month++) {
            $monthName = Carbon::create($year, $month, 1)->format('F');
            $listName = $listType === \App\Enums\ListTypeEnum::INDIE_GAMES
                ? "{$monthName} {$year} - Indie Games"
                : "{$monthName} {$year}";
            // Generate slug from month and year only, not the full list name
            $slug = $this->generateUniqueSlug("{$monthName} {$year}", $listType);

            // Check if list already exists (must match both slug and type)
            $existingList = GameList::where('slug', $slug)
                ->where('list_type', $listType->value)
                ->first();

            if ($existingList) {
                $this->warn("List '{$listName}' already exists (ID: {$existingList->id}), skipping...");
                $skippedCount++;
                continue;
            }

            // Calculate start and end dates for the month
            $startDate = Carbon::create($year, $month, 1)->startOfDay();
            $endDate = Carbon::create($year, $month, 1)->endOfMonth()->endOfDay();

            // Create the game list
            $gameList = GameList::create([
                'user_id' => 1,
                'name' => $listName,
                'description' => null,
                'slug' => $slug,
                'is_public' => true,
                'is_system' => true,
                'is_active' => true,
                'list_type' => $listType->value,
                'start_at' => $startDate,
                'end_at' => $endDate,
            ]);

            $this->info("✓ Created: {$listName} (ID: {$gameList->id}, Slug: {$slug})");
            $this->line("  Start: {$startDate->format('Y-m-d')} | End: {$endDate->format('Y-m-d')}");
            $createdCount++;
        }

        $this->newLine();
        $this->info("=== Summary ===");
        $this->info("Created: {$createdCount} list(s)");
        if ($skippedCount > 0) {
            $this->warn("Skipped: {$skippedCount} list(s) (already exist)");
        }

        return Command::SUCCESS;
    }

    /**
     * Generate a unique slug from name, unique per list type.
     */
    private function generateUniqueSlug(string $name, \App\Enums\ListTypeEnum $listType): string
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $counter = 1;

        while (GameList::where('slug', $slug)->where('list_type', $listType->value)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}
