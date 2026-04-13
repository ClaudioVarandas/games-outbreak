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
        Schema::create('news_imports', function (Blueprint $table) {
            $table->id();
            $table->text('url');
            $table->text('canonical_url')->nullable();
            $table->string('source_domain')->nullable();
            $table->string('status')->default('pending');
            $table->text('failure_reason')->nullable();
            $table->string('raw_title')->nullable();
            $table->string('raw_author')->nullable();
            $table->timestamp('raw_published_at')->nullable();
            $table->longText('raw_body')->nullable();
            $table->text('raw_excerpt')->nullable();
            $table->text('raw_image_url')->nullable();
            $table->string('checksum')->nullable()->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('news_imports');
    }
};
