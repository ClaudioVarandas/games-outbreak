<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('igdb_id')->unique(); // IGDB unique identifier
            $table->string('name');
            $table->text('summary')->nullable();
            $table->timestamp('first_release_date')->nullable(); // Global first release
            $table->string('cover_image_id')->nullable(); // e.g., "coazkb"
            $table->json('steam_data')->nullable(); // Flexible Steam enrichment
            $table->json('similar_games')->nullable();
            $table->json('screenshots')->nullable();
            $table->json('trailers')->nullable();
            $table->timestamps();

            $table->index('first_release_date');
            $table->index('igdb_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
