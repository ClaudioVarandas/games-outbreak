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
        Schema::table('games', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique()->after('id');
        });

        // Backfill UUIDs for existing games
        DB::table('games')->whereNull('uuid')->orderBy('id')->chunk(1000, function ($games) {
            foreach ($games as $game) {
                DB::table('games')
                    ->where('id', $game->id)
                    ->update(['uuid' => Str::uuid()->toString()]);
            }
        });

        // Make uuid non-nullable after backfill
        Schema::table('games', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('uuid');
        });
    }
};
