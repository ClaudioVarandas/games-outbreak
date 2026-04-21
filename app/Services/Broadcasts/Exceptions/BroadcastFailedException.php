<?php

declare(strict_types=1);

namespace App\Services\Broadcasts\Exceptions;

use RuntimeException;
use Throwable;

class BroadcastFailedException extends RuntimeException
{
    /**
     * @param  array<string, Throwable>  $channelFailures  keyed by channel name
     */
    public function __construct(public readonly array $channelFailures)
    {
        $summary = collect($channelFailures)
            ->map(fn (Throwable $e, string $channel) => "{$channel}: {$e->getMessage()}")
            ->implode(' | ');

        parent::__construct("Broadcast failed on all channels — {$summary}");
    }
}
