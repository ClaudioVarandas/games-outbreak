<?php

declare(strict_types=1);

namespace App\Actions\News;

use App\Enums\NewsImportStatusEnum;
use App\Models\NewsImport;

class CreateNewsImport
{
    public function handle(string $url, int $userId): NewsImport
    {
        $host = parse_url($url, PHP_URL_HOST);

        return NewsImport::create([
            'url' => $url,
            'source_domain' => $host ? preg_replace('/^www\./', '', $host) : null,
            'status' => NewsImportStatusEnum::Pending,
            'user_id' => $userId,
        ]);
    }
}
