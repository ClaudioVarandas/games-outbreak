<?php

declare(strict_types=1);

namespace App\Support;

readonly class PivotChange
{
    /**
     * @param  array<string, mixed>  $pivot  partial game_list_game payload to apply
     */
    public function __construct(
        public string $field,
        public string $label,
        public string $current,
        public string $suggested,
        public array $pivot,
    ) {}
}
