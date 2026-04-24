<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\Broadcasts\BroadcastNewsArticleJob;
use App\Jobs\Broadcasts\BroadcastVideoJob;
use App\Models\NewsArticle;
use App\Models\Video;
use Illuminate\Console\Command;

class ResendBroadcastCommand extends Command
{
    protected $signature = 'broadcast:resend
        {type : Record type — news|video}
        {id : Model primary key}
        {--channel=telegram : Broadcast channel — telegram (x not implemented)}';

    protected $description = 'Re-send a broadcast for a news article or video. Bypasses the already-broadcast guard; useful for testing or as a fallback.';

    public function handle(): int
    {
        $type = strtolower((string) $this->argument('type'));
        $id = (int) $this->argument('id');
        $channel = strtolower((string) $this->option('channel'));

        if (! in_array($channel, ['telegram', 'x'], true)) {
            $this->error("Unknown channel '{$channel}'. Allowed: telegram, x.");

            return self::INVALID;
        }

        if ($channel === 'x') {
            $this->error('X channel is not implemented yet.');

            return self::INVALID;
        }

        return match ($type) {
            'news' => $this->resendNews($id),
            'video' => $this->resendVideo($id),
            default => $this->invalidType($type),
        };
    }

    private function resendNews(int $id): int
    {
        $article = NewsArticle::find($id);

        if (! $article) {
            $this->error("News article #{$id} not found.");

            return self::FAILURE;
        }

        BroadcastNewsArticleJob::dispatch($article->id, force: true);

        $this->info("Queued re-broadcast for news article #{$article->id}: \"".($article->original_title ?? '(no title)').'"');

        return self::SUCCESS;
    }

    private function resendVideo(int $id): int
    {
        $video = Video::find($id);

        if (! $video) {
            $this->error("Video #{$id} not found.");

            return self::FAILURE;
        }

        BroadcastVideoJob::dispatch($video->id, force: true);

        $this->info("Queued re-broadcast for video #{$video->id}: \"".($video->title ?? '(no title)').'"');

        return self::SUCCESS;
    }

    private function invalidType(string $type): int
    {
        $this->error("Unknown type '{$type}'. Allowed: news, video.");

        return self::INVALID;
    }
}
