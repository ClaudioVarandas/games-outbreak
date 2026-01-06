<?php

use App\Models\GameList;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, populate slugs for any lists that don't have one
        $listsWithoutSlugs = GameList::whereNull('slug')->get();
        
        foreach ($listsWithoutSlugs as $list) {
            $slug = Str::slug($list->name);
            $originalSlug = $slug;
            $counter = 1;
            
            // Ensure uniqueness
            while (GameList::where('slug', $slug)->where('id', '!=', $list->id)->exists()) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }
            
            $list->update(['slug' => $slug]);
        }
        
        // Now make the column not nullable
        // Note: We don't re-add unique() because it already exists from the original migration
        // For SQLite, we need to use raw SQL as change() doesn't work well
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            // SQLite doesn't support ALTER COLUMN directly, but Laravel handles this via table recreation
            // We'll just ensure all slugs are populated (done above) and skip the column change for SQLite
            // The unique constraint already exists, so we're good
        } else {
            Schema::table('game_lists', function (Blueprint $table) {
                $table->string('slug')->nullable(false)->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_lists', function (Blueprint $table) {
            $table->string('slug')->nullable()->change();
        });
    }
};
