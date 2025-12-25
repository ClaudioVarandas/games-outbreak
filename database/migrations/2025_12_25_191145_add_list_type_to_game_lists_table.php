<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('game_lists', function (Blueprint $table) {
            $table->string('list_type')->default('regular')->after('user_id');
        });

        // Migrate existing non-system lists to 'regular'
        DB::table('game_lists')
            ->where('is_system', false)
            ->whereNull('list_type')
            ->update(['list_type' => 'regular']);

        // Add index for unique constraint enforcement
        Schema::table('game_lists', function (Blueprint $table) {
            $table->index(['user_id', 'list_type']);
        });

        // Add unique constraint for backlog and wishlist (one per user)
        // Note: MySQL doesn't support partial unique indexes directly, so we'll handle this in application logic
        // But we can add a regular unique index that will help with enforcement
        // For PostgreSQL, we could use: $table->unique(['user_id', 'list_type'])->where('list_type', 'in', ['backlog', 'wishlist']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_lists', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'list_type']);
            $table->dropColumn('list_type');
        });
    }
};
