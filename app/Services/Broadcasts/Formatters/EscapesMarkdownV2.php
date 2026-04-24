<?php

declare(strict_types=1);

namespace App\Services\Broadcasts\Formatters;

trait EscapesMarkdownV2
{
    protected function escape(string $value): string
    {
        $specials = ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];

        return str_replace(
            $specials,
            array_map(fn (string $ch) => '\\'.$ch, $specials),
            $value,
        );
    }
}
