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
        Schema::create('steam_game_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('steam_app_id')->index();

            // SteamSpy fields
            $table->string('owners')->nullable();
            $table->string('players_forever')->nullable();
            $table->string('players_2weeks')->nullable();
            $table->unsignedInteger('average_forever')->nullable();
            $table->unsignedInteger('average_2weeks')->nullable();
            $table->unsignedInteger('median_forever')->nullable();
            $table->unsignedInteger('median_2weeks')->nullable();
            $table->unsignedInteger('ccu')->nullable();
            $table->unsignedInteger('price')->nullable();
            $table->integer('score_rank')->nullable();
            $table->string('genre')->nullable();
            $table->json('tags')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('steam_game_data');
    }
};
