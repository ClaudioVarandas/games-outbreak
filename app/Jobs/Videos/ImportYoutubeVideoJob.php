<?php

declare(strict_types=1);

namespace App\Jobs\Videos;

use App\Actions\Videos\CreateVideo;
use App\Actions\Videos\FetchYoutubeVideoMetadata;
use App\Enums\VideoImportStatusEnum;
use App\Models\Video;
use App\Services\YoutubeDataService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ImportYoutubeVideoJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [10, 30, 90];

    public function __construct(
        public readonly string $url,
        public readonly int $userId,
        public readonly bool $shouldBroadcast = true,
    ) {}

    public function handle(
        YoutubeDataService $youtube,
        CreateVideo $createVideo,
        FetchYoutubeVideoMetadata $fetchMetadata,
    ): void {
        $youtubeId = $youtube->extractYoutubeId($this->url);

        if (! $youtubeId) {
            $createVideo->handle(
                url: $this->url,
                youtubeId: null,
                userId: $this->userId,
                status: VideoImportStatusEnum::Failed,
                failureReason: 'Could not extract a YouTube video ID from the URL.',
                shouldBroadcast: $this->shouldBroadcast,
            );

            return;
        }

        $existing = Video::where('youtube_id', $youtubeId)->first();

        if ($existing) {
            return;
        }

        $video = $createVideo->handle(
            url: $this->url,
            youtubeId: $youtubeId,
            userId: $this->userId,
            shouldBroadcast: $this->shouldBroadcast,
        );

        $fetchMetadata->handle($video);
    }
}
