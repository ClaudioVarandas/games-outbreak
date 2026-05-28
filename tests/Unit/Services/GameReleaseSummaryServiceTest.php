<?php

use App\DTOs\ReleaseHeroSummary;
use App\Models\Game;
use App\Models\GameReleaseDate;
use App\Models\Platform;
use App\Models\ReleaseDateStatus;
use App\Services\GameReleaseSummaryService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function status(string $name, int $igdbId): ReleaseDateStatus
{
    return ReleaseDateStatus::firstOrCreate(['igdb_id' => $igdbId], ['name' => $name, 'abbreviation' => $name]);
}

function platform(int $igdbId, string $name): Platform
{
    return Platform::firstOrCreate(['igdb_id' => $igdbId], ['name' => $name]);
}

function rel(Game $g, Platform $p, ReleaseDateStatus $s, ?Carbon $date, array $extra = []): void
{
    GameReleaseDate::create(array_merge([
        'game_id' => $g->id, 'platform_id' => $p->id, 'status_id' => $s->id,
        'date' => $date, 'year' => $date?->year, 'month' => $date?->month, 'day' => $date?->day,
        'human_readable' => $date?->format('j M Y'), 'is_manual' => false,
    ], $extra));
}

function summary(Game $g): ReleaseHeroSummary
{
    $g->load(['releaseDates.platform', 'releaseDates.status', 'platforms']);

    return app(GameReleaseSummaryService::class)->forHero($g);
}

it('Scenario A: released on all platforms => Available now', function () {
    $g = Game::factory()->create();
    $released = status('Released', 0);
    rel($g, platform(6, 'PC'), $released, now()->subYear());
    rel($g, platform(167, 'PlayStation 5'), $released, now()->subYear());

    $s = summary($g);
    expect($s->primary->label)->toBe('Available now')
        ->and($s->primary->variant)->toBe('success')
        ->and($s->primary->platforms)->toContain('PC')
        ->and($s->secondary)->toBeNull();
});

it('Scenario B: released on some, coming later on others', function () {
    $g = Game::factory()->create();
    $released = status('Released', 0);
    rel($g, platform(6, 'PC'), $released, now()->subMonths(2));
    rel($g, platform(508, 'Nintendo Switch 2'), $released, now()->addMonths(4));

    $s = summary($g);
    expect($s->primary->label)->toBe('Available now')
        ->and($s->secondary?->label)->toBe('Coming later')
        ->and($s->secondary?->variant)->toBe('upcoming');
});

it('Scenario C: early access now, full release upcoming', function () {
    $g = Game::factory()->create();
    rel($g, platform(6, 'PC'), status('Early Access', 3), now()->subMonth());
    rel($g, platform(6, 'PC'), status('Released', 0), now()->addMonth());

    $s = summary($g);
    expect($s->primary->label)->toBe('In Early Access')
        ->and($s->primary->variant)->toBe('early_access')
        ->and($s->secondary?->label)->toBe('Full release');
});

it('Scenario F: future exact date => Coming soon with date', function () {
    $g = Game::factory()->create();
    rel($g, platform(6, 'PC'), status('Released', 0), now()->addDays(20));

    $s = summary($g);
    expect($s->primary->label)->toBe('Coming soon')
        ->and($s->primary->date)->not->toBeNull();
});

it('Scenario G: only year/quarter => Coming soon expected', function () {
    $g = Game::factory()->create();
    GameReleaseDate::create([
        'game_id' => $g->id, 'platform_id' => platform(6, 'PC')->id, 'status_id' => status('Released', 0)->id,
        'date' => null, 'year' => 2027, 'month' => null, 'day' => null, 'human_readable' => 'Q4 2027', 'is_manual' => false,
    ]);

    $s = summary($g);
    expect($s->primary->label)->toBe('Coming soon')
        ->and($s->primary->date)->toContain('2027');
});

it('Scenario H: no dates => Release date TBA', function () {
    $g = Game::factory()->create(['first_release_date' => null]);

    $s = summary($g);
    expect($s->primary->label)->toBe('Release date TBA')
        ->and($s->primary->variant)->toBe('tba');
});

it('fallback: only first_release_date in the past => Available now', function () {
    $g = Game::factory()->create(['first_release_date' => now()->subYear()]);
    $g->platforms()->attach(platform(6, 'PC')->id);

    $s = summary($g);
    expect($s->primary->label)->toBe('Available now');
});
