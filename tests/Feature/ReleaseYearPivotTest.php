<?php

use App\Models\Game;
use App\Models\GameList;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('persists and exposes release_year on the game_list_game pivot', function () {
    $list = GameList::factory()->events()->system()->create(['slug' => 'evt']);
    $game = Game::factory()->create();

    $list->games()->attach($game->id, ['order' => 1, 'is_tba' => true, 'release_year' => 2027]);

    expect((int) $list->games()->where('games.id', $game->id)->first()->pivot->release_year)->toBe(2027);
});
