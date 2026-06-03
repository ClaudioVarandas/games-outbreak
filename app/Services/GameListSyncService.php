<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ListTypeEnum;
use App\Models\GameList;
use Carbon\Carbon;
use Illuminate\Support\Str;

class GameListSyncService
{
    public function findYearlyList(int $year): ?GameList
    {
        return GameList::yearly()
            ->where('is_system', true)
            ->whereYear('start_at', $year)
            ->first();
    }

    /**
     * Find the system yearly list for the year, creating it if absent.
     *
     * Note: this is a check-then-create within a single process; it is intended for
     * serial admin use (CLI commands / a single sync run). It is not safe against
     * concurrent callers without a surrounding transaction or unique constraint.
     */
    public function firstOrCreateYearlyList(int $year): GameList
    {
        if ($existing = $this->findYearlyList($year)) {
            return $existing;
        }

        $name = "Game Releases {$year}";

        return GameList::create([
            'user_id' => 1,
            'name' => $name,
            'description' => "Curated game releases for {$year}",
            'slug' => $this->uniqueYearlySlug($name),
            'is_public' => true,
            'is_system' => true,
            'is_active' => true,
            'list_type' => ListTypeEnum::YEARLY->value,
            'start_at' => Carbon::create($year, 1, 1)->startOfDay(),
            'end_at' => Carbon::create($year, 12, 31)->endOfDay(),
        ]);
    }

    private function uniqueYearlySlug(string $name): string
    {
        $slug = Str::slug($name);
        $original = $slug;
        $counter = 1;

        while (GameList::where('slug', $slug)->where('list_type', ListTypeEnum::YEARLY->value)->exists()) {
            $slug = $original.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
