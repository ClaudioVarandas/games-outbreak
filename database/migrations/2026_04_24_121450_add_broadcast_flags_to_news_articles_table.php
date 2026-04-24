<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_articles', function (Blueprint $table) {
            $table->boolean('should_broadcast')->default(true)->after('scheduled_at');
            $table->timestamp('broadcasted_at')->nullable()->after('should_broadcast');
        });
    }

    public function down(): void
    {
        Schema::table('news_articles', function (Blueprint $table) {
            $table->dropColumn(['broadcasted_at', 'should_broadcast']);
        });
    }
};
