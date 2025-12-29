<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Migrate existing system lists to appropriate types
        // We need to check each list individually since SQLite doesn't support REGEXP

        $months = ['January', 'February', 'March', 'April', 'May', 'June',
                   'July', 'August', 'September', 'October', 'November', 'December'];

        $systemLists = DB::table('game_lists')
            ->where('is_system', true)
            ->get();

        foreach ($systemLists as $list) {
            $isMonthly = false;

            // Check if the name matches "Month YYYY" pattern
            foreach ($months as $month) {
                if (preg_match('/^' . $month . ' \d{4}$/', $list->name)) {
                    $isMonthly = true;
                    break;
                }
            }

            $newType = $isMonthly ? 'monthly' : 'seasoned';

            DB::table('game_lists')
                ->where('id', $list->id)
                ->update(['list_type' => $newType]);
        }

        // Add compound index for efficient querying of system lists by type
        Schema::table('game_lists', function (Blueprint $table) {
            $table->index(['is_system', 'list_type'], 'game_lists_is_system_list_type_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_lists', function (Blueprint $table) {
            $table->dropIndex('game_lists_is_system_list_type_index');
        });

        // Revert all system lists back to 'regular'
        DB::table('game_lists')
            ->where('is_system', true)
            ->whereIn('list_type', ['monthly', 'seasoned'])
            ->update(['list_type' => 'regular']);
    }
};
