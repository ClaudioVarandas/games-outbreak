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
        Schema::create('game_external_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('external_game_source_id')->constrained()->cascadeOnDelete();
            $table->string('external_uid');
            $table->string('external_url')->nullable();

            // Sync tracking columns
            $table->string('sync_status')->default('pending');
            $table->unsignedInteger('retry_count')->default(0);
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();

            $table->timestamps();

            $table->unique(['game_id', 'external_game_source_id']);
            $table->index('sync_status');
            $table->index('next_retry_at');
            $table->index('external_uid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_external_sources');
    }
};
