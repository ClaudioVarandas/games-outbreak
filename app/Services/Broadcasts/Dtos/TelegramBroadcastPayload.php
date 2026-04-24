<?php

declare(strict_types=1);

namespace App\Services\Broadcasts\Dtos;

final readonly class TelegramBroadcastPayload
{
    public function __construct(
        public string $caption,
        public ?string $photoUrl = null,
    ) {}

    public function hasPhoto(): bool
    {
        return $this->photoUrl !== null && $this->photoUrl !== '';
    }

    /**
     * Normalize a stored image URL into an absolute http(s) URL Telegram can fetch,
     * or null when it can't be resolved. Relative paths are expanded against APP_URL.
     */
    public static function resolvePhotoUrl(?string $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $parsed = parse_url($raw);
        if (! empty($parsed['host']) && in_array($parsed['scheme'] ?? '', ['http', 'https'], true)) {
            return $raw;
        }

        if (str_starts_with($raw, '/')) {
            return url($raw);
        }

        return null;
    }
}
