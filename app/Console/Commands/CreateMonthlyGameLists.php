<?php

namespace App\Console\Commands;

use App\Models\GameList;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateMonthlyGameLists extends Command
{
    protected $signature = 'games:lists:create-monthly {--year= : Year to create lists for (defaults to current year)}';

    protected $description = 'Create monthly game lists for a given year. Creates 12 lists (one for each month) with system, public, and active flags set.';

    public function handle(): int
    {
        $year = $this->option('year');
        
        if (!$year) {
            $year = date('Y');
        }
        
        $year = (int) $year;
        
        if ($year < 2000 || $year > 2100) {
            $this->error('Invalid year. Please enter a year between 2000 and 2100.');
            return Command::FAILURE;
        }
        
        $this->info("Creating monthly game lists for year: {$year}");
        $this->newLine();
        
        $createdCount = 0;
        $skippedCount = 0;
        
        for ($month = 1; $month <= 12; $month++) {
            $monthName = Carbon::create($year, $month, 1)->format('F');
            $listName = "{$monthName} {$year}";
            $slug = $this->generateUniqueSlug($listName);
            
            // Check if list already exists
            $existingList = GameList::where('slug', $slug)
                ->orWhere(function ($query) use ($listName) {
                    $query->where('name', $listName)
                          ->where('is_system', true);
                })
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
                'list_type' => \App\Enums\ListTypeEnum::MONTHLY->value,
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
     * Generate a unique slug from name.
     */
    private function generateUniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $counter = 1;
        
        while (GameList::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }
}
