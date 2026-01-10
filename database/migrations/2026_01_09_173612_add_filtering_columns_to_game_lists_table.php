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
        Schema::table('game_lists', function (Blueprint $table) {
            $table->string('og_image_path')->nullable()->after('description');
            $table->json('sections')->nullable()->after('og_image_path');
            $table->boolean('auto_section_by_genre')->default(true)->after('sections');
            $table->json('tags')->nullable()->after('auto_section_by_genre');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_lists', function (Blueprint $table) {
            $table->dropColumn(['og_image_path', 'sections', 'auto_section_by_genre', 'tags']);
        });
    }
};
