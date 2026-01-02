<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration documents the addition of the 'indie-games' value
     * to the ListTypeEnum. No schema changes are needed since the
     * list_type column is a string that accepts any value.
     */
    public function up(): void
    {
        // No schema changes needed - the list_type column already exists
        // and accepts string values. This migration is for documentation
        // and to provide a rollback mechanism.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert any indie-games lists back to regular type
        DB::table('game_lists')
            ->where('list_type', 'indie-games')
            ->update(['list_type' => 'regular']);
    }
};
