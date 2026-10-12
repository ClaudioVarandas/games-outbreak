<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PcPortCategoryEnum;
use App\Jobs\FetchPcPortCover;
use App\Models\PcPort;
use Illuminate\Support\Facades\Storage;

class PcPortSyncService
{
    private const COLUMNS = [
        'game_title',
        'original_platform',
        'project_name',
        'previous_project_name',
        'framework',
        'technique',
        'status',
        'windows_support',
        'linux_support',
        'macos_support',
        'latest_version',
        'latest_public_version',
        'last_activity',
        'last_checked_at',
        'added_at',
        'notes',
        'repo_url',
        'homepage_url',
        'primary_source',
        'framework_repo_url',
        'cover_source_url',
    ];

    /**
     * Upserts the tracker rows by slug. The local CSV is canonical, so `prune`
     * removes ports that are no longer in it.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{created: int, updated: int, unchanged: int, deleted: int}
     */
    public function sync(array $rows, bool $prune = false): array
    {
        $summary = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'deleted' => 0];

        foreach ($rows as $row) {
            $port = PcPort::firstOrNew(['slug' => $row['slug']]);
            $port->fill($this->attributesFrom($row));

            // MySQL reorders JSON object keys on write, so compare details regardless of key order.
            if ($port->exists && $port->isDirty('details') && $port->details == $port->getOriginal('details')) {
                $port->syncOriginalAttribute('details');
            }

            $isNew = ! $port->exists;
            $coverChanged = $port->isDirty('cover_source_url');

            if ($isNew) {
                $summary['created']++;
            } elseif ($port->isDirty()) {
                $summary['updated']++;
            } else {
                $summary['unchanged']++;
            }

            $port->save();

            if ($coverChanged || ! $port->cover_path) {
                FetchPcPortCover::dispatch($port->id);
            }
        }

        if ($prune) {
            $stale = PcPort::whereNotIn('slug', array_column($rows, 'slug'))->get();

            foreach ($stale as $port) {
                if ($port->cover_path && str_starts_with($port->cover_path, FetchPcPortCover::DIRECTORY.'/')) {
                    Storage::disk('public')->delete($port->cover_path);
                }

                $port->delete();
                $summary['deleted']++;
            }
        }

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function attributesFrom(array $row): array
    {
        $attributes = [];
        foreach (self::COLUMNS as $column) {
            $attributes[$column] = $row[$column] ?? null;
        }

        $category = PcPortCategoryEnum::tryFrom((string) ($row['category'] ?? '')) ?? PcPortCategoryEnum::PcPort;

        // Same rule as the standalone generator: a non-native, non-mod entry is a radar candidate.
        if ($category !== PcPortCategoryEnum::Mod && ($row['native_pc_port'] ?? null) === 'no') {
            $category = PcPortCategoryEnum::Radar;
        }

        $details = collect(PcPort::DETAIL_KEYS)
            ->mapWithKeys(fn (string $key): array => [$key => $row[$key] ?? null])
            ->filter(fn (?string $value): bool => filled($value))
            ->all();

        return $attributes + [
            'category' => $category,
            'details' => $details ?: null,
        ];
    }
}
