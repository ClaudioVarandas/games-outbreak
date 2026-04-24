<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->text('url');
            $table->string('youtube_id')->nullable()->unique();
            $table->string('title')->nullable();
            $table->string('channel_name')->nullable();
            $table->string('channel_id')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->text('thumbnail_url')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('status')->default('pending');
            $table->longText('failure_reason')->nullable();
            $table->json('raw_api_response')->nullable();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index(['is_active', 'is_featured', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};
