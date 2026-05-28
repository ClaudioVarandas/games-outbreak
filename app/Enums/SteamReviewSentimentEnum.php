<?php

declare(strict_types=1);

namespace App\Enums;

enum SteamReviewSentimentEnum: string
{
    case OverwhelminglyPositive = 'Overwhelmingly Positive';
    case VeryPositive = 'Very Positive';
    case Positive = 'Positive';
    case MostlyPositive = 'Mostly Positive';
    case Mixed = 'Mixed';
    case MostlyNegative = 'Mostly Negative';
    case Negative = 'Negative';
    case VeryNegative = 'Very Negative';
    case OverwhelminglyNegative = 'Overwhelmingly Negative';

    public function label(): string
    {
        return $this->value;
    }

    public function colorClass(): string
    {
        return match ($this) {
            self::OverwhelminglyPositive,
            self::VeryPositive,
            self::Positive,
            self::MostlyPositive => 'text-green-400 drop-shadow-[0_0_12px_rgba(74,222,128,0.5)]',
            self::Mixed => 'text-yellow-400 drop-shadow-[0_0_12px_rgba(250,204,21,0.5)]',
            self::MostlyNegative,
            self::Negative,
            self::VeryNegative,
            self::OverwhelminglyNegative => 'text-red-400 drop-shadow-[0_0_12px_rgba(248,113,113,0.5)]',
        };
    }

    public static function fromLabel(?string $label): ?self
    {
        if ($label === null) {
            return null;
        }

        return self::tryFrom($label);
    }
}
