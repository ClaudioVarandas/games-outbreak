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
