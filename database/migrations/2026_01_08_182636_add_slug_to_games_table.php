<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Step 1: Add nullable slug column if it doesn't exist
        if (! Schema::hasColumn('games', 'slug')) {
            Schema::table('games', function (Blueprint $table) {
                $table->string('slug')->nullable()->after('name');
            });
        }

        // Step 2: Backfill slugs for ALL existing games
        $this->backfillSlugs();

        // Step 3: Verify all games have slugs before proceeding
        $nullCount = DB::table('games')->whereNull('slug')->count();
        if ($nullCount > 0) {
            throw new \RuntimeException("Migration failed: {$nullCount} games still have NULL slug values.");
        }

        // Step 4: Add unique index if it doesn't exist (database-agnostic check)
        $indexExists = $this->indexExists('games', 'games_slug_unique');
        if (! $indexExists) {
            Schema::table('games', function (Blueprint $table) {
                $table->unique('slug');
            });
        }

        // Step 5: Make slug non-nullable (database-agnostic)
        $this->makeColumnNotNullable('games', 'slug');
    }

    /**
     * Backfill slugs for all existing games.
     */
    private function backfillSlugs(): void
    {
        // Process oldest games first (they get priority for clean slugs)
        while (DB::table('games')->whereNull('slug')->exists()) {
            $games = DB::table('games')
                ->whereNull('slug')
                ->orderBy('first_release_date', 'asc')
                ->orderBy('id', 'asc')
                ->limit(500)
                ->get();

            if ($games->isEmpty()) {
                break;
            }

            foreach ($games as $game) {
                $slug = $this->generateUniqueSlug(
                    $game->name,
                    $game->first_release_date,
                    $game->id
                );

                DB::table('games')
                    ->where('id', $game->id)
                    ->update(['slug' => $slug]);
            }
        }
    }

    /**
     * Generate a unique slug for a game.
     */
    private function generateUniqueSlug(string $name, ?string $releaseDate, int $gameId): string
    {
        $baseSlug = Str::slug($name);

        // Add year if release date exists
        if ($releaseDate) {
            $year = date('Y', strtotime($releaseDate));
            $baseSlug .= '-'.$year;
        }

        $slug = $baseSlug;
        $counter = 1;

        // Check for uniqueness, excluding current game
        while (DB::table('games')
            ->where('slug', $slug)
            ->where('id', '!=', $gameId)
            ->exists()) {
            $counter++;
            $slug = $baseSlug.'-'.$counter;
        }

        return $slug;
    }

    /**
     * Check if an index exists (works with MySQL and SQLite).
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            $result = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);

            return ! empty($result);
        }

        if ($driver === 'sqlite') {
            $result = DB::select("SELECT name FROM sqlite_master WHERE type = 'index' AND name = ?", [$indexName]);

            return ! empty($result);
        }

        return false;
    }

    /**
     * Make a column not nullable (works with MySQL and SQLite).
     */
    private function makeColumnNotNullable(string $table, string $column): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            $columnInfo = DB::selectOne("SHOW COLUMNS FROM {$table} WHERE Field = ?", [$column]);
            if ($columnInfo && $columnInfo->Null === 'YES') {
                DB::statement("ALTER TABLE {$table} MODIFY {$column} VARCHAR(255) NOT NULL");
            }
        }
        // SQLite doesn't support ALTER COLUMN - for fresh migrations it's handled by the schema
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
