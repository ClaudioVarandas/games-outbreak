<?php

use App\Models\Game;
use App\Models\GameList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('keeps dated future-year games as month sections on events lists (no out-of-year skip)', function () {
    $list = GameList::factory()->events()->system()->create([
        'slug' => 'evt-2026',
        'start_at' => now()->setDate(2026, 6, 2),
        'end_at' => now()->setDate(2026, 6, 2),
    ]);
    $game = Game::factory()->create();
    $list->games()->attach($game->id, ['order' => 1, 'release_date' => now()->setDate(2027, 9, 1)]);

    expect($list->fresh('games')->groupGamesByMonth())->toHaveKey('2027-09');
});

it('still skips out-of-year dated games on yearly lists', function () {
    $list = GameList::factory()->yearly()->system()->create([
        'slug' => 'yr-2026',
        'start_at' => now()->setDate(2026, 1, 1),
        'end_at' => now()->setDate(2026, 12, 31),
    ]);
    $game = Game::factory()->create();
    $list->games()->attach($game->id, ['order' => 1, 'release_date' => now()->setDate(2027, 9, 1)]);

    expect($list->fresh('games')->groupGamesByMonth())->not->toHaveKey('2027-09');
});

it('renders a year section on the public events page for a TBA game tagged with a year', function () {
    $list = GameList::factory()->events()->system()->create([
        'slug' => 'evt-render-2026',
        'is_public' => true,
        'is_active' => true,
        'start_at' => now()->setDate(2026, 6, 2),
        'end_at' => now()->setDate(2026, 6, 2),
    ]);
    $game = Game::factory()->create(['name' => 'Future Announce']);
    $list->games()->attach($game->id, ['order' => 1, 'is_tba' => true, 'release_year' => 2027]);

    $this->actingAs(User::factory()->create())
        ->get('/list/events/evt-render-2026')
        ->assertOk()
        ->assertSee('Future Announce')
        ->assertSee('2027');
});

it('subdivides the TBA area by release_year on events lists', function () {
    $list = GameList::factory()->events()->system()->create([
        'slug' => 'evt-2026',
        'start_at' => now()->setDate(2026, 6, 2),
        'end_at' => now()->setDate(2026, 6, 2),
    ]);

    $dated = Game::factory()->create(['name' => 'Dated']);
    $y2027 = Game::factory()->create(['name' => 'Future27']);
    $y2028 = Game::factory()->create(['name' => 'Future28']);
    $plain = Game::factory()->create(['name' => 'Unknown']);

    $list->games()->attach($dated->id, ['order' => 1, 'release_date' => now()->setDate(2026, 9, 1)]);
    $list->games()->attach($y2027->id, ['order' => 2, 'is_tba' => true, 'release_year' => 2027]);
    $list->games()->attach($y2028->id, ['order' => 3, 'is_tba' => true, 'release_year' => 2028]);
    $list->games()->attach($plain->id, ['order' => 4, 'is_tba' => true]);

    $grouped = $list->fresh('games')->groupGamesByMonth();
    $keys = array_keys($grouped);

    expect($grouped)->toHaveKey('tba-2027')
        ->and($grouped['tba-2027']['label'])->toBe('2027')
        ->and($grouped)->toHaveKey('tba-2028')
        ->and($grouped)->toHaveKey('tba')
        ->and($grouped)->toHaveKey('2026-09')
        ->and(array_search('tba', $keys, true))->toBeLessThan(array_search('tba-2027', $keys, true))
        ->and(array_search('tba-2027', $keys, true))->toBeLessThan(array_search('tba-2028', $keys, true))
        ->and(array_search('tba-2028', $keys, true))->toBeLessThan(array_search('2026-09', $keys, true));
});

it('does NOT subdivide TBA by year on non-events (yearly) lists', function () {
    $list = GameList::factory()->yearly()->system()->create([
        'slug' => 'yr-2027',
        'start_at' => now()->setDate(2027, 1, 1),
        'end_at' => now()->setDate(2027, 12, 31),
    ]);
    $game = Game::factory()->create();
    $list->games()->attach($game->id, ['order' => 1, 'is_tba' => true, 'release_year' => 2027]);

    $grouped = $list->fresh('games')->groupGamesByMonth();

    expect($grouped)->toHaveKey('tba')
        ->and($grouped)->not->toHaveKey('tba-2027');
});
