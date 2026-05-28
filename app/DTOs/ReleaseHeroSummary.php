<?php

declare(strict_types=1);

namespace App\DTOs;

final class ReleaseHeroSummary
{
    public function __construct(
        public ReleaseHeroLine $primary,
        public ?ReleaseHeroLine $secondary = null,
        public ?string $note = null,
    ) {}
}
