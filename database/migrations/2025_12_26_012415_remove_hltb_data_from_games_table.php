<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if column exists before dropping (for SQLite compatibility)
        if (Schema::hasColumn('games', 'hltb_data')) {
            Schema::table('games', function (Blueprint $table) {
                $table->dropColumn('hltb_data');
            });
        }
    }

    public function down(): void
    {
        // Only add column if it doesn't exist
        if (!Schema::hasColumn('games', 'hltb_data')) {
            Schema::table('games', function (Blueprint $table) {
                $table->json('hltb_data')->nullable()->after('trailers');
            });
        }
    }
};
