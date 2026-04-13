<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NewsLocaleEnum;
use Database\Factories\NewsArticleLocalizationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsArticleLocalization extends Model
{
    /** @use HasFactory<NewsArticleLocalizationFactory> */
    use HasFactory;

    protected $fillable = [
        'news_article_id',
        'locale',
        'title',
        'summary_short',
        'summary_medium',
        'body',
        'seo_title',
        'seo_description',
        'generation_metadata',
    ];

    protected function casts(): array
    {
        return [
            'locale' => NewsLocaleEnum::class,
            'body' => 'array',
            'generation_metadata' => 'array',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(NewsArticle::class, 'news_article_id');
    }
}
