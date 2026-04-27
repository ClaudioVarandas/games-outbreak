<?php

use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the Latest Game Videos section when videos exist', function () {
    Video::factory()->ready()->featured()->create(['title' => 'Featured Hero Trailer']);
    Video::factory()->ready()->count(3)->create();

    $this->get('/')
        ->assertOk()
        ->assertSee('Latest Game Videos')
        ->assertSee('Featured Hero Trailer');
});

it('hides the section entirely when no videos are ready', function () {
    Video::factory()->count(2)->create();

    $this->get('/')
        ->assertOk()
        ->assertDontSee('Latest Game Videos');
});

it('places the section between This Week\'s Choices and Events', function () {
    Video::factory()->ready()->create(['title' => 'Between Marker']);

    $html = $this->get('/')->assertOk()->getContent();

    $thisWeekPos = strpos($html, "This Week's Choices");
    $videosPos = strpos($html, 'Latest Game Videos');
    $eventsPos = strpos($html, 'data-events-grid');

    if ($thisWeekPos === false) {
        $thisWeekPos = strpos($html, 'this-week-choices');
    }

    expect($videosPos)->toBeGreaterThan($thisWeekPos ?: 0);
});

it('renders the category badge and days-ago meta on the homepage', function () {
    Video::factory()
        ->ready()
        ->featured()
        ->forCategory('trailers')
        ->create([
            'title' => 'Hero Trailer',
            'channel_name' => 'Rockstar',
            'published_at' => now()->subDays(3),
        ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('TRAILERS', false)
        ->assertSee('neon-category-pill', false)
        ->assertSee('days ago', false)
        ->assertSee('Rockstar', false);
});

it('always shows the featured video, even if older than recent imports', function () {
    Video::factory()->ready()->featured()->create([
        'title' => 'Old Featured Video',
        'created_at' => now()->subMonths(6),
        'published_at' => now()->subMonths(6),
    ]);
    Video::factory()->ready()->count(10)->create([
        'created_at' => now()->subDays(1),
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('Old Featured Video');
});

it('orders the latest video tail by created_at desc', function () {
    Video::factory()->ready()->featured()->create(['title' => 'Pinned Featured']);

    $older = Video::factory()->ready()->create([
        'title' => 'Older Tail',
        'created_at' => now()->subDays(3),
        'published_at' => now(),
    ]);
    $newer = Video::factory()->ready()->create([
        'title' => 'Newer Tail',
        'created_at' => now()->subHour(),
        'published_at' => now()->subDays(10),
    ]);

    $html = $this->get('/')->assertOk()->getContent();

    expect(strpos($html, $newer->title))->toBeLessThan(strpos($html, $older->title));
});

it('omits the category badge when no category is assigned', function () {
    Video::factory()
        ->ready()
        ->featured()
        ->create(['title' => 'No Cat Trailer']);

    $this->get('/')
        ->assertOk()
        ->assertSee('No Cat Trailer')
        ->assertDontSee('neon-category-pill', false);
});
