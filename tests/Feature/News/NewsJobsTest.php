<?php

use App\Actions\News\ExtractNewsArticle;
use App\Enums\NewsImportStatusEnum;
use App\Jobs\News\ExtractNewsArticleJob;
use App\Jobs\News\GenerateNewsContentJob;
use App\Jobs\News\ImportNewsUrlJob;
use App\Models\NewsImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('ImportNewsUrlJob creates an import and dispatches ExtractNewsArticleJob', function () {
    Queue::fake([ExtractNewsArticleJob::class]);
    $user = User::factory()->create();

    dispatch(new ImportNewsUrlJob('https://ign.com/articles/test', $user->id));

    Queue::assertPushed(ExtractNewsArticleJob::class);
    $this->assertDatabaseHas('news_imports', ['url' => 'https://ign.com/articles/test']);
});

it('ExtractNewsArticleJob dispatches GenerateNewsContentJob on extracted status', function () {
    Queue::fake([GenerateNewsContentJob::class]);

    $import = NewsImport::factory()->create();

    $mock = $this->mock(ExtractNewsArticle::class);
    $mock->shouldReceive('handle')
        ->once()
        ->andReturnUsing(function (NewsImport $imp) {
            $imp->update(['status' => NewsImportStatusEnum::Extracted]);
        });

    dispatch(new ExtractNewsArticleJob($import));

    Queue::assertPushed(GenerateNewsContentJob::class);
});

it('ExtractNewsArticleJob does not dispatch GenerateNewsContentJob on failed status', function () {
    Queue::fake([GenerateNewsContentJob::class]);

    $import = NewsImport::factory()->failed()->create();

    $mock = $this->mock(ExtractNewsArticle::class);
    $mock->shouldReceive('handle')->once();

    dispatch(new ExtractNewsArticleJob($import));

    Queue::assertNotPushed(GenerateNewsContentJob::class);
});
