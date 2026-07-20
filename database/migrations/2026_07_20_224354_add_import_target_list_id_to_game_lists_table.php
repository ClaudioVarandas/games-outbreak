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
        Schema::table('game_lists', function (Blueprint $table) {
            $table->foreignId('import_target_list_id')
                ->nullable()
                ->after('igdb_event_id')
                ->constrained('game_lists')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_lists', function (Blueprint $table) {
            $table->dropConstrainedForeignId('import_target_list_id');
        });
    }
};
