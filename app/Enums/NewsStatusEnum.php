<?php

declare(strict_types=1);

namespace App\Enums;

enum NewsStatusEnum: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
            self::Archived => 'Archived',
        };
    }

    public function colorClass(): string
    {
        return match ($this) {
            self::Draft => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
            self::Published => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
            self::Archived => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        };
    }
}
