<?php

use App\Models\GameList;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the events timeline page successfully', function () {
    $response = $this->get(route('events'));

    $response->assertSuccessful();
    $response->assertViewIs('events.index');
    $response->assertSeeText('Events');
});

it('shows upcoming events in the upcoming section', function () {
    GameList::factory()->events()->system()->public()->active()->create([
        'name' => 'Summer Game Fest 2026',
        'slug' => 'summer-game-fest-2026',
        'start_at' => now()->addMonths(2),
        'event_data' => ['event_time' => now()->addMonths(2)->toISOString(), 'event_timezone' => 'UTC'],
    ]);

    $response = $this->get(route('events'));

    $response->assertSuccessful();
    $response->assertSeeText('Summer Game Fest 2026');
    $response->assertSeeText('Upcoming');
});

it('shows past events in the past events section', function () {
    GameList::factory()->events()->system()->public()->active()->create([
        'name' => 'Xbox Showcase 2025',
        'slug' => 'xbox-showcase-2025',
        'start_at' => now()->subMonths(6),
        'event_data' => ['event_time' => now()->subMonths(6)->toISOString(), 'event_timezone' => 'UTC'],
    ]);

    $response = $this->get(route('events'));

    $response->assertSuccessful();
    $response->assertSeeText('Xbox Showcase 2025');
    $response->assertSeeText('Past Events');
});

it('does not show inactive events', function () {
    GameList::factory()->events()->system()->public()->create([
        'name' => 'Hidden Event',
        'slug' => 'hidden-event',
        'is_active' => false,
        'start_at' => now()->addMonth(),
    ]);

    $response = $this->get(route('events'));

    $response->assertSuccessful();
    $response->assertDontSeeText('Hidden Event');
});

it('does not show private events', function () {
    GameList::factory()->events()->system()->create([
        'name' => 'Private Event',
        'slug' => 'private-event',
        'is_public' => false,
        'is_active' => true,
        'start_at' => now()->addMonth(),
    ]);

    $response = $this->get(route('events'));

    $response->assertSuccessful();
    $response->assertDontSeeText('Private Event');
});

it('shows empty state when no events exist', function () {
    $response = $this->get(route('events'));

    $response->assertSuccessful();
    $response->assertSeeText('No upcoming events right now.');
    $response->assertSeeText('No past events.');
});
