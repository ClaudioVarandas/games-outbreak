<?php

use App\Models\Game;
use App\Models\GameList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function releaseYearListAndGame(): array
{
    $admin = User::factory()->create(['is_admin' => true]);
    $list = GameList::factory()->events()->system()->create([
        'user_id' => $admin->id,
        'slug' => 'nacon-connect-2026',
    ]);
    $game = Game::factory()->create(['igdb_id' => 777111]);

    return [$admin, $list, $game];
}

it('stores release_year when adding a TBA game', function () {
    [$admin, $list, $game] = releaseYearListAndGame();

    $this->actingAs($admin)
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post('/admin/system-lists/events/nacon-connect-2026/games', [
            'game_id' => 777111,
            'is_tba' => true,
            'release_year' => 2027,
        ])
        ->assertJson(['success' => true]);

    expect((int) $list->games()->where('game_id', $game->id)->first()->pivot->release_year)->toBe(2027);
});

it('ignores release_year when the game is not TBA', function () {
    [$admin, $list, $game] = releaseYearListAndGame();

    $this->actingAs($admin)
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post('/admin/system-lists/events/nacon-connect-2026/games', [
            'game_id' => 777111,
            'is_tba' => false,
            'release_year' => 2027,
        ])
        ->assertJson(['success' => true]);

    expect($list->games()->where('game_id', $game->id)->first()->pivot->release_year)->toBeNull();
});

it('updates and clears release_year via the pivot endpoint', function () {
    [$admin, $list, $game] = releaseYearListAndGame();
    $list->games()->attach($game->id, ['order' => 1, 'is_tba' => true, 'release_year' => 2027]);

    $this->actingAs($admin)
        ->patchJson('/admin/system-lists/events/nacon-connect-2026/games/'.$game->id.'/pivot', [
            'is_tba' => false,
            'release_date' => '2026-09-01',
        ])
        ->assertJson(['success' => true]);

    expect($list->games()->where('game_id', $game->id)->first()->pivot->release_year)->toBeNull();
});

it('changes release_year on a TBA game via the pivot endpoint', function () {
    [$admin, $list, $game] = releaseYearListAndGame();
    $list->games()->attach($game->id, ['order' => 1, 'is_tba' => true, 'release_year' => 2027]);

    $this->actingAs($admin)
        ->patchJson('/admin/system-lists/events/nacon-connect-2026/games/'.$game->id.'/pivot', [
            'is_tba' => true,
            'release_year' => 2029,
        ])
        ->assertJson(['success' => true]);

    expect((int) $list->games()->where('game_id', $game->id)->first()->pivot->release_year)->toBe(2029);
});

it('returns release_year from the genres endpoint', function () {
    [$admin, $list, $game] = releaseYearListAndGame();
    $list->games()->attach($game->id, ['order' => 1, 'is_tba' => true, 'release_year' => 2028]);

    $this->actingAs($admin)
        ->getJson('/admin/system-lists/events/nacon-connect-2026/games/'.$game->id.'/genres')
        ->assertJson(['release_year' => 2028]);
});

it('rejects an out-of-range release_year', function () {
    [$admin, $list, $game] = releaseYearListAndGame();

    $this->actingAs($admin)
        ->withHeaders(['Accept' => 'application/json'])
        ->post('/admin/system-lists/events/nacon-connect-2026/games', [
            'game_id' => 777111,
            'is_tba' => true,
            'release_year' => 1850,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['release_year']);
});
