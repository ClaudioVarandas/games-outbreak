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
        Schema::create('game_release_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('platform_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('status_id')->nullable()->constrained('release_date_statuses')->nullOnDelete();
            $table->unsignedBigInteger('igdb_release_date_id')->nullable()->comment('IGDB release date ID for tracking');
            $table->timestamp('date')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->unsignedTinyInteger('month')->nullable();
            $table->unsignedTinyInteger('day')->nullable();
            $table->unsignedTinyInteger('region')->nullable()->comment('IGDB region code');
            $table->string('human_readable')->nullable()->comment('Human-readable date from IGDB');
            $table->boolean('is_manual')->default(false)->comment('User-added vs IGDB-synced');
            $table->timestamps();

            // Indexes for performance
            $table->index('game_id');
            $table->index('platform_id');
            $table->index('date');
            $table->index(['game_id', 'platform_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_release_dates');
    }
};
