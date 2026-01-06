<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            // Track when game data was last synced from IGDB
            $table->timestamp('last_igdb_sync_at')->nullable()->after('updated_at');

            // Track user engagement
            $table->timestamp('last_viewed_at')->nullable()->after('last_igdb_sync_at');
            $table->unsignedInteger('view_count')->default(0)->after('last_viewed_at');

            // Calculated priority for update scheduling (0-100)
            $table->unsignedTinyInteger('update_priority')->default(50)->after('view_count');

            // Indexes for efficient querying
            $table->index('last_igdb_sync_at');
            $table->index('last_viewed_at');
            $table->index('update_priority');
            $table->index(['view_count', 'last_igdb_sync_at']); // Composite for popular games query
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropIndex(['games_last_igdb_sync_at_index']);
            $table->dropIndex(['games_last_viewed_at_index']);
            $table->dropIndex(['games_update_priority_index']);
            $table->dropIndex(['games_view_count_last_igdb_sync_at_index']);

            $table->dropColumn([
                'last_igdb_sync_at',
                'last_viewed_at',
                'view_count',
                'update_priority',
            ]);
        });
    }
};
