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
            $table->boolean('is_indie')->default(false)->after('is_highlight');
            $table->string('indie_genre')->nullable()->after('is_indie');
        });
    }

    public function down(): void
    {
        Schema::table('game_list_game', function (Blueprint $table) {
            $table->dropColumn(['is_indie', 'indie_genre']);
        });
    }
};
