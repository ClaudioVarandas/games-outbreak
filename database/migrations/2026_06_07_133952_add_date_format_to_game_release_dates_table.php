<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IGDB's release-date granularity (date_format): 0=YYYYMMDD (full), 1=YYYYMM (month),
     * 2=YYYY (year), 3-6=quarters, 7=TBD. Drives whether a list pivot gets a concrete
     * date or TBA + year. Previously we relied on a non-existent `d` field, so granularity
     * was undetectable.
     */
    public function up(): void
    {
        Schema::table('game_release_dates', function (Blueprint $table) {
            $table->unsignedTinyInteger('date_format')->nullable()->after('day');
        });
    }

    public function down(): void
    {
        Schema::table('game_release_dates', function (Blueprint $table) {
            $table->dropColumn('date_format');
        });
    }
};
