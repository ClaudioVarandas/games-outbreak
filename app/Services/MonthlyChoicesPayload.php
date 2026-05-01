<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class MonthlyChoicesPayload
{
    public function __construct(
        public readonly CarbonImmutable $windowStart,
        public readonly CarbonImmutable $windowEnd,
        public readonly Collection $games,
        public readonly string $ctaUrl,
        public readonly bool $isPreview = false,
    ) {}

    public function isEmpty(): bool
    {
        return $this->games->isEmpty();
    }

    public function count(): int
    {
        return $this->games->count();
    }
}
