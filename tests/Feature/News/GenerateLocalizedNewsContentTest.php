<?php

use App\Contracts\NewsGenerationServiceInterface;
use App\Services\AnthropicNewsGenerationService;
use App\Services\OpenAiNewsGenerationService;
use App\Support\News\MarkdownToTiptapConverter;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.anthropic.api_key' => 'test-key',
        'services.anthropic.model' => 'claude-haiku-4-5-20251001',
        'services.anthropic.version' => '2023-06-01',
    ]);
});

/** Returns a 3-locale payload (en + pt-PT + pt-BR). */
function threeLocalePayload(): array
{
    return [
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
            'body_markdown' => "## Visão\n\nTexto PT.",
            'seo_title' => 'SEO PT',
            'seo_description' => 'Desc PT.',
        ],
        'pt-BR' => [
            'title' => 'Jogo BR',
            'summary_short' => 'Curto BR.',
            'summary_medium' => 'Médio BR.',
            'body_markdown' => "## Visão\n\nTexto BR.",
            'seo_title' => 'SEO BR',
            'seo_description' => 'Desc BR.',
        ],
    ];
}

it('implements NewsGenerationServiceInterface', function () {
    $service = new AnthropicNewsGenerationService(new MarkdownToTiptapConverter);

    expect($service)->toBeInstanceOf(NewsGenerationServiceInterface::class);
});

it('calls Anthropic and returns all three locales with converted body', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [[
                'type' => 'text',
                'text' => json_encode(threeLocalePayload()),
            ]],
        ], 200),
    ]);

    $service = new AnthropicNewsGenerationService(new MarkdownToTiptapConverter);
    $result = $service->summarizeAndLocalize([
        'title' => 'Game Announced',
        'content' => "## Overview\n\nContent.",
        'source' => 'IGN',
    ]);

    expect($result)->toHaveKeys(['en', 'pt-PT', 'pt-BR']);
    expect($result['en']['title'])->toBe('Game EN');
    expect($result['en']['body'])->toBeArray();
    expect($result['en']['body']['type'])->toBe('doc');
    expect($result['pt-PT']['title'])->toBe('Jogo PT');
    expect($result['pt-PT']['body']['type'])->toBe('doc');
    expect($result['pt-BR']['title'])->toBe('Jogo BR');
});

it('throws RuntimeException on Anthropic API failure', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response(['error' => ['message' => 'Unauthorized']], 401),
    ]);

    $service = new AnthropicNewsGenerationService(new MarkdownToTiptapConverter);

    expect(fn () => $service->summarizeAndLocalize([
        'title' => 'Test',
        'content' => 'Content',
        'source' => 'Test',
    ]))->toThrow(RuntimeException::class);
});

it('throws RuntimeException when response missing locale keys', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [[
                'type' => 'text',
                'text' => json_encode(['wrong_key' => []]),
            ]],
        ], 200),
    ]);

    $service = new AnthropicNewsGenerationService(new MarkdownToTiptapConverter);

    expect(fn () => $service->summarizeAndLocalize([
        'title' => 'Test',
        'content' => 'Content',
        'source' => 'Test',
    ]))->toThrow(RuntimeException::class);
});

it('can be resolved from container via interface binding', function () {
    config(['services.news_ai_provider' => 'anthropic']);

    $service = app(NewsGenerationServiceInterface::class);

    expect($service)->toBeInstanceOf(AnthropicNewsGenerationService::class);
});

// ============================================================
// OpenAiNewsGenerationService
// ============================================================

it('OpenAiNewsGenerationService implements NewsGenerationServiceInterface', function () {
    $service = new OpenAiNewsGenerationService(new MarkdownToTiptapConverter);

    expect($service)->toBeInstanceOf(NewsGenerationServiceInterface::class);
});

it('OpenAiNewsGenerationService calls OpenAI and returns all three locales with converted body', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode(threeLocalePayload()),
                ],
            ]],
        ], 200),
    ]);

    $service = new OpenAiNewsGenerationService(new MarkdownToTiptapConverter);
    $result = $service->summarizeAndLocalize([
        'title' => 'Game Announced',
        'content' => "## Overview\n\nContent.",
        'source' => 'IGN',
    ]);

    expect($result)->toHaveKeys(['en', 'pt-PT', 'pt-BR']);
    expect($result['en']['title'])->toBe('Game EN');
    expect($result['en']['body']['type'])->toBe('doc');
    expect($result['pt-PT']['title'])->toBe('Jogo PT');
    expect($result['pt-PT']['body']['type'])->toBe('doc');
    expect($result['pt-BR']['title'])->toBe('Jogo BR');
});

it('OpenAiNewsGenerationService throws RuntimeException on API failure', function () {
    Http::fake([
        'api.openai.com/*' => Http::response(['error' => ['message' => 'Invalid API key']], 401),
    ]);

    $service = new OpenAiNewsGenerationService(new MarkdownToTiptapConverter);

    expect(fn () => $service->summarizeAndLocalize([
        'title' => 'Test',
        'content' => 'Content',
        'source' => 'Test',
    ]))->toThrow(RuntimeException::class);
});

it('OpenAiNewsGenerationService throws RuntimeException when response missing locale keys', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [[
                'message' => ['content' => json_encode(['wrong_key' => []])],
            ]],
        ], 200),
    ]);

    $service = new OpenAiNewsGenerationService(new MarkdownToTiptapConverter);

    expect(fn () => $service->summarizeAndLocalize([
        'title' => 'Test',
        'content' => 'Content',
        'source' => 'Test',
    ]))->toThrow(RuntimeException::class);
});

it('resolves OpenAiNewsGenerationService from container when provider is openai', function () {
    config(['services.news_ai_provider' => 'openai']);

    $service = app(NewsGenerationServiceInterface::class);

    expect($service)->toBeInstanceOf(OpenAiNewsGenerationService::class);
});
