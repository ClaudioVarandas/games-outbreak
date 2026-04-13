<?php

declare(strict_types=1);

namespace App\Enums;

enum NewsLocaleEnum: string
{
    case PtPt = 'pt-PT';
    case PtBr = 'pt-BR';

    public function label(): string
    {
        return match ($this) {
            self::PtPt => 'Português (Portugal)',
            self::PtBr => 'Português (Brasil)',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::PtPt => 'PT',
            self::PtBr => 'BR',
        };
    }

    public function slugPrefix(): string
    {
        return match ($this) {
            self::PtPt => 'pt-pt',
            self::PtBr => 'pt-br',
        };
    }
}
