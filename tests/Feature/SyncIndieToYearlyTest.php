<?php

use App\Models\Game;
use App\Models\GameList;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('inserts a game into the matching yearly list when marked indie on a seasoned list', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $genre = Genre::factory()->create();

    $seasoned = GameList::factory()->seasoned()->system()->create([
        'slug' => 'summer-2031',
        'start_at' => now()->setDate(2031, 6, 1),
        'end_at' => now()->setDate(2031, 8, 31),
    ]);
    $yearly = GameList::factory()->yearly()->system()->create([
        'slug' => 'game-releases-2031',
        'start_at' => now()->setDate(2031, 1, 1),
        'end_at' => now()->setDate(2031, 12, 31),
    ]);

    $game = Game::factory()->create();
    $seasoned->games()->attach($game->id, [
        'order' => 1,
        'release_date' => now()->setDate(2031, 7, 15),
        'platforms' => json_encode([6]),
    ]);

    $this->actingAs($admin)
        ->patchJson('/admin/system-lists/seasoned/summer-2031/games/'.$game->id.'/indie', [
            'is_indie' => true,
            'primary_genre_id' => $genre->id,
        ])
        ->assertJson(['success' => true]);

    $pivot = $yearly->games()->where('games.id', $game->id)->first()?->pivot;
    expect($pivot)->not->toBeNull()
        ->and((bool) $pivot->is_indie)->toBeTrue();
});

it('updates is_indie on the existing yearly row without inserting a duplicate', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $genre = Genre::factory()->create();

    $seasoned = GameList::factory()->seasoned()->system()->create([
        'slug' => 'summer-2031',
        'start_at' => now()->setDate(2031, 6, 1),
        'end_at' => now()->setDate(2031, 8, 31),
    ]);
    $yearly = GameList::factory()->yearly()->system()->create([
        'slug' => 'game-releases-2031',
        'start_at' => now()->setDate(2031, 1, 1),
        'end_at' => now()->setDate(2031, 12, 31),
    ]);

    $game = Game::factory()->create();
    $seasoned->games()->attach($game->id, ['order' => 1, 'release_date' => now()->setDate(2031, 7, 15), 'platforms' => json_encode([6])]);
    // Game is ALREADY in the yearly list (e.g. added manually) with is_indie=false.
    $yearly->games()->attach($game->id, ['order' => 1, 'release_date' => now()->setDate(2031, 7, 15), 'platforms' => json_encode([6]), 'is_indie' => false]);

    $this->actingAs($admin)
        ->patchJson('/admin/system-lists/seasoned/summer-2031/games/'.$game->id.'/indie', [
            'is_indie' => true,
            'primary_genre_id' => $genre->id,
        ])
        ->assertJson(['success' => true]);

    expect($yearly->games()->where('games.id', $game->id)->count())->toBe(1) // no duplicate
        ->and((bool) $yearly->games()->where('games.id', $game->id)->first()->pivot->is_indie)->toBeTrue();
});
