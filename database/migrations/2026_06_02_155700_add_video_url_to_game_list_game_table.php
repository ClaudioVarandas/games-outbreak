<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_list_game', function (Blueprint $table) {
            $table->string('video_url')->nullable()->after('platform_group');
        });
    }

    public function down(): void
    {
        Schema::table('game_list_game', function (Blueprint $table) {
            $table->dropColumn('video_url');
        });
    }
};
