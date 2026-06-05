<?php

use App\Models\Game;
use App\Models\GameReleaseDate;
use App\Services\EventReleaseDateSuggester;
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

it('suggests a concrete date when the day is known', function () {
    $game = Game::factory()->create(['first_release_date' => null]);
    releaseDate($game, [
        'date' => Carbon::create(2027, 3, 15),
        'year' => 2027, 'month' => 3, 'day' => 15,
        'human_readable' => 'Mar 15, 2027',
    ]);

    $suggestion = app(EventReleaseDateSuggester::class)->suggest($game->fresh('releaseDates'));

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

    $suggestion = app(EventReleaseDateSuggester::class)->suggest($game->fresh('releaseDates'));

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

    $suggestion = app(EventReleaseDateSuggester::class)->suggest($game->fresh('releaseDates'));

    expect($suggestion->isTba)->toBeTrue()
        ->and($suggestion->releaseYear)->toBe(2027);
});

it('falls back to the game first_release_date when there are no release-date rows', function () {
    $game = Game::factory()->create(['first_release_date' => Carbon::create(2026, 9, 1)]);

    $suggestion = app(EventReleaseDateSuggester::class)->suggest($game->fresh('releaseDates'));

    expect($suggestion->isTba)->toBeFalse()
        ->and($suggestion->releaseDate->toDateString())->toBe('2026-09-01');
});

it('suggests plain TBA when there is no date signal at all', function () {
    $game = Game::factory()->create(['first_release_date' => null]);

    $suggestion = app(EventReleaseDateSuggester::class)->suggest($game->fresh('releaseDates'));

    expect($suggestion->isTba)->toBeTrue()
        ->and($suggestion->releaseYear)->toBeNull();
});

it('picks the earliest release date among several', function () {
    $game = Game::factory()->create(['first_release_date' => null]);
    releaseDate($game, ['date' => Carbon::create(2029, 1, 10), 'year' => 2029, 'month' => 1, 'day' => 10]);
    releaseDate($game, ['date' => Carbon::create(2027, 6, 5), 'year' => 2027, 'month' => 6, 'day' => 5]);

    $suggestion = app(EventReleaseDateSuggester::class)->suggest($game->fresh('releaseDates'));

    expect($suggestion->releaseDate->toDateString())->toBe('2027-06-05');
});

it('ignores manual release dates', function () {
    $game = Game::factory()->create(['first_release_date' => null]);
    releaseDate($game, [
        'date' => Carbon::create(2027, 6, 5), 'year' => 2027, 'month' => 6, 'day' => 5,
        'is_manual' => true,
    ]);

    $suggestion = app(EventReleaseDateSuggester::class)->suggest($game->fresh('releaseDates'));

    expect($suggestion->isTba)->toBeTrue()
        ->and($suggestion->releaseYear)->toBeNull();
});
