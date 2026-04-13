<?php

declare(strict_types=1);

namespace App\Jobs\News;

use App\Actions\News\PublishNewsArticle;
use App\Models\NewsArticle;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PublishScheduledNewsJob implements ShouldQueue
{
    use Queueable;

    public function handle(PublishNewsArticle $action): void
    {
        NewsArticle::scheduledDue()
            ->get()
            ->each(fn (NewsArticle $article) => $action->handle($article));
    }
}
