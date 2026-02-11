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
        Schema::create('user_games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->string('status')->nullable();
            $table->boolean('is_wishlisted')->default(false);
            $table->decimal('time_played', 6, 1)->nullable();
            $table->unsignedSmallInteger('rating')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('added_at');
            $table->timestamp('status_changed_at')->nullable();
            $table->timestamp('wishlisted_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'game_id']);
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'is_wishlisted']);
            $table->index(['user_id', 'status', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_games');
    }
};
