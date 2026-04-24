<?php

declare(strict_types=1);

namespace App\Services\Broadcasts\Formatters;

use App\Enums\NewsLocaleEnum;
use App\Models\NewsArticle;
use App\Services\Broadcasts\Dtos\TelegramBroadcastPayload;

class NewsArticleTelegramFormatter
{
    use EscapesMarkdownV2;

    private const MAX_CAPTION = 1024;

    public function format(NewsArticle $article, NewsLocaleEnum $locale): TelegramBroadcastPayload
    {
        $localization = $article->localization($locale->value);

        $title = $localization?->title ?? $article->original_title ?? '';
        $summary = $localization?->summary_short ?? '';

        $url = $locale->articleUrl($article);

        $caption = $this->buildCaption($title, $summary, $url);

        return new TelegramBroadcastPayload(
            caption: $caption,
            photoUrl: $article->featured_image_url ?: null,
        );
    }

    /**
     * Resolve the best locale for broadcasting.
     * Order: pt-PT → pt-BR → EN. Returns null if none has both a slug and a localization.
     */
    public function resolveLocale(NewsArticle $article): ?NewsLocaleEnum
    {
        $article->loadMissing('localizations');

        foreach ([NewsLocaleEnum::PtPt, NewsLocaleEnum::PtBr, NewsLocaleEnum::En] as $candidate) {
            $slug = $article->{$candidate->slugColumn()} ?? null;
            if (! $slug) {
                continue;
            }
            $loc = $article->localization($candidate->value);
            if ($loc && ! empty($loc->title)) {
                return $candidate;
            }
        }

        return null;
    }

    private function buildCaption(string $title, string $summary, string $url): string
    {
        $lines = [
            '📰 *'.$this->escape($title).'*',
        ];

        if ($summary !== '') {
            $lines[] = '';
            $lines[] = $this->escape($summary);
        }

        $lines[] = '';
        $lines[] = '[Ler mais →]('.$url.')';

        $caption = implode("\n", $lines);

        if (mb_strlen($caption) > self::MAX_CAPTION) {
            $caption = mb_substr($caption, 0, self::MAX_CAPTION - 1).'…';
        }

        return $caption;
    }
}
