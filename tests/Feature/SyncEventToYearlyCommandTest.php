<?php

use App\Models\Game;
use App\Models\GameList;
use App\Models\User;
use App\Services\EventYearlySyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    User::factory()->create(); // user_id = 1 owner for any auto-created yearly list
});

function eventWithGames(): array
{
    $event = GameList::factory()->events()->system()->create([
        'slug' => 'nacon-connect-2026',
        'start_at' => now()->setDate(2026, 6, 2),
        'end_at' => now()->setDate(2026, 6, 2),
    ]);

    $in2026 = Game::factory()->create(['name' => 'Game A']);
    $in2028 = Game::factory()->create(['name' => 'Game B']);
    $tba = Game::factory()->create(['name' => 'Game C']);

    $event->games()->attach($in2026->id, ['order' => 1, 'release_date' => now()->setDate(2026, 9, 1), 'video_url' => 'https://youtu.be/dQw4w9WgXcQ']);
    $event->games()->attach($in2028->id, ['order' => 2, 'release_date' => now()->setDate(2028, 3, 1)]);
    $event->games()->attach($tba->id, ['order' => 3, 'is_tba' => true]);

    return [$event->fresh('games'), $in2026, $in2028, $tba];
}

it('routes each game to the yearly list for its own release year, TBA to the event year', function () {
    [$event, $in2026, $in2028, $tba] = eventWithGames();

    $plan = app(EventYearlySyncService::class)->plan($event);
    $byId = collect($plan)->keyBy(fn ($p) => $p['game']->id);

    expect($byId[$in2026->id]['target_year'])->toBe(2026)
        ->and($byId[$in2026->id]['has_video'])->toBeTrue()
        ->and($byId[$in2028->id]['target_year'])->toBe(2028)
        ->and($byId[$tba->id]['target_year'])->toBe(2026)
        ->and($byId[$tba->id]['release_label'])->toBe('TBA');
});

it('marks a game already complete in its yearly list as skip', function () {
    [$event, $in2026] = eventWithGames();

    $yearly = GameList::factory()->yearly()->system()->create([
        'slug' => 'game-releases-2026',
        'start_at' => now()->setDate(2026, 1, 1),
        'end_at' => now()->setDate(2026, 12, 31),
    ]);
    $yearly->games()->attach($in2026->id, [
        'order' => 1,
        'release_date' => now()->setDate(2026, 9, 1),
        'platforms' => json_encode([6]),
        'video_url' => 'https://youtu.be/dQw4w9WgXcQ',
    ]);

    $plan = app(EventYearlySyncService::class)->plan($event);
    $entry = collect($plan)->firstWhere(fn ($p) => $p['game']->id === $in2026->id);

    expect($entry['action'])->toBe('skip')
        ->and($entry['fills'])->toBe([]);
});

it('marks a game present in its yearly list but missing a trailer as fill', function () {
    [$event, $in2026] = eventWithGames();

    $yearly = GameList::factory()->yearly()->system()->create([
        'slug' => 'game-releases-2026',
        'start_at' => now()->setDate(2026, 1, 1),
        'end_at' => now()->setDate(2026, 12, 31),
    ]);
    // Present with date + platforms, but NO video_url — the event has one to fill.
    $yearly->games()->attach($in2026->id, [
        'order' => 1,
        'release_date' => now()->setDate(2026, 9, 1),
        'platforms' => json_encode([6]),
        'video_url' => null,
    ]);

    $plan = app(EventYearlySyncService::class)->plan($event);
    $entry = collect($plan)->firstWhere(fn ($p) => $p['game']->id === $in2026->id);

    expect($entry['action'])->toBe('fill')
        ->and($entry['fills'])->toBe(['video_url']);
});
