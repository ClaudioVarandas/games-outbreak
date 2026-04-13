<?php

declare(strict_types=1);

namespace App\Enums;

enum NewsArticleStatusEnum: string
{
    case Draft = 'draft';
    case Review = 'review';
    case Approved = 'approved';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Review => 'Review',
            self::Approved => 'Approved',
            self::Scheduled => 'Scheduled',
            self::Published => 'Published',
            self::Archived => 'Archived',
        };
    }

    public function colorClass(): string
    {
        return match ($this) {
            self::Draft => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
            self::Review => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
            self::Approved => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
            self::Scheduled => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
            self::Published => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
            self::Archived => 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
        };
    }

    public function isPubliclyVisible(): bool
    {
        return $this === self::Published;
    }
}
