<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NewsImportStatusEnum;
use Database\Factories\NewsImportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class NewsImport extends Model
{
    /** @use HasFactory<NewsImportFactory> */
    use HasFactory;

    protected $fillable = [
        'url',
        'canonical_url',
        'source_domain',
        'status',
        'failure_reason',
        'raw_title',
        'raw_author',
        'raw_published_at',
        'raw_body',
        'raw_excerpt',
        'raw_image_url',
        'checksum',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => NewsImportStatusEnum::class,
            'raw_published_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function article(): HasOne
    {
        return $this->hasOne(NewsArticle::class);
    }

    public function isFailed(): bool
    {
        return $this->status === NewsImportStatusEnum::Failed;
    }

    public function isReady(): bool
    {
        return $this->status === NewsImportStatusEnum::Ready;
    }

    public function markAs(NewsImportStatusEnum $status, ?string $reason = null): void
    {
        $this->update(['status' => $status, 'failure_reason' => $reason]);
    }
}
