<?php

declare(strict_types=1);

namespace App\Actions\Videos;

use App\Enums\VideoImportStatusEnum;
use App\Models\Video;

class CreateVideo
{
    public function handle(string $url, ?string $youtubeId, int $userId, VideoImportStatusEnum $status = VideoImportStatusEnum::Pending, ?string $failureReason = null): Video
    {
        return Video::create([
            'url' => $url,
            'youtube_id' => $youtubeId,
            'status' => $status,
            'failure_reason' => $failureReason,
            'user_id' => $userId,
        ]);
    }
}
