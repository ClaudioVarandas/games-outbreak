<?php

declare(strict_types=1);

namespace App\DTOs;

readonly class ExternalSourceData
{
    public function __construct(
        public int $sourceId,
        public string $sourceName,
        public string $externalUid,
        public ?string $externalUrl,
        public ?int $category,
    ) {}

    public function toArray(): array
    {
        return [
            'source_id' => $this->sourceId,
            'source_name' => $this->sourceName,
            'external_uid' => $this->externalUid,
            'external_url' => $this->externalUrl,
            'category' => $this->category,
        ];
    }
}
