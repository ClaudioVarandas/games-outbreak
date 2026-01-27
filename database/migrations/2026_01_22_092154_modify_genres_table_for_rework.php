<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columns = Schema::getColumnListing('genres');

        if (! in_array('slug', $columns)) {
            Schema::table('genres', function (Blueprint $table) {
                $table->string('slug')->nullable()->after('name');
            });
        }

        if (! in_array('is_system', $columns)) {
            Schema::table('genres', function (Blueprint $table) {
                $table->boolean('is_system')->default(false)->after('slug');
            });
        }

        if (! in_array('is_visible', $columns)) {
            Schema::table('genres', function (Blueprint $table) {
                $table->boolean('is_visible')->default(true)->after('is_system');
            });
        }

        if (! in_array('is_pending_review', $columns)) {
            Schema::table('genres', function (Blueprint $table) {
                $table->boolean('is_pending_review')->default(false)->after('is_visible');
            });
        }

        if (! in_array('sort_order', $columns)) {
            Schema::table('genres', function (Blueprint $table) {
                $table->unsignedInteger('sort_order')->default(0)->after('is_pending_review');
            });
        }

        // Ensure igdb_id is nullable (custom genres don't have IGDB IDs)
        Schema::table('genres', function (Blueprint $table) {
            $table->unsignedBigInteger('igdb_id')->nullable()->change();
        });

        DB::table('genres')->whereNull('slug')->orWhere('slug', '')->get()->each(function ($genre) {
            DB::table('genres')
                ->where('id', $genre->id)
                ->update(['slug' => str()->slug($genre->name)]);
        });

        $indexes = $this->getIndexNames('genres');

        if (! in_array('genres_slug_unique', $indexes)) {
            Schema::table('genres', function (Blueprint $table) {
                $table->string('slug')->nullable(false)->unique()->change();
            });
        }

        if (! in_array('genres_is_visible_index', $indexes)) {
            Schema::table('genres', function (Blueprint $table) {
                $table->index('is_visible');
            });
        }

        if (! in_array('genres_is_pending_review_index', $indexes)) {
            Schema::table('genres', function (Blueprint $table) {
                $table->index('is_pending_review');
            });
        }

        if (! in_array('genres_sort_order_index', $indexes)) {
            Schema::table('genres', function (Blueprint $table) {
                $table->index('sort_order');
            });
        }
    }

    public function down(): void
    {
        Schema::table('genres', function (Blueprint $table) {
            $table->dropIndex(['is_visible']);
            $table->dropIndex(['is_pending_review']);
            $table->dropIndex(['sort_order']);
            $table->dropUnique(['slug']);

            $table->dropColumn(['slug', 'is_system', 'is_visible', 'is_pending_review', 'sort_order']);
        });

        Schema::table('genres', function (Blueprint $table) {
            $table->unsignedBigInteger('igdb_id')->nullable(false)->change();
            $table->unique('igdb_id');
        });
    }

    private function getIndexNames(string $table): array
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('{$table}')");

            return collect($indexes)->pluck('name')->toArray();
        }

        $indexes = DB::select("SHOW INDEX FROM {$table}");

        return collect($indexes)->pluck('Key_name')->unique()->toArray();
    }
};
