<?php

declare(strict_types=1);

namespace App\Jobs\Broadcasts;

use App\Enums\VideoImportStatusEnum;
use App\Models\Video;
use App\Services\Broadcasts\Clients\TelegramClient;
use App\Services\Broadcasts\Formatters\VideoTelegramFormatter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class BroadcastVideoJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $videoId,
        public readonly bool $force = false,
    ) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(TelegramClient $client, VideoTelegramFormatter $formatter): void
    {
        if (! config('services.telegram.enabled')) {
            return;
        }

        $video = Video::find($this->videoId);

        if (! $video) {
            return;
        }

        if ($video->status !== VideoImportStatusEnum::Ready) {
            return;
        }

        if (! $video->is_active) {
            return;
        }

        if (! $video->should_broadcast) {
            return;
        }

        if (! $this->force && $video->broadcasted_at !== null) {
            return;
        }

        $payload = $formatter->format($video);

        $botToken = (string) config('services.telegram.bot_token');
        $chatId = (string) config('services.telegram.chat_id');

        if ($botToken === '' || $chatId === '') {
            Log::warning('video.broadcast.skipped.no-credentials', ['video_id' => $video->id]);

            return;
        }

        if ($payload->hasPhoto()) {
            $client->sendPhoto($botToken, $chatId, $payload->photoUrl, $payload->caption);
        } else {
            $client->sendMessage($botToken, $chatId, $payload->caption);
        }

        $video->update(['broadcasted_at' => now()]);
    }

    public function failed(Throwable $e): void
    {
        Log::error('video.broadcast.failed', [
            'video_id' => $this->videoId,
            'force' => $this->force,
            'exception' => $e::class,
            'message' => $e->getMessage(),
        ]);
    }
}
