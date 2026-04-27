<?php

use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows ready active videos on /videos', function () {
    Video::factory()->ready()->create(['title' => 'Visible Trailer']);
    Video::factory()->ready()->inactive()->create(['title' => 'Hidden Trailer']);
    Video::factory()->failed()->create(['title' => 'Broken Trailer']);

    $this->get('/videos')
        ->assertOk()
        ->assertSee('Visible Trailer')
        ->assertDontSee('Hidden Trailer')
        ->assertDontSee('Broken Trailer');
});

it('renders an empty state when no videos are ready', function () {
    Video::factory()->count(2)->create();

    $this->get('/videos')
        ->assertOk()
        ->assertSee('No videos yet.');
});

it('emits canonical and OG tags', function () {
    Video::factory()->ready()->create();

    $this->get('/videos')
        ->assertOk()
        ->assertSee('<link rel="canonical"', false)
        ->assertSee('<meta property="og:type" content="website">', false)
        ->assertSee('BreadcrumbList', false);
});

it('paginates at 20 per page', function () {
    Video::factory()->ready()->count(25)->create();

    $this->get('/videos')
        ->assertOk()
        ->assertSee('page=2');
});

it('orders videos by created_at desc, ignoring published_at', function () {
    $older = Video::factory()->ready()->create([
        'title' => 'Older By Created',
        'created_at' => now()->subDays(2),
        'published_at' => now(),
    ]);
    $newer = Video::factory()->ready()->create([
        'title' => 'Newer By Created',
        'created_at' => now(),
        'published_at' => now()->subDays(5),
    ]);

    $html = $this->get('/videos')->assertOk()->getContent();

    expect(strpos($html, $newer->title))->toBeLessThan(strpos($html, $older->title));
});

it('renders the category badge on public cards', function () {
    Video::factory()->ready()->forCategory('gameplay')->create(['title' => 'Gameplay Clip']);

    $this->get('/videos')
        ->assertOk()
        ->assertSee('Gameplay Clip')
        ->assertSee('GAMEPLAY', false)
        ->assertSee('neon-category-pill', false);
});
