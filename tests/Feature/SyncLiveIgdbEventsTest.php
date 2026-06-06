<?php

use App\Models\GameList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function fakeLiveIgdb(): void
{
    Http::fake(function ($request) {
        $url = $request->url();
        if (str_contains($url, 'id.twitch.tv')) {
            return Http::response(['access_token' => 'token'], 200);
        }
        if (str_contains($url, '/v4/events')) {
            preg_match('/where id = (\d+)/', $request->body(), $m);
            $id = (int) ($m[1] ?? 0);

            return Http::response([[
                'id' => $id,
                'name' => "Event {$id}",
                'start_time' => 1749500000,
                'games' => [111],
            ]], 200);
        }
        if (str_contains($url, '/v4/games')) {
            return Http::response([[
                'id' => 111,
                'name' => 'Game 111',
                'cover' => ['image_id' => 'co111'],
                'platforms' => [['id' => 6, 'name' => 'PC']],
                'genres' => [],
                'game_modes' => [],
                'external_games' => [],
                'websites' => [],
                'game_type' => 0,
                'release_dates' => null,
            ]], 200);
        }

        return Http::response([], 200);
    });
}

beforeEach(function () {
    Queue::fake();
});

it('syncs only events within the start + window-hours cap', function () {
    fakeLiveIgdb();

    $live = GameList::factory()->events()->system()->create([
        'igdb_event_id' => 137,
        'start_at' => now()->subHour(), // started 1h ago, inside the 3h cap
    ]);
    $notStarted = GameList::factory()->events()->system()->create([
        'igdb_event_id' => 200,
        'start_at' => now()->addHour(), // starts in the future
    ]);
    $pastCap = GameList::factory()->events()->system()->create([
        'igdb_event_id' => 300,
        'start_at' => now()->subHours(5), // started 5h ago, beyond the 3h cap
    ]);

    $this->artisan('igdb:events:sync-live')->assertSuccessful();

    expect($live->games()->count())->toBe(1)
        ->and($notStarted->games()->count())->toBe(0)
        ->and($pastCap->games()->count())->toBe(0);
});

it('keeps syncing past a far-future IGDB end date once the cap is reached', function () {
    fakeLiveIgdb();

    // end_at is irrelevant to the sweep — only start_at + cap matters.
    $stale = GameList::factory()->events()->system()->create([
        'igdb_event_id' => 300,
        'start_at' => now()->subHours(6),
        'end_at' => now()->addDays(30),
    ]);

    $this->artisan('igdb:events:sync-live')->assertSuccessful();

    expect($stale->games()->count())->toBe(0);
});

it('respects a configurable window cap', function () {
    config(['services.igdb.event_sync_window_hours' => 8]);
    fakeLiveIgdb();

    $live = GameList::factory()->events()->system()->create([
        'igdb_event_id' => 137,
        'start_at' => now()->subHours(5), // beyond 3h, inside 8h
    ]);

    $this->artisan('igdb:events:sync-live')->assertSuccessful();

    expect($live->games()->count())->toBe(1);
});

it('ignores events that have no IGDB event id', function () {
    fakeLiveIgdb();

    $unlinked = GameList::factory()->events()->system()->create([
        'igdb_event_id' => null,
        'start_at' => now()->subHour(),
    ]);

    $this->artisan('igdb:events:sync-live')->assertSuccessful();

    expect($unlinked->games()->count())->toBe(0);
});

it('reports when there are no live events', function () {
    fakeLiveIgdb();

    $this->artisan('igdb:events:sync-live')
        ->expectsOutputToContain('No live events')
        ->assertSuccessful();
});
