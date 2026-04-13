<?php

declare(strict_types=1);

namespace App\Actions\News;

use App\Enums\NewsArticleStatusEnum;
use App\Models\NewsArticle;

class PublishNewsArticle
{
    public function handle(NewsArticle $article): void
    {
        $article->update([
            'status' => NewsArticleStatusEnum::Published,
            'published_at' => $article->published_at ?? now(),
            'scheduled_at' => null,
        ]);
    }
}
