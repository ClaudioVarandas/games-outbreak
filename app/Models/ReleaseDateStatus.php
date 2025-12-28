<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class ReleaseDateStatus extends Model
{
    protected $fillable = [
        'igdb_id',
        'name',
        'abbreviation',
        'description',
    ];

    /**
     * Get cached status by IGDB ID
     */
    public static function getByIgdbId(int $igdbId): ?self
    {
        return Cache::remember("release_date_status_{$igdbId}", now()->addDay(), function () use ($igdbId) {
            return self::where('igdb_id', $igdbId)->first();
        });
    }

    /**
     * Get all statuses cached
     */
    public static function getAllCached(): \Illuminate\Support\Collection
    {
        return Cache::remember('release_date_statuses_all', now()->addDay(), function () {
            return self::all()->keyBy('igdb_id');
        });
    }

    /**
     * Clear status cache
     */
    public static function clearCache(): void
    {
        Cache::forget('release_date_statuses_all');
        self::all()->each(function ($status) {
            Cache::forget("release_date_status_{$status->igdb_id}");
        });
    }
}
