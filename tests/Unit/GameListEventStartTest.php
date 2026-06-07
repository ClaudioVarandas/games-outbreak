<?php

use App\Models\GameList;
use Carbon\Carbon;
use Tests\TestCase;

uses(TestCase::class);

it('derives the start instant from event_data, timezone-correct', function () {
    $instant = GameList::eventStartAtFor([
        'event_time' => '2026-06-06 11:00:00',
        'event_timezone' => 'America/Los_Angeles', // 11:00 PDT = 18:00 UTC
    ]);

    expect($instant)->toBeInstanceOf(Carbon::class)
        ->and($instant->utc()->toIso8601String())->toBe('2026-06-06T18:00:00+00:00');
});

it('round-trips to the same instant as getEventTime', function () {
    $list = new GameList(['event_data' => [
        'event_time' => '2026-06-06 11:00:00',
        'event_timezone' => 'America/Los_Angeles',
    ]]);

    expect(GameList::eventStartAtFor($list->event_data)->equalTo($list->getEventTime()))->toBeTrue();
});

it('defaults to UTC when the timezone is missing', function () {
    $instant = GameList::eventStartAtFor(['event_time' => '2026-06-06 18:00:00']);

    expect($instant->utc()->toIso8601String())->toBe('2026-06-06T18:00:00+00:00');
});

it('returns null when event_time is absent', function () {
    expect(GameList::eventStartAtFor(['event_timezone' => 'UTC']))->toBeNull()
        ->and(GameList::eventStartAtFor(null))->toBeNull()
        ->and(GameList::eventStartAtFor([]))->toBeNull();
});
