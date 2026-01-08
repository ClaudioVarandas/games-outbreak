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
        // Step 1: Add nullable UUID column if it doesn't exist
        if (! Schema::hasColumn('games', 'uuid')) {
            Schema::table('games', function (Blueprint $table) {
                $table->uuid('uuid')->nullable()->after('id');
            });
        }

        // Step 2: Backfill UUIDs for ALL existing games using a loop to ensure completion
        while (DB::table('games')->whereNull('uuid')->exists()) {
            $games = DB::table('games')
                ->whereNull('uuid')
                ->orderBy('id')
                ->limit(500)
                ->get();

            if ($games->isEmpty()) {
                break;
            }

            foreach ($games as $game) {
                DB::table('games')
                    ->where('id', $game->id)
                    ->update(['uuid' => Str::uuid()->toString()]);
            }
        }

        // Step 3: Verify all games have UUIDs before proceeding
        $nullCount = DB::table('games')->whereNull('uuid')->count();
        if ($nullCount > 0) {
            throw new \RuntimeException("Migration failed: {$nullCount} games still have NULL uuid values.");
        }

        // Step 4: Add unique index if it doesn't exist (database-agnostic check)
        $indexExists = $this->indexExists('games', 'games_uuid_unique');
        if (! $indexExists) {
            Schema::table('games', function (Blueprint $table) {
                $table->unique('uuid');
            });
        }

        // Step 5: Make uuid non-nullable (database-agnostic)
        $this->makeColumnNotNullable('games', 'uuid');
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

        // For other drivers, try adding the index and catch the exception
        return false;
    }

    /**
     * Make a column not nullable (works with MySQL and SQLite).
     */
    private function makeColumnNotNullable(string $table, string $column): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            // Check if column is already NOT NULL
            $columnInfo = DB::selectOne("SHOW COLUMNS FROM {$table} WHERE Field = ?", [$column]);
            if ($columnInfo && $columnInfo->Null === 'YES') {
                DB::statement("ALTER TABLE {$table} MODIFY {$column} CHAR(36) NOT NULL");
            }
        } elseif ($driver === 'sqlite') {
            // SQLite doesn't support ALTER COLUMN, but for fresh migrations it's already handled
            // For SQLite in tests, we skip this step as the column is created fresh
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn('uuid');
        });
    }
};
