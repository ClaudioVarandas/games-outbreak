<?php

declare(strict_types=1);

namespace App\Actions\Videos;

use App\Enums\VideoImportStatusEnum;
use App\Models\Video;
use App\Services\YoutubeDataService;
use Illuminate\Support\Facades\Log;

class FetchYoutubeVideoMetadata
{
    public function __construct(
        private readonly YoutubeDataService $youtube,
        private readonly MaybeBroadcastVideo $maybeBroadcast,
    ) {}

    public function handle(Video $video): void
    {
        if (! $video->youtube_id) {
            $video->markAs(VideoImportStatusEnum::Failed, 'Missing YouTube ID.');

            return;
        }

        $video->markAs(VideoImportStatusEnum::Fetching);

        try {
            $data = $this->youtube->fetchVideo($video->youtube_id);

            $video->update([
                'status' => VideoImportStatusEnum::Ready,
                'failure_reason' => null,
                'title' => $data['title'],
                'channel_name' => $data['channel_name'],
                'channel_id' => $data['channel_id'],
                'duration_seconds' => $data['duration_seconds'],
                'thumbnail_url' => $data['thumbnail_url'],
                'description' => $data['description'],
                'published_at' => $data['published_at'],
                'raw_api_response' => $data['raw'],
            ]);

            $this->maybeBroadcast->handle($video->fresh());
        } catch (\Throwable $e) {
            Log::warning('YouTube video metadata fetch failed', [
                'video_id' => $video->id,
                'youtube_id' => $video->youtube_id,
                'error' => $e->getMessage(),
            ]);
            $video->markAs(VideoImportStatusEnum::Failed, $e->getMessage());
        }
    }
}
