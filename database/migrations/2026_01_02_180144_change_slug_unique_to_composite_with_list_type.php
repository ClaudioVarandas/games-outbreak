<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Change slug unique constraint to composite unique (slug, list_type).
     * This allows the same slug to be used across different list types.
     */
    public function up(): void
    {
        Schema::table('game_lists', function (Blueprint $table) {
            // Drop the old unique constraint on slug
            $table->dropUnique(['slug']);

            // Add composite unique constraint on (slug, list_type)
            $table->unique(['slug', 'list_type'], 'game_lists_slug_list_type_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_lists', function (Blueprint $table) {
            // Drop the composite unique constraint
            $table->dropUnique('game_lists_slug_list_type_unique');

            // Restore the simple unique constraint on slug
            $table->unique('slug');
        });
    }
};
