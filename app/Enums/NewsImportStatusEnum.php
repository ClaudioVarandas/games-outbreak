<?php

declare(strict_types=1);

namespace App\Enums;

enum NewsImportStatusEnum: string
{
    case Pending = 'pending';
    case Fetching = 'fetching';
    case Extracted = 'extracted';
    case Generating = 'generating';
    case Ready = 'ready';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Fetching => 'Fetching',
            self::Extracted => 'Extracted',
            self::Generating => 'Generating',
            self::Ready => 'Ready',
            self::Failed => 'Failed',
        };
    }

    public function colorClass(): string
    {
        return match ($this) {
            self::Pending => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
            self::Fetching => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
            self::Extracted => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200',
            self::Generating => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
            self::Ready => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
            self::Failed => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
        };
    }

    public function isFinal(): bool
    {
        return match ($this) {
            self::Ready, self::Failed => true,
            default => false,
        };
    }
}
