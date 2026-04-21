<?php

declare(strict_types=1);

namespace App\Services\Broadcasts\Channels;

use App\Services\WeeklyChoicesPayload;

interface BroadcastChannel
{
    public function name(): string;

    public function isEnabled(): bool;

    public function send(WeeklyChoicesPayload $payload): void;

    public function preview(WeeklyChoicesPayload $payload): string;
}
