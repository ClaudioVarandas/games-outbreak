<?php

use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('generates slug from name and year on creation', function () {
    $game = Game::factory()->create([
        'name' => 'The Legend of Zelda',
        'first_release_date' => new DateTime('2023-05-12'),
    ]);

    expect($game->slug)->toBe('the-legend-of-zelda-2023');
});

it('generates slug from name only when no release date', function () {
    $game = Game::factory()->create([
        'name' => 'Unknown Game',
        'first_release_date' => null,
    ]);

    expect($game->slug)->toBe('unknown-game');
});

it('handles special characters in name', function () {
    $game = Game::factory()->create([
        'name' => "Assassin's Creed: Valhalla",
        'first_release_date' => new DateTime('2020-11-10'),
    ]);

    expect($game->slug)->toBe('assassins-creed-valhalla-2020');
});

it('handles duplicate slugs with counter', function () {
    $game1 = Game::factory()->create([
        'name' => 'Doom',
        'first_release_date' => new DateTime('2016-05-13'),
    ]);

    $game2 = Game::factory()->create([
        'name' => 'Doom',
        'first_release_date' => new DateTime('2016-07-20'),
    ]);

    expect($game1->slug)->toBe('doom-2016');
    expect($game2->slug)->toBe('doom-2016-2');
});

it('handles multiple duplicates with incrementing counter', function () {
    $game1 = Game::factory()->create(['name' => 'Test Game', 'first_release_date' => new DateTime('2020-01-01')]);
    $game2 = Game::factory()->create(['name' => 'Test Game', 'first_release_date' => new DateTime('2020-06-01')]);
    $game3 = Game::factory()->create(['name' => 'Test Game', 'first_release_date' => new DateTime('2020-12-01')]);

    expect($game1->slug)->toBe('test-game-2020');
    expect($game2->slug)->toBe('test-game-2020-2');
    expect($game3->slug)->toBe('test-game-2020-3');
});

it('regenerates slug when name changes', function () {
    $game = Game::factory()->create([
        'name' => 'Original Name',
        'first_release_date' => new DateTime('2023-01-01'),
    ]);

    expect($game->slug)->toBe('original-name-2023');

    $game->update(['name' => 'Updated Name']);

    expect($game->fresh()->slug)->toBe('updated-name-2023');
});

it('regenerates slug when release date changes', function () {
    $game = Game::factory()->create([
        'name' => 'Game Title',
        'first_release_date' => new DateTime('2023-01-01'),
    ]);

    expect($game->slug)->toBe('game-title-2023');

    $game->update(['first_release_date' => new DateTime('2024-06-15')]);

    expect($game->fresh()->slug)->toBe('game-title-2024');
});

it('does not change slug when other attributes change', function () {
    $game = Game::factory()->create([
        'name' => 'Stable Game',
        'first_release_date' => new DateTime('2022-01-01'),
    ]);

    $originalSlug = $game->slug;

    $game->update(['summary' => 'New summary text']);

    expect($game->fresh()->slug)->toBe($originalSlug);
});

it('preserves custom slug if set manually before creation', function () {
    $game = new Game;
    $game->fill([
        'igdb_id' => 999999,
        'name' => 'Custom Slug Game',
        'first_release_date' => new DateTime('2023-01-01'),
        'slug' => 'my-custom-slug',
    ]);
    $game->save();

    expect($game->fresh()->slug)->toBe('my-custom-slug');
});

it('generates uuid along with slug on creation', function () {
    $game = Game::factory()->create([
        'name' => 'Test UUID Game',
        'first_release_date' => new DateTime('2023-01-01'),
    ]);

    expect($game->uuid)->not->toBeNull();
    expect($game->uuid)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
    expect($game->slug)->toBe('test-uuid-game-2023');
});

it('can be found by slug via route model binding', function () {
    $game = Game::factory()->create([
        'name' => 'Findable Game',
        'first_release_date' => new DateTime('2023-06-15'),
    ]);

    $foundGame = Game::where('slug', $game->slug)->first();

    expect($foundGame)->not->toBeNull();
    expect($foundGame->id)->toBe($game->id);
});
