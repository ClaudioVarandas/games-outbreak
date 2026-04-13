<?php

use App\Actions\News\ExtractNewsArticle;
use App\Actions\News\GenerateLocalizedNewsContent;
use App\Contracts\ContentExtractorInterface;
use App\Enums\NewsArticleStatusEnum;
use App\Enums\NewsImportStatusEnum;
use App\Models\NewsImport;
use App\Models\User;
use App\Services\AnthropicNewsGenerationService;
use App\Support\News\MarkdownToTiptapConverter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('runs full pipeline: url → extract → generate → article in review', function () {
    config([
        'services.anthropic.api_key' => 'test-key',
        'services.anthropic.model' => 'claude-haiku-4-5-20251001',
        'services.anthropic.version' => '2023-06-01',
    ]);

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [[
                'type' => 'text',
                'text' => json_encode([
                    'en' => [
                        'title' => 'Game EN',
                        'summary_short' => 'Short EN.',
                        'summary_medium' => 'Medium EN.',
                        'body_markdown' => "## Overview\n\nContent EN.",
                        'seo_title' => 'SEO EN',
                        'seo_description' => 'Desc EN.',
                    ],
                    'pt-PT' => [
                        'title' => 'Jogo PT',
                        'summary_short' => 'Curto PT.',
                        'summary_medium' => 'Médio PT.',
                        'body_markdown' => "## Título\n\nConteúdo PT.",
                        'seo_title' => 'SEO PT',
                        'seo_description' => 'Desc PT.',
                    ],
                    'pt-BR' => [
                        'title' => 'Jogo BR',
                        'summary_short' => 'Curto BR.',
                        'summary_medium' => 'Médio BR.',
                        'body_markdown' => "## Título\n\nConteúdo BR.",
                        'seo_title' => 'SEO BR',
                        'seo_description' => 'Desc BR.',
                    ],
                ]),
            ]],
        ], 200),
    ]);

    $user = User::factory()->create();
    $import = NewsImport::factory()->create(['user_id' => $user->id]);

    $extractor = $this->mock(ContentExtractorInterface::class);
    $extractor->shouldReceive('extract')
        ->once()
        ->andReturn([
            'title' => 'New Game Released for 2026',
            'content' => "## Overview\n\nBig announcement today.",
            'image' => 'https://cdn.example.com/game.jpg',
            'summary' => 'Big announcement.',
            'source_name' => 'IGN',
        ]);

    (new ExtractNewsArticle($extractor))->handle($import);

    expect($import->fresh()->status)->toBe(NewsImportStatusEnum::Extracted);

    $aiService = new AnthropicNewsGenerationService(new MarkdownToTiptapConverter);
    $article = (new GenerateLocalizedNewsContent($aiService))->handle($import->fresh());

    expect($import->fresh()->status)->toBe(NewsImportStatusEnum::Ready);
    expect($article->status)->toBe(NewsArticleStatusEnum::Review);
    expect($article->localizations)->toHaveCount(3);

    $en = $article->localizations->firstWhere('locale', 'en');
    expect($en->title)->toBe('Game EN');
    expect($en->body)->toBeArray();
    expect($en->body['type'])->toBe('doc');

    $ptPt = $article->localizations->firstWhere('locale', 'pt-PT');
    expect($ptPt->title)->toBe('Jogo PT');
    expect($ptPt->body['type'])->toBe('doc');

    $ptBr = $article->localizations->firstWhere('locale', 'pt-BR');
    expect($ptBr->title)->toBe('Jogo BR');
});
