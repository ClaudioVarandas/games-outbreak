<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->foreignId('video_category_id')
                ->nullable()
                ->after('user_id')
                ->constrained('video_categories')
                ->nullOnDelete();

            $table->index('video_category_id');
        });
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropForeign(['video_category_id']);
            $table->dropIndex(['video_category_id']);
            $table->dropColumn('video_category_id');
        });
    }
};
