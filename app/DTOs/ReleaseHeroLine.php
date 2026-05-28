<?php

declare(strict_types=1);

namespace App\DTOs;

final class ReleaseHeroLine
{
    /**
     * @param  string[]  $platforms
     */
    public function __construct(
        public string $label,
        public string $variant,      // success | upcoming | early_access | tba
        public array $platforms = [],
        public ?string $date = null,
        public string $description = '',
    ) {}
}
