<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('game_list_game', function (Blueprint $table) {
            $table->dateTime('release_date')->nullable()->after('order');
        });
        
        // Backfill existing records - use database-agnostic approach
        $connection = \DB::connection()->getDriverName();
        
        if ($connection === 'sqlite') {
            // SQLite syntax
            \DB::statement('
                UPDATE game_list_game
                SET release_date = (
                    SELECT first_release_date
                    FROM games
                    WHERE games.id = game_list_game.game_id
                )
                WHERE EXISTS (
                    SELECT 1
                    FROM games
                    WHERE games.id = game_list_game.game_id
                    AND games.first_release_date IS NOT NULL
                )
            ');
        } else {
            // MySQL/MariaDB syntax
            \DB::statement('
                UPDATE game_list_game glg
                INNER JOIN games g ON glg.game_id = g.id
                SET glg.release_date = g.first_release_date
                WHERE g.first_release_date IS NOT NULL
            ');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_list_game', function (Blueprint $table) {
            $table->dropColumn('release_date');
        });
    }
};
