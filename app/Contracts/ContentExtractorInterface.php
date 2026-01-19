<?php

namespace App\Contracts;

interface ContentExtractorInterface
{
    /**
     * Extract article content from a URL.
     *
     * @param  string  $url  The URL to extract content from
     * @return array{title: string|null, content: string|null, image: string|null, summary: string|null, source_name: string|null}
     */
    public function extract(string $url): array;
}
