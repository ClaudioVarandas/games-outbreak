<?php

use App\Models\NewsArticleLocalization;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function bodyWithRawLink(): array
{
    return [
        'type' => 'doc',
        'content' => [
            [
                'type' => 'paragraph',
                'content' => [
                    ['type' => 'text', 'text' => 'Source: [Phoronix](https://a.test), more text'],
                ],
            ],
        ],
    ];
}

it('converts raw markdown links in stored bodies into link marks', function () {
    $localization = NewsArticleLocalization::factory()->create(['body' => bodyWithRawLink()]);

    $this->artisan('news:fix-markdown-links')->assertSuccessful();

    $nodes = $localization->fresh()->body['content'][0]['content'];

    expect($nodes[0])->toBe(['type' => 'text', 'text' => 'Source: '])
        ->and($nodes[1])->toBe([
            'type' => 'text',
            'text' => 'Phoronix',
            'marks' => [['type' => 'link', 'attrs' => ['href' => 'https://a.test']]],
        ])
        ->and($nodes[2])->toBe(['type' => 'text', 'text' => ', more text']);
});

it('does not modify bodies on a dry run', function () {
    $localization = NewsArticleLocalization::factory()->create(['body' => bodyWithRawLink()]);

    $this->artisan('news:fix-markdown-links', ['--dry-run' => true])->assertSuccessful();

    expect($localization->fresh()->body)->toBe(bodyWithRawLink());
});

it('leaves bodies that already use link marks untouched (idempotent)', function () {
    $clean = [
        'type' => 'doc',
        'content' => [[
            'type' => 'paragraph',
            'content' => [
                ['type' => 'text', 'text' => 'See '],
                ['type' => 'text', 'text' => 'Phoronix', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'https://a.test']]]],
            ],
        ]],
    ];

    $localization = NewsArticleLocalization::factory()->create(['body' => $clean]);

    $this->artisan('news:fix-markdown-links')->assertSuccessful();

    expect($localization->fresh()->body)->toBe($clean);
});
