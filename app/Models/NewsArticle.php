<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NewsArticleStatusEnum;
use Database\Factories\NewsArticleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NewsArticle extends Model
{
    /** @use HasFactory<NewsArticleFactory> */
    use HasFactory;

    protected $fillable = [
        'news_import_id',
        'user_id',
        'status',
        'source_name',
        'source_url',
        'original_title',
        'original_language',
        'original_published_at',
        'featured_image_url',
        'slug_pt_pt',
        'slug_pt_br',
        'scheduled_at',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => NewsArticleStatusEnum::class,
            'original_published_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (NewsArticle $article) {
            if (empty($article->slug_pt_pt) && $article->original_title) {
                $article->slug_pt_pt = static::generateUniqueSlug($article->original_title, 'slug_pt_pt');
                $article->slug_pt_br = static::generateUniqueSlug($article->original_title, 'slug_pt_br');
            }
        });
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(NewsImport::class, 'news_import_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function localizations(): HasMany
    {
        return $this->hasMany(NewsArticleLocalization::class);
    }

    public function localization(string $locale): ?NewsArticleLocalization
    {
        return $this->localizations->firstWhere('locale', $locale);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', NewsArticleStatusEnum::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopeScheduledDue(Builder $query): Builder
    {
        return $query->where('status', NewsArticleStatusEnum::Scheduled)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now());
    }

    public function isPublished(): bool
    {
        return $this->status === NewsArticleStatusEnum::Published;
    }

    protected static function generateUniqueSlug(string $title, string $column): string
    {
        $slug = $base = str()->slug($title);
        $counter = 1;
        while (static::where($column, $slug)->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }
}
