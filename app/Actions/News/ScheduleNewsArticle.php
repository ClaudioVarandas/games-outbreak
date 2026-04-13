<?php

declare(strict_types=1);

namespace App\Actions\News;

use App\Enums\NewsArticleStatusEnum;
use App\Models\NewsArticle;
use Carbon\Carbon;

class ScheduleNewsArticle
{
    public function handle(NewsArticle $article, Carbon $scheduledAt): void
    {
        $article->update([
            'status' => NewsArticleStatusEnum::Scheduled,
            'scheduled_at' => $scheduledAt,
        ]);
    }
}
