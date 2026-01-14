<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameExternalSource extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'last_attempted_at' => 'datetime',
            'next_retry_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'retry_count' => 'integer',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function externalGameSource(): BelongsTo
    {
        return $this->belongsTo(ExternalGameSource::class);
    }

    public function markAsSynced(): void
    {
        $this->update([
            'sync_status' => 'synced',
            'last_synced_at' => now(),
            'last_attempted_at' => now(),
            'retry_count' => 0,
            'next_retry_at' => null,
        ]);
    }

    public function markAsFailed(): void
    {
        $retryCount = $this->retry_count + 1;
        $backoffHours = match (true) {
            $retryCount === 1 => 1,
            $retryCount === 2 => 4,
            $retryCount === 3 => 24,
            default => 168, // 7 days
        };

        $this->update([
            'sync_status' => 'failed',
            'last_attempted_at' => now(),
            'retry_count' => $retryCount,
            'next_retry_at' => now()->addHours($backoffHours),
        ]);
    }

    public function getFullUrlAttribute(): ?string
    {
        if ($this->external_url) {
            return $this->external_url;
        }

        $baseUrl = $this->externalGameSource?->store_url;

        if ($baseUrl && $this->external_uid) {
            return $baseUrl.$this->external_uid;
        }

        return null;
    }

    public function scopePending($query)
    {
        return $query->where('sync_status', 'pending');
    }

    public function scopeFailed($query)
    {
        return $query->where('sync_status', 'failed');
    }

    public function scopeReadyForRetry($query)
    {
        return $query->where('sync_status', 'failed')
            ->where(function ($q) {
                $q->whereNull('next_retry_at')
                    ->orWhere('next_retry_at', '<=', now());
            });
    }

    public function scopeForSource($query, int $sourceIgdbId)
    {
        return $query->whereHas('externalGameSource', function ($q) use ($sourceIgdbId) {
            $q->where('igdb_id', $sourceIgdbId);
        });
    }
}
