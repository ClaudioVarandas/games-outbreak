<?php

declare(strict_types=1);

namespace App\Enums;

enum UserGameStatusEnum: string
{
    case Playing = 'playing';
    case Played = 'played';
    case Backlog = 'backlog';

    public function label(): string
    {
        return match ($this) {
            self::Playing => 'Playing',
            self::Played => 'Played',
            self::Backlog => 'Backlog',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Playing => 'gamepad',
            self::Played => 'trophy',
            self::Backlog => 'clock',
        };
    }

    public function colorClass(): string
    {
        return match ($this) {
            self::Playing => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
            self::Played => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
            self::Backlog => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Playing => 'bg-green-500',
            self::Played => 'bg-blue-500',
            self::Backlog => 'bg-yellow-500',
        };
    }
}
