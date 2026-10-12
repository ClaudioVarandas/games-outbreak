<?php

declare(strict_types=1);

namespace App\Enums;

enum PcPortSupportStateEnum: string
{
    case Supported = 'supported';
    case Experimental = 'experimental';
    case Unsupported = 'unsupported';
    case Unknown = 'unknown';

    /**
     * CSV support columns hold `true`, `false`, blank, or a free-form experimental note.
     */
    public static function fromCsvValue(?string $value): self
    {
        return match (true) {
            $value === 'true' => self::Supported,
            $value === 'false' => self::Unsupported,
            blank($value) => self::Unknown,
            default => self::Experimental,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Supported => 'Supported',
            self::Experimental => 'Experimental',
            self::Unsupported => 'Not supported',
            self::Unknown => 'Support not confirmed',
        };
    }

    public function pillClasses(): string
    {
        return match ($this) {
            self::Supported => 'border-orange-400/30 bg-orange-400/15 text-orange-300',
            self::Experimental => 'border-amber-400/30 bg-amber-400/15 text-amber-300',
            self::Unsupported, self::Unknown => 'border-slate-400/20 bg-slate-400/10 text-slate-500',
        };
    }
}
