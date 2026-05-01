<?php

declare(strict_types=1);

namespace App\Services\Broadcasts\Channels;

use App\Services\MonthlyChoicesPayload;

interface MonthlyBroadcastChannel
{
    public function name(): string;

    public function isEnabled(): bool;

    public function send(MonthlyChoicesPayload $payload): void;

    public function preview(MonthlyChoicesPayload $payload): string;
}
