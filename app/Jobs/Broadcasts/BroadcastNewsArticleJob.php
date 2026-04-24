<?php

declare(strict_types=1);

namespace App\Jobs\Broadcasts;

use App\Enums\NewsArticleStatusEnum;
use App\Models\NewsArticle;
use App\Services\Broadcasts\Clients\TelegramClient;
use App\Services\Broadcasts\Formatters\NewsArticleTelegramFormatter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class BroadcastNewsArticleJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $newsArticleId,
        public readonly bool $force = false,
    ) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(TelegramClient $client, NewsArticleTelegramFormatter $formatter): void
    {
        if (! config('services.telegram.enabled')) {
            return;
        }

        $article = NewsArticle::with('localizations')->find($this->newsArticleId);

        if (! $article) {
            return;
        }

        if ($article->status !== NewsArticleStatusEnum::Published) {
            return;
        }

        if (! $article->should_broadcast) {
            return;
        }

        if (! $this->force && $article->broadcasted_at !== null) {
            return;
        }

        $locale = $formatter->resolveLocale($article);

        if (! $locale) {
            Log::warning('news.broadcast.skipped.no-locale', ['article_id' => $article->id]);

            return;
        }

        $payload = $formatter->format($article, $locale);

        $botToken = (string) config('services.telegram.bot_token');
        $chatId = (string) config('services.telegram.chat_id');

        if ($botToken === '' || $chatId === '') {
            Log::warning('news.broadcast.skipped.no-credentials', ['article_id' => $article->id]);

            return;
        }

        if ($payload->hasPhoto()) {
            $client->sendPhoto($botToken, $chatId, $payload->photoUrl, $payload->caption);
        } else {
            $client->sendMessage($botToken, $chatId, $payload->caption);
        }

        $article->update(['broadcasted_at' => now()]);
    }

    public function failed(Throwable $e): void
    {
        Log::error('news.broadcast.failed', [
            'article_id' => $this->newsArticleId,
            'force' => $this->force,
            'exception' => $e::class,
            'message' => $e->getMessage(),
        ]);
    }
}
