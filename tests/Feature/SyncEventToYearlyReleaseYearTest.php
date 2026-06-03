<?php

use App\Models\Game;
use App\Models\GameList;
use App\Models\User;
use App\Services\EventYearlySyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    User::factory()->create(); // user_id = 1 owner for auto-created yearly lists
});

function eventForReleaseYear(): GameList
{
    return GameList::factory()->events()->system()->create([
        'slug' => 'evt-2026',
        'start_at' => now()->setDate(2026, 6, 2),
        'end_at' => now()->setDate(2026, 6, 2),
    ]);
}

it('routes a TBA game tagged with a release_year into that year list', function () {
    $event = eventForReleaseYear();
    $game = Game::factory()->create();
    $event->games()->attach($game->id, ['order' => 1, 'is_tba' => true, 'release_year' => 2027]);
    $event = $event->fresh('games');

    $result = app(EventYearlySyncService::class)->apply($event, [$game->id]);

    $list2027 = GameList::yearly()->whereYear('start_at', 2027)->first();
    expect($list2027)->not->toBeNull()
        ->and($result['inserted'])->toBe(1);

    $pivot = $list2027->games()->where('games.id', $game->id)->first()->pivot;
    expect((bool) $pivot->is_tba)->toBeTrue()
        ->and((int) $pivot->release_year)->toBe(2027);
});

it('routes a plain TBA game (no year) to the event year', function () {
    $event = eventForReleaseYear();
    $game = Game::factory()->create();
    $event->games()->attach($game->id, ['order' => 1, 'is_tba' => true]);
    $event = $event->fresh('games');

    app(EventYearlySyncService::class)->apply($event, [$game->id]);

    expect(GameList::yearly()->whereYear('start_at', 2026)->first()?->games()->where('games.id', $game->id)->exists())->toBeTrue()
        ->and(GameList::yearly()->whereYear('start_at', 2027)->exists())->toBeFalse();
});

it('plan() reports the tagged year as the target', function () {
    $event = eventForReleaseYear();
    $game = Game::factory()->create();
    $event->games()->attach($game->id, ['order' => 1, 'is_tba' => true, 'release_year' => 2028]);
    $event = $event->fresh('games');

    $entry = collect(app(EventYearlySyncService::class)->plan($event))->firstWhere(fn ($p) => $p['game']->id === $game->id);

    expect($entry['target_year'])->toBe(2028)
        ->and($entry['release_label'])->toBe('TBA');
});

it('plan() falls back to the event year for a plain TBA game', function () {
    $event = eventForReleaseYear();
    $game = Game::factory()->create();
    $event->games()->attach($game->id, ['order' => 1, 'is_tba' => true]);
    $event = $event->fresh('games');

    $entry = collect(app(EventYearlySyncService::class)->plan($event))->firstWhere(fn ($p) => $p['game']->id === $game->id);

    expect($entry['target_year'])->toBe(2026);
});
