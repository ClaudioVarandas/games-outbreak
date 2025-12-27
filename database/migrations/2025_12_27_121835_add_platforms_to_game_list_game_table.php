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
            $table->json('platforms')->nullable()->after('release_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_list_game', function (Blueprint $table) {
            $table->dropColumn('platforms');
        });
    }
};
