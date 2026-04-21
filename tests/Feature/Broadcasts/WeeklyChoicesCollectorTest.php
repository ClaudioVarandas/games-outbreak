<?php

use App\Models\Game;
use App\Models\GameList;
use App\Services\WeeklyChoicesCollector;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-04-26 21:00:00', 'Europe/Lisbon'));

    $this->list = GameList::factory()->system()->yearly()->active()->create([
        'start_at' => Carbon::create(2026, 1, 1),
        'end_at' => Carbon::create(2026, 12, 31),
    ]);

    $this->thisWeek = Game::factory()->create(['name' => 'This-Week Game']);
    $this->nextWeek = Game::factory()->create(['name' => 'Next-Week Game']);
    $this->weekAfter = Game::factory()->create(['name' => 'Week-After Game']);

    $this->list->games()->attach($this->thisWeek->id, ['release_date' => '2026-04-22 00:00:00']);
    $this->list->games()->attach($this->nextWeek->id, ['release_date' => '2026-04-29 00:00:00']);
    $this->list->games()->attach($this->weekAfter->id, ['release_date' => '2026-05-06 00:00:00']);

    $this->collector = app(WeeklyChoicesCollector::class);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('forCurrentWeek returns only games in the current Mon-Sun window', function () {
    $payload = $this->collector->forCurrentWeek();

    expect($payload->count())->toBe(1);
    expect($payload->games->first()->name)->toBe('This-Week Game');
    expect($payload->windowStart->toDateString())->toBe('2026-04-20');
    expect($payload->windowEnd->toDateString())->toBe('2026-04-26');
});

it('forUpcomingWeek returns only games in next Mon-Sun window', function () {
    $payload = $this->collector->forUpcomingWeek();

    expect($payload->count())->toBe(1);
    expect($payload->games->first()->name)->toBe('Next-Week Game');
    expect($payload->windowStart->toDateString())->toBe('2026-04-27');
    expect($payload->windowEnd->toDateString())->toBe('2026-05-03');
});

it('returns empty payload when no yearly list exists for the window year', function () {
    GameList::query()->delete();

    $payload = $this->collector->forUpcomingWeek();

    expect($payload->isEmpty())->toBeTrue();
    expect($payload->count())->toBe(0);
});

it('respects the 18-game limit', function () {
    $games = Game::factory()->count(25)->create();
    foreach ($games as $i => $game) {
        $this->list->games()->attach($game->id, [
            'release_date' => Carbon::parse('2026-04-27 00:00:00')->addMinutes($i)->toDateTimeString(),
        ]);
    }

    $payload = $this->collector->forUpcomingWeek();

    expect($payload->count())->toBe(18);
});

it('keys the yearly list off the window start year across year rollover', function () {
    $listFor2027 = GameList::factory()->system()->yearly()->active()->create([
        'start_at' => Carbon::create(2027, 1, 1),
        'end_at' => Carbon::create(2027, 12, 31),
    ]);

    $game2027 = Game::factory()->create(['name' => '2027 Kickoff Game']);
    $listFor2027->games()->attach($game2027->id, ['release_date' => '2027-01-04 00:00:00']);

    Carbon::setTestNow(Carbon::parse('2027-01-03 21:00:00', 'Europe/Lisbon'));
    $payload = $this->collector->forUpcomingWeek(CarbonImmutable::now());

    expect($payload->windowStart->year)->toBe(2027);
    expect($payload->count())->toBe(1);
    expect($payload->games->first()->name)->toBe('2027 Kickoff Game');
});
