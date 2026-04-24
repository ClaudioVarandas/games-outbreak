<?php

declare(strict_types=1);

namespace App\Services\Broadcasts\Formatters;

use App\Models\Video;
use App\Services\Broadcasts\Dtos\TelegramBroadcastPayload;

class VideoTelegramFormatter
{
    use EscapesMarkdownV2;

    private const MAX_CAPTION = 1024;

    public function format(Video $video): TelegramBroadcastPayload
    {
        $title = (string) ($video->title ?? '');
        $metaParts = array_filter([
            $video->channel_name,
            $video->durationFormatted(),
        ]);
        $meta = implode(' · ', $metaParts);

        $url = $video->watchUrl() ?? $video->url;

        $lines = [
            '🎬 *'.$this->escape($title).'*',
        ];

        if ($meta !== '') {
            $lines[] = '';
            $lines[] = $this->escape($meta);
        }

        $lines[] = '';
        $lines[] = '[Ver no YouTube →]('.$url.')';

        $caption = implode("\n", $lines);

        if (mb_strlen($caption) > self::MAX_CAPTION) {
            $caption = mb_substr($caption, 0, self::MAX_CAPTION - 1).'…';
        }

        return new TelegramBroadcastPayload(
            caption: $caption,
            photoUrl: TelegramBroadcastPayload::resolvePhotoUrl(
                $video->thumbnail_url ?: $video->thumbnailMaxRes() ?: $video->thumbnailHq()
            ),
        );
    }
}
