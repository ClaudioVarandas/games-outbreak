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
        Schema::create('news_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('news_import_id')->constrained('news_imports')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('draft');
            $table->string('source_name')->nullable();
            $table->text('source_url')->nullable();
            $table->text('original_title')->nullable();
            $table->string('original_language', 10)->default('en');
            $table->timestamp('original_published_at')->nullable();
            $table->text('featured_image_url')->nullable();
            $table->string('slug_pt_pt')->nullable()->unique();
            $table->string('slug_pt_br')->nullable()->unique();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('news_articles');
    }
};
