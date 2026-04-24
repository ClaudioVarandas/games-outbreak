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
}
