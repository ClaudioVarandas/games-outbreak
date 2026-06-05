<?php

use App\Models\Game;
use App\Models\GameList;
use App\Models\GameReleaseDate;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function eventList(): GameList
{
    return GameList::factory()->events()->system()->create([
        'slug' => 'evt-dates',
        'start_at' => now()->setDate(2026, 6, 2),
        'end_at' => now()->setDate(2026, 6, 2),
    ]);
}

function igdbReleaseDate(Game $game, array $attributes): void
{
    GameReleaseDate::factory()->create(array_merge([
        'game_id' => $game->id,
        'platform_id' => null,
        'status_id' => null,
        'is_manual' => false,
    ], $attributes));
}

function pivotFor(GameList $list, Game $game): object
{
    return $list->games()->where('games.id', $game->id)->first()->pivot;
}

it('fails when the list is not an events list', function () {
    $list = GameList::factory()->yearly()->system()->create(['slug' => 'yr-2026']);
    $game = Game::factory()->create();
    $list->games()->attach($game->id, ['order' => 1, 'is_tba' => false, 'release_year' => null]);

    $this->artisan('igdb:gamelist:events:sync-dates', ['game_list_id' => $list->id, '--accept-all' => true])
        ->expectsOutputToContain('not an events list')
        ->assertExitCode(1);

    expect((bool) pivotFor($list, $game)->is_tba)->toBeFalse();
});

it('fails when the list is not found', function () {
    $this->artisan('igdb:gamelist:events:sync-dates', ['game_list_id' => 9999, '--accept-all' => true])
        ->assertExitCode(1);
});

it('applies a TBA + year suggestion to a blank pivot with --accept-all', function () {
    $list = eventList();
    $game = Game::factory()->create(['first_release_date' => null]);
    igdbReleaseDate($game, ['date' => null, 'year' => 2028, 'month' => null, 'day' => null, 'human_readable' => '2028']);
    $list->games()->attach($game->id, ['order' => 1, 'is_tba' => false, 'release_date' => null, 'release_year' => null]);

    $this->artisan('igdb:gamelist:events:sync-dates', ['game_list_id' => $list->id, '--accept-all' => true])
        ->assertExitCode(0);

    $pivot = pivotFor($list, $game);
    expect((bool) $pivot->is_tba)->toBeTrue()
        ->and((int) $pivot->release_year)->toBe(2028)
        ->and($pivot->release_date)->toBeNull();
});

it('overrides a curated TBA with a concrete date when IGDB has a firm day', function () {
    $list = eventList();
    $game = Game::factory()->create(['first_release_date' => null]);
    igdbReleaseDate($game, ['date' => Carbon::create(2027, 3, 15), 'year' => 2027, 'month' => 3, 'day' => 15]);
    $list->games()->attach($game->id, ['order' => 1, 'is_tba' => true, 'release_year' => 2027, 'release_date' => null]);

    $this->artisan('igdb:gamelist:events:sync-dates', ['game_list_id' => $list->id, '--accept-all' => true])
        ->assertExitCode(0);

    $pivot = pivotFor($list, $game);
    expect((bool) $pivot->is_tba)->toBeFalse()
        ->and($pivot->release_year)->toBeNull()
        ->and(Carbon::parse($pivot->release_date)->toDateString())->toBe('2027-03-15');
});

it('skips a game whose pivot already matches the suggestion', function () {
    $list = eventList();
    $game = Game::factory()->create(['first_release_date' => null]);
    igdbReleaseDate($game, ['date' => Carbon::create(2027, 3, 15), 'year' => 2027, 'month' => 3, 'day' => 15]);
    $list->games()->attach($game->id, [
        'order' => 1, 'is_tba' => false, 'release_year' => null,
        'release_date' => Carbon::create(2027, 3, 15)->toDateTimeString(),
    ]);

    $this->artisan('igdb:gamelist:events:sync-dates', ['game_list_id' => $list->id, '--accept-all' => true])
        ->expectsOutputToContain('Updated 0, skipped 1')
        ->assertExitCode(0);
});

it('applies the suggestion when the operator confirms interactively', function () {
    $list = eventList();
    $game = Game::factory()->create(['first_release_date' => null, 'name' => 'Hollow Knight Silksong']);
    igdbReleaseDate($game, ['date' => null, 'year' => 2028, 'month' => null, 'day' => null, 'human_readable' => '2028']);
    $list->games()->attach($game->id, ['order' => 1, 'is_tba' => false, 'release_date' => null, 'release_year' => null]);

    $this->artisan('igdb:gamelist:events:sync-dates', ['game_list_id' => $list->id])
        ->expectsChoice('Update "Hollow Knight Silksong"?', 'yes', [
            'yes' => 'Yes',
            'no' => 'No',
            'all' => 'Yes to all',
            'quit' => 'Quit',
        ])
        ->assertExitCode(0);

    $pivot = pivotFor($list, $game);
    expect((bool) $pivot->is_tba)->toBeTrue()
        ->and((int) $pivot->release_year)->toBe(2028);
});

it('does not turn an early-access game into TBA', function () {
    $list = eventList();
    $game = Game::factory()->create(['first_release_date' => null]);
    igdbReleaseDate($game, ['date' => null, 'year' => 2028, 'month' => null, 'day' => null]);
    $list->games()->attach($game->id, [
        'order' => 1, 'is_tba' => false, 'is_early_access' => true,
        'release_date' => Carbon::create(2026, 1, 1)->toDateTimeString(),
    ]);

    $this->artisan('igdb:gamelist:events:sync-dates', ['game_list_id' => $list->id, '--accept-all' => true])
        ->expectsOutputToContain('Early Access cannot be TBA')
        ->assertExitCode(0);

    expect((bool) pivotFor($list, $game)->is_tba)->toBeFalse();
});
