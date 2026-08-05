<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_list_game', function (Blueprint $table) {
            $table->json('import_source_ids')->nullable()->after('import_sources');
        });
    }

    public function down(): void
    {
        Schema::table('game_list_game', function (Blueprint $table) {
            $table->dropColumn('import_source_ids');
        });
    }
};
