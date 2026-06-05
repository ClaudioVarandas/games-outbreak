<?php

use App\Models\Game;
use App\Models\GameList;
use App\Models\GameReleaseDate;
use App\Models\Genre;
use App\Models\Platform;
use App\Models\ReleaseDateStatus;
use App\Services\GameListPivotSuggester;
use App\Support\PivotChange;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function releaseDate(Game $game, array $attributes): GameReleaseDate
{
    return GameReleaseDate::factory()->create(array_merge([
        'game_id' => $game->id,
        'platform_id' => null,
        'status_id' => null,
        'is_manual' => false,
    ], $attributes));
}

function gameInList(Game $game, array $pivot = []): Game
{
    $list = GameList::factory()->create();
    $list->games()->attach($game->id, array_merge(['order' => 1], $pivot));

    return $list->load(['games.releaseDates.status', 'games.platforms', 'games.genres'])
        ->games->firstWhere('id', $game->id);
}

/**
 * @param  list<PivotChange>  $changes
 */
function changeField(array $changes, string $field): ?PivotChange
{
    return collect($changes)->firstWhere('field', $field);
}

it('suggests a concrete date when the day is known', function () {
    $game = Game::factory()->create(['first_release_date' => null]);
    releaseDate($game, [
        'date' => Carbon::create(2027, 3, 15),
        'year' => 2027, 'month' => 3, 'day' => 15,
        'human_readable' => 'Mar 15, 2027',
    ]);

    $suggestion = app(GameListPivotSuggester::class)->releaseSuggestion($game->fresh('releaseDates'));

    expect($suggestion->isTba)->toBeFalse()
        ->and($suggestion->releaseYear)->toBeNull()
        ->and($suggestion->releaseDate->toDateString())->toBe('2027-03-15');
});

it('suggests TBA + year when only the year is known', function () {
    $game = Game::factory()->create(['first_release_date' => null]);
    releaseDate($game, [
        'date' => null,
        'year' => 2028, 'month' => null, 'day' => null,
        'human_readable' => '2028',
    ]);

    $suggestion = app(GameListPivotSuggester::class)->releaseSuggestion($game->fresh('releaseDates'));

    expect($suggestion->isTba)->toBeTrue()
        ->and($suggestion->releaseYear)->toBe(2028)
        ->and($suggestion->releaseDate)->toBeNull();
});

it('treats month precision without a day as TBA + year', function () {
    $game = Game::factory()->create(['first_release_date' => null]);
    releaseDate($game, [
        'date' => null,
        'year' => 2027, 'month' => 11, 'day' => null,
        'human_readable' => 'Nov 2027',
    ]);

    $suggestion = app(GameListPivotSuggester::class)->releaseSuggestion($game->fresh('releaseDates'));

    expect($suggestion->isTba)->toBeTrue()
        ->and($suggestion->releaseYear)->toBe(2027);
});

it('falls back to the game first_release_date when there are no release-date rows', function () {
    $game = Game::factory()->create(['first_release_date' => Carbon::create(2026, 9, 1)]);

    $suggestion = app(GameListPivotSuggester::class)->releaseSuggestion($game->fresh('releaseDates'));

    expect($suggestion->isTba)->toBeFalse()
        ->and($suggestion->releaseDate->toDateString())->toBe('2026-09-01');
});

it('suggests plain TBA when there is no date signal at all', function () {
    $game = Game::factory()->create(['first_release_date' => null]);

    $suggestion = app(GameListPivotSuggester::class)->releaseSuggestion($game->fresh('releaseDates'));

    expect($suggestion->isTba)->toBeTrue()
        ->and($suggestion->releaseYear)->toBeNull();
});

it('picks the earliest release date among several', function () {
    $game = Game::factory()->create(['first_release_date' => null]);
    releaseDate($game, ['date' => Carbon::create(2029, 1, 10), 'year' => 2029, 'month' => 1, 'day' => 10]);
    releaseDate($game, ['date' => Carbon::create(2027, 6, 5), 'year' => 2027, 'month' => 6, 'day' => 5]);

    $suggestion = app(GameListPivotSuggester::class)->releaseSuggestion($game->fresh('releaseDates'));

    expect($suggestion->releaseDate->toDateString())->toBe('2027-06-05');
});

it('ignores manual release dates', function () {
    $game = Game::factory()->create(['first_release_date' => null]);
    releaseDate($game, [
        'date' => Carbon::create(2027, 6, 5), 'year' => 2027, 'month' => 6, 'day' => 5,
        'is_manual' => true,
    ]);

    $suggestion = app(GameListPivotSuggester::class)->releaseSuggestion($game->fresh('releaseDates'));

    expect($suggestion->isTba)->toBeTrue()
        ->and($suggestion->releaseYear)->toBeNull();
});

it('suggests a platforms change carrying the IGDB platform ids', function () {
    $game = Game::factory()->create(['first_release_date' => null]);
    $game->platforms()->attach(Platform::factory()->create(['igdb_id' => 6])->id);

    $loaded = gameInList($game, ['platforms' => json_encode([])]);
    $change = changeField(app(GameListPivotSuggester::class)->changesFor($loaded), 'platforms');

    expect($change)->not->toBeNull()
        ->and(json_decode($change->pivot['platforms'], true))->toBe([6]);
});

it('suggests a genres change carrying the local genre ids and a primary', function () {
    $game = Game::factory()->create(['first_release_date' => null]);
    $genre = Genre::factory()->create();
    $game->genres()->attach($genre->id);

    $loaded = gameInList($game, ['genre_ids' => json_encode([]), 'primary_genre_id' => null]);
    $change = changeField(app(GameListPivotSuggester::class)->changesFor($loaded), 'genres');

    expect($change)->not->toBeNull()
        ->and(json_decode($change->pivot['genre_ids'], true))->toBe([$genre->id])
        ->and($change->pivot['primary_genre_id'])->toBe($genre->id);
});

it('suggests early access when the primary release date status is early access in the past', function () {
    $status = ReleaseDateStatus::factory()->create(['name' => 'Early Access', 'igdb_id' => 3]);
    $game = Game::factory()->create(['first_release_date' => null]);
    releaseDate($game, [
        'date' => Carbon::now()->subMonths(2), 'year' => 2026, 'month' => 4, 'day' => 1,
        'status_id' => $status->id,
    ]);

    $loaded = gameInList($game, ['is_early_access' => false]);
    $change = changeField(app(GameListPivotSuggester::class)->changesFor($loaded), 'early_access');

    expect($change)->not->toBeNull()
        ->and($change->pivot['is_early_access'])->toBeTrue();
});

it('returns no changes when the pivot already matches IGDB', function () {
    $status = ReleaseDateStatus::factory()->create(['name' => 'Full Release', 'igdb_id' => 6]);
    $game = Game::factory()->create(['first_release_date' => null]);
    releaseDate($game, [
        'date' => Carbon::create(2027, 3, 15), 'year' => 2027, 'month' => 3, 'day' => 15,
        'status_id' => $status->id,
    ]);
    $platform = Platform::factory()->create(['igdb_id' => 6]);
    $genre = Genre::factory()->create();
    $game->platforms()->attach($platform->id);
    $game->genres()->attach($genre->id);

    $loaded = gameInList($game, [
        'release_date' => Carbon::create(2027, 3, 15)->toDateTimeString(),
        'is_tba' => false,
        'is_early_access' => false,
        'platforms' => json_encode([6]),
        'genre_ids' => json_encode([$genre->id]),
        'primary_genre_id' => $genre->id,
    ]);

    expect(app(GameListPivotSuggester::class)->changesFor($loaded))->toBe([]);
});
