<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\Carbon;

readonly class ReleaseDateSuggestion
{
    public function __construct(
        public ?Carbon $releaseDate,
        public bool $isTba,
        public ?int $releaseYear,
        public ?string $human = null,
    ) {}

    public static function concrete(Carbon $date, ?string $human = null): self
    {
        return new self($date, false, null, $human);
    }

    public static function tba(?int $year, ?string $human = null): self
    {
        return new self(null, true, $year, $human);
    }

    public function label(): string
    {
        if (! $this->isTba && $this->releaseDate) {
            return $this->releaseDate->format('M j, Y');
        }

        return $this->releaseYear ? 'TBA '.$this->releaseYear : 'TBA';
    }

    /**
     * Pivot payload that respects the game_list_game invariant:
     * a concrete date clears the TBA flag/year; a TBA clears the date.
     *
     * @return array{release_date: ?string, is_tba: bool, release_year: ?int}
     */
    public function toPivot(): array
    {
        return [
            'release_date' => $this->isTba ? null : $this->releaseDate?->toDateTimeString(),
            'is_tba' => $this->isTba,
            'release_year' => $this->isTba ? $this->releaseYear : null,
        ];
    }
}
