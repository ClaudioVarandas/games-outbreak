<?php

use App\Models\Game;
use App\Models\GameList;
use App\Models\GameReleaseDate;
use App\Models\Genre;
use App\Models\Platform;
use App\Models\ReleaseDateStatus;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function listGameReleaseDate(Game $game, array $attributes): void
{
    GameReleaseDate::factory()->create(array_merge([
        'game_id' => $game->id,
        'platform_id' => null,
        'status_id' => null,
        'is_manual' => false,
    ], $attributes));
}

function pivotRow(GameList $list, Game $game): object
{
    return $list->games()->where('games.id', $game->id)->first()->pivot;
}

it('fails when the list is not found', function () {
    $this->artisan('igdb:gamelist:sync-pivot', ['game_list_id' => 9999, '--accept-all' => true])
        ->assertExitCode(1);
});

it('reports when every pivot already matches', function () {
    $list = GameList::factory()->create();
    $game = Game::factory()->create(['first_release_date' => null]);
    listGameReleaseDate($game, [
        'date' => Carbon::create(2027, 3, 15), 'year' => 2027, 'month' => 3, 'day' => 15,
    ]);
    $list->games()->attach($game->id, [
        'order' => 1, 'is_tba' => false, 'is_early_access' => false,
        'release_date' => Carbon::create(2027, 3, 15)->toDateTimeString(),
        'platforms' => json_encode([]), 'genre_ids' => json_encode([]),
    ]);

    $this->artisan('igdb:gamelist:sync-pivot', ['game_list_id' => $list->id, '--accept-all' => true])
        ->expectsOutputToContain('Every pivot already matches')
        ->assertExitCode(0);
});

it('syncs the pivot for any list type, not just events', function () {
    $list = GameList::factory()->yearly()->system()->create(['slug' => 'yr-2027']);
    $game = Game::factory()->create(['first_release_date' => null]);
    listGameReleaseDate($game, [
        'date' => Carbon::create(2027, 3, 15), 'year' => 2027, 'month' => 3, 'day' => 15,
    ]);
    $game->platforms()->attach(Platform::factory()->create(['igdb_id' => 6])->id);
    $list->games()->attach($game->id, ['order' => 1, 'is_tba' => false, 'platforms' => json_encode([])]);

    $this->artisan('igdb:gamelist:sync-pivot', ['game_list_id' => $list->id, '--accept-all' => true])
        ->assertExitCode(0);

    expect(json_decode(pivotRow($list, $game)->platforms, true))->toBe([6]);
});

it('applies release, platforms and genres together with --accept-all', function () {
    $list = GameList::factory()->create();
    $game = Game::factory()->create(['first_release_date' => null]);
    listGameReleaseDate($game, [
        'date' => Carbon::create(2027, 3, 15), 'year' => 2027, 'month' => 3, 'day' => 15,
    ]);
    $game->platforms()->attach(Platform::factory()->create(['igdb_id' => 6])->id);
    $genre = Genre::factory()->create();
    $game->genres()->attach($genre->id);

    $list->games()->attach($game->id, [
        'order' => 1, 'is_tba' => true, 'release_date' => null,
        'platforms' => json_encode([]), 'genre_ids' => json_encode([]),
    ]);

    $this->artisan('igdb:gamelist:sync-pivot', ['game_list_id' => $list->id, '--accept-all' => true])
        ->assertExitCode(0);

    $pivot = pivotRow($list, $game);
    expect((bool) $pivot->is_tba)->toBeFalse()
        ->and(Carbon::parse($pivot->release_date)->toDateString())->toBe('2027-03-15')
        ->and(json_decode($pivot->platforms, true))->toBe([6])
        ->and(json_decode($pivot->genre_ids, true))->toBe([$genre->id]);
});

it('applies a change picked from the interactive checklist', function () {
    $list = GameList::factory()->create();
    $game = Game::factory()->create(['first_release_date' => null]);
    $game->platforms()->attach(Platform::factory()->create(['igdb_id' => 6])->id);
    // is_tba pivot + no release dates → only the platforms row is offered.
    $list->games()->attach($game->id, ['order' => 1, 'is_tba' => true, 'platforms' => json_encode([])]);

    $this->artisan('igdb:gamelist:sync-pivot', ['game_list_id' => $list->id])
        ->expectsQuestion('Check the changes to apply', ['c0'])
        ->expectsOutputToContain('Applied 1 change')
        ->assertExitCode(0);

    expect(json_decode(pivotRow($list, $game)->platforms, true))->toBe([6]);
});

it('derives the early-access flag from the IGDB release status', function () {
    $status = ReleaseDateStatus::factory()->create(['name' => 'Early Access', 'igdb_id' => 3]);
    $list = GameList::factory()->create();
    $game = Game::factory()->create(['first_release_date' => null]);
    listGameReleaseDate($game, [
        'date' => Carbon::now()->subMonths(2), 'year' => 2026, 'month' => 4, 'day' => 1,
        'status_id' => $status->id,
    ]);

    $list->games()->attach($game->id, ['order' => 1, 'is_tba' => false, 'is_early_access' => false, 'release_date' => null]);

    $this->artisan('igdb:gamelist:sync-pivot', ['game_list_id' => $list->id, '--accept-all' => true])
        ->assertExitCode(0);

    expect((bool) pivotRow($list, $game)->is_early_access)->toBeTrue();
});
