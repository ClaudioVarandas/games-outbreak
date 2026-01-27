<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_list_game', function (Blueprint $table) {
            $table->dropColumn('indie_genre');
            $table->json('genre_ids')->nullable()->after('is_indie');
            $table->foreignId('primary_genre_id')->nullable()->after('genre_ids')
                ->constrained('genres')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('game_list_game', function (Blueprint $table) {
            $table->dropForeign(['primary_genre_id']);
            $table->dropColumn(['genre_ids', 'primary_genre_id']);
            $table->string('indie_genre')->nullable()->after('is_indie');
        });
    }
};
