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
        Schema::table('games', function (Blueprint $table) {
            $table->unsignedTinyInteger('metacritic_score')->nullable()->after('steam_wishlist_count');
            $table->string('metacritic_url')->nullable()->after('metacritic_score');
            $table->unsignedTinyInteger('steam_review_percent')->nullable()->after('metacritic_url');
            $table->string('steam_review_desc')->nullable()->after('steam_review_percent');
            $table->unsignedInteger('steam_review_total')->nullable()->after('steam_review_desc');
            $table->unsignedInteger('steam_review_positive')->nullable()->after('steam_review_total');
            $table->unsignedInteger('steam_review_negative')->nullable()->after('steam_review_positive');
            $table->unsignedTinyInteger('igdb_aggregated_rating')->nullable()->after('steam_review_negative');
            $table->unsignedSmallInteger('igdb_aggregated_rating_count')->nullable()->after('igdb_aggregated_rating');
            $table->timestamp('last_steam_review_sync_at')->nullable()->after('igdb_aggregated_rating_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn([
                'metacritic_score',
                'metacritic_url',
                'steam_review_percent',
                'steam_review_desc',
                'steam_review_total',
                'steam_review_positive',
                'steam_review_negative',
                'igdb_aggregated_rating',
                'igdb_aggregated_rating_count',
                'last_steam_review_sync_at',
            ]);
        });
    }
};
