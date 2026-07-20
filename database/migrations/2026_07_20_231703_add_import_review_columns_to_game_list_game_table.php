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
            $table->string('import_confidence')->nullable()->after('release_year');
            $table->json('import_sources')->nullable()->after('import_confidence');
            $table->string('import_note', 500)->nullable()->after('import_sources');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_list_game', function (Blueprint $table) {
            $table->dropColumn(['import_confidence', 'import_sources', 'import_note']);
        });
    }
};
