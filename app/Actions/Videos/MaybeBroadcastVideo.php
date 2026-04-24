<?php

declare(strict_types=1);

namespace App\Actions\Videos;

use App\Enums\VideoImportStatusEnum;
use App\Jobs\Broadcasts\BroadcastVideoJob;
use App\Models\Video;

class MaybeBroadcastVideo
{
    public function handle(Video $video): void
    {
        if ($video->status !== VideoImportStatusEnum::Ready) {
            return;
        }

        if (! $video->is_active) {
            return;
        }

        if (! $video->should_broadcast) {
            return;
        }

        if ($video->broadcasted_at !== null) {
            return;
        }

        BroadcastVideoJob::dispatch($video->id);
    }
}
