<?php

declare(strict_types=1);

namespace App\Contracts;

interface NewsGenerationServiceInterface
{
    /**
     * Summarise and localise article content into pt-PT and pt-BR.
     *
     * @param  array{title: string, content: string, source: string}  $article
     * @return array<string, array{title: string, summary_short: string, summary_medium: string, body: array, seo_title: string, seo_description: string}>
     */
    public function summarizeAndLocalize(array $article): array;
}
