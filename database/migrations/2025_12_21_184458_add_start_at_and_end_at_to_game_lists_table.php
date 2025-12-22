<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('game_lists', function (Blueprint $table) {
            // Add new columns
            $table->timestamp('start_at')->nullable()->after('is_active');
            $table->timestamp('end_at')->nullable()->after('start_at');
        });

        // Migrate data from expire_at to end_at
        DB::table('game_lists')->whereNotNull('expire_at')->update([
            'end_at' => DB::raw('expire_at')
        ]);

        // Drop the old expire_at column
        Schema::table('game_lists', function (Blueprint $table) {
            $table->dropColumn('expire_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_lists', function (Blueprint $table) {
            // Add back expire_at
            $table->timestamp('expire_at')->nullable()->after('is_active');
        });

        // Migrate data back from end_at to expire_at
        DB::table('game_lists')->whereNotNull('end_at')->update([
            'expire_at' => DB::raw('end_at')
        ]);

        // Drop the new columns
        Schema::table('game_lists', function (Blueprint $table) {
            $table->dropColumn(['start_at', 'end_at']);
        });
    }
};
