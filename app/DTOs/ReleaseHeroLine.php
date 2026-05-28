<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\ReleaseHeroVariantEnum;

final class ReleaseHeroLine
{
    /**
     * @param  string[]  $platforms
     */
    public function __construct(
        public string $label,
        public ReleaseHeroVariantEnum $variant,
        public array $platforms = [],
        public ?string $date = null,
        public string $description = '',
    ) {}
}
