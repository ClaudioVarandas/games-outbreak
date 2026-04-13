<?php

use App\Actions\News\ExtractNewsArticle;
use App\Contracts\ContentExtractorInterface;
use App\Enums\NewsImportStatusEnum;
use App\Models\NewsImport;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('updates import with raw content on success', function () {
    $import = NewsImport::factory()->create(['status' => NewsImportStatusEnum::Pending]);

    $extractor = $this->mock(ContentExtractorInterface::class);
    $extractor->shouldReceive('extract')
        ->once()
        ->with($import->url)
        ->andReturn([
            'title' => 'Test Article',
            'content' => "## Overview\n\nSome content here.",
            'image' => 'https://cdn.example.com/img.jpg',
            'summary' => 'Short summary.',
            'source_name' => 'IGN',
        ]);

    $action = new ExtractNewsArticle($extractor);
    $action->handle($import);

    $import->refresh();
    expect($import->status)->toBe(NewsImportStatusEnum::Extracted);
    expect($import->raw_title)->toBe('Test Article');
    expect($import->raw_image_url)->toBe('https://cdn.example.com/img.jpg');
});

it('marks import as failed when extractor throws', function () {
    $import = NewsImport::factory()->create();

    $extractor = $this->mock(ContentExtractorInterface::class);
    $extractor->shouldReceive('extract')
        ->once()
        ->andThrow(new RuntimeException('Domain blocked'));

    $action = new ExtractNewsArticle($extractor);
    $action->handle($import);

    expect($import->fresh()->status)->toBe(NewsImportStatusEnum::Failed);
    expect($import->fresh()->failure_reason)->toContain('Domain blocked');
});
