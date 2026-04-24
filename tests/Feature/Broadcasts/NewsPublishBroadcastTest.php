<?php

use App\Actions\News\PublishNewsArticle;
use App\Jobs\Broadcasts\BroadcastNewsArticleJob;
use App\Models\NewsArticle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['features.news' => true]);
    Bus::fake();
});

it('publishes an article and dispatches a broadcast when should_broadcast is on', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $article = NewsArticle::factory()->create(['should_broadcast' => true, 'broadcasted_at' => null]);

    $this->actingAs($admin)
        ->post(route('admin.news-articles.publish', $article), ['should_broadcast' => '1'])
        ->assertRedirect();

    Bus::assertDispatched(BroadcastNewsArticleJob::class, fn ($j) => $j->newsArticleId === $article->id);
});

it('publishes an article without broadcast when should_broadcast is off', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $article = NewsArticle::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.news-articles.publish', $article))
        ->assertRedirect();

    Bus::assertNotDispatched(BroadcastNewsArticleJob::class);
    expect($article->fresh()->should_broadcast)->toBeFalse();
});

it('does not re-dispatch for an already broadcasted article', function () {
    $article = NewsArticle::factory()->create([
        'should_broadcast' => true,
        'broadcasted_at' => now()->subHour(),
    ]);

    app(PublishNewsArticle::class)->handle($article);

    Bus::assertNotDispatched(BroadcastNewsArticleJob::class);
});
