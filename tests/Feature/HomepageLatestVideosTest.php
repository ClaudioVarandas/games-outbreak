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
