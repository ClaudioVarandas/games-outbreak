<?php

use App\Models\Game;
use App\Models\GameList;
use App\Services\MonthlyChoicesCollector;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-04-15 09:00:00', 'UTC'));

    $this->list = GameList::factory()->system()->yearly()->active()->create([
        'start_at' => Carbon::create(2026, 1, 1),
        'end_at' => Carbon::create(2026, 12, 31),
    ]);

    $this->thisMonth = Game::factory()->create(['name' => 'This-Month Game']);
    $this->nextMonth = Game::factory()->create(['name' => 'Next-Month Game']);
    $this->twoMonthsOut = Game::factory()->create(['name' => 'Two-Months-Out Game']);

    $this->list->games()->attach($this->thisMonth->id, ['release_date' => '2026-04-20 00:00:00']);
    $this->list->games()->attach($this->nextMonth->id, ['release_date' => '2026-05-12 00:00:00']);
    $this->list->games()->attach($this->twoMonthsOut->id, ['release_date' => '2026-06-10 00:00:00']);

    $this->collector = app(MonthlyChoicesCollector::class);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('forCurrentMonth returns only games in the current calendar month', function () {
    $payload = $this->collector->forCurrentMonth();

    expect($payload->count())->toBe(1);
    expect($payload->games->first()->name)->toBe('This-Month Game');
    expect($payload->windowStart->toDateString())->toBe('2026-04-01');
    expect($payload->windowEnd->toDateString())->toBe('2026-04-30');
    expect($payload->isPreview)->toBeFalse();
    expect($payload->now->toDateString())->toBe('2026-04-15');
});

it('forUpcomingMonth returns only games in next calendar month', function () {
    $payload = $this->collector->forUpcomingMonth();

    expect($payload->count())->toBe(1);
    expect($payload->games->first()->name)->toBe('Next-Month Game');
    expect($payload->windowStart->toDateString())->toBe('2026-05-01');
    expect($payload->windowEnd->toDateString())->toBe('2026-05-31');
});

it('forMonth targets an arbitrary month and stamps the runtime now', function () {
    $payload = $this->collector->forMonth(CarbonImmutable::create(2026, 6, 1));

    expect($payload->count())->toBe(1);
    expect($payload->games->first()->name)->toBe('Two-Months-Out Game');
    expect($payload->windowStart->toDateString())->toBe('2026-06-01');
    expect($payload->windowEnd->toDateString())->toBe('2026-06-30');
    expect($payload->now->toDateString())->toBe('2026-04-15');
});

it('points the CTA at the per-month releases page', function () {
    $april = $this->collector->forCurrentMonth();
    $may = $this->collector->forUpcomingMonth();
    $september = $this->collector->forMonth(CarbonImmutable::create(2026, 9, 1));
    $january2027 = $this->collector->forMonth(CarbonImmutable::create(2027, 1, 1));

    expect($april->ctaUrl)->toEndWith('/releases/2026/04');
    expect($may->ctaUrl)->toEndWith('/releases/2026/05');
    expect($september->ctaUrl)->toEndWith('/releases/2026/09');
    expect($january2027->ctaUrl)->toEndWith('/releases/2027/01');
});

it('flags the payload as preview when requested', function () {
    $payload = $this->collector->forUpcomingMonth(null, isPreview: true);

    expect($payload->isPreview)->toBeTrue();
});

it('returns empty payload when no yearly list exists for the window year', function () {
    GameList::query()->delete();

    $payload = $this->collector->forUpcomingMonth();

    expect($payload->isEmpty())->toBeTrue();
    expect($payload->count())->toBe(0);
});

it('respects the 200-game safety cap', function () {
    $games = Game::factory()->count(210)->create();
    foreach ($games as $i => $game) {
        $this->list->games()->attach($game->id, [
            'release_date' => Carbon::parse('2026-05-01 00:00:00')->addMinutes($i)->toDateTimeString(),
        ]);
    }

    $payload = $this->collector->forUpcomingMonth();

    expect($payload->count())->toBe(200);
});

it('keys the yearly list off the window start year across year rollover', function () {
    $listFor2027 = GameList::factory()->system()->yearly()->active()->create([
        'start_at' => Carbon::create(2027, 1, 1),
        'end_at' => Carbon::create(2027, 12, 31),
    ]);

    $game2027 = Game::factory()->create(['name' => '2027 Kickoff Game']);
    $listFor2027->games()->attach($game2027->id, ['release_date' => '2027-01-08 00:00:00']);

    Carbon::setTestNow(Carbon::parse('2026-12-23 09:00:00', 'UTC'));
    $payload = $this->collector->forUpcomingMonth(CarbonImmutable::now());

    expect($payload->windowStart->year)->toBe(2027);
    expect($payload->windowStart->toDateString())->toBe('2027-01-01');
    expect($payload->count())->toBe(1);
    expect($payload->games->first()->name)->toBe('2027 Kickoff Game');
});
