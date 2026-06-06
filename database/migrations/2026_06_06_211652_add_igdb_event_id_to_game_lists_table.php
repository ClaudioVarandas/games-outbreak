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
            $table->unsignedBigInteger('igdb_event_id')->nullable()->after('list_type')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_lists', function (Blueprint $table) {
            $table->dropIndex(['igdb_event_id']);
            $table->dropColumn('igdb_event_id');
        });
    }
};
