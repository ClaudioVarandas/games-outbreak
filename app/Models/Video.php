<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VideoImportStatusEnum;
use Database\Factories\VideoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Video extends Model
{
    /** @use HasFactory<VideoFactory> */
    use HasFactory;

    protected $fillable = [
        'url',
        'youtube_id',
        'title',
        'channel_name',
        'channel_id',
        'duration_seconds',
        'thumbnail_url',
        'description',
        'published_at',
        'is_featured',
        'is_active',
        'status',
        'failure_reason',
        'raw_api_response',
        'user_id',
        'video_category_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => VideoImportStatusEnum::class,
            'published_at' => 'datetime',
            'raw_api_response' => 'array',
            'is_featured' => 'bool',
            'is_active' => 'bool',
            'duration_seconds' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(VideoCategory::class, 'video_category_id');
    }

    public function scopeReady(Builder $query): Builder
    {
        return $query->where('status', VideoImportStatusEnum::Ready);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopePublicVisible(Builder $query): Builder
    {
        return $query->ready()->active();
    }

    public function isReady(): bool
    {
        return $this->status === VideoImportStatusEnum::Ready;
    }

    public function isFailed(): bool
    {
        return $this->status === VideoImportStatusEnum::Failed;
    }

    public function embedUrl(bool $autoplay = true): ?string
    {
        if (! $this->youtube_id) {
            return null;
        }

        $flags = 'rel=0&modestbranding=1'.($autoplay ? '&autoplay=1' : '');

        return "https://www.youtube.com/embed/{$this->youtube_id}?{$flags}";
    }

    public function watchUrl(): ?string
    {
        return $this->youtube_id
            ? "https://www.youtube.com/watch?v={$this->youtube_id}"
            : null;
    }

    public function durationFormatted(): ?string
    {
        if ($this->duration_seconds === null) {
            return null;
        }

        $h = intdiv($this->duration_seconds, 3600);
        $m = intdiv($this->duration_seconds % 3600, 60);
        $s = $this->duration_seconds % 60;

        return $h > 0
            ? sprintf('%d:%02d:%02d', $h, $m, $s)
            : sprintf('%d:%02d', $m, $s);
    }

    public function thumbnailMaxRes(): ?string
    {
        return $this->youtube_id
            ? "https://i.ytimg.com/vi/{$this->youtube_id}/maxresdefault.jpg"
            : null;
    }

    public function thumbnailHq(): ?string
    {
        return $this->youtube_id
            ? "https://i.ytimg.com/vi/{$this->youtube_id}/hqdefault.jpg"
            : null;
    }

    public function markAs(VideoImportStatusEnum $status, ?string $reason = null): void
    {
        $this->update(['status' => $status, 'failure_reason' => $reason]);
    }
}
