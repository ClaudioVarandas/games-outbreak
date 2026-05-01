<?php

use App\Models\Game;
use App\Models\GameList;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-04-23 09:00:00', 'UTC'));
    Http::preventStrayRequests();

    config([
        'services.telegram.enabled' => true,
        'services.telegram.bot_token' => 'test-token',
        'services.telegram.chat_id' => '@test-channel',
        'services.x.enabled' => false,
    ]);

    $this->list = GameList::factory()->system()->yearly()->active()->create([
        'start_at' => Carbon::create(2026, 1, 1),
        'end_at' => Carbon::create(2026, 12, 31),
    ]);

    $game = Game::factory()->create(['name' => 'Dry Run Game', 'slug' => 'dry-run-game']);
    $this->list->games()->attach($game->id, ['release_date' => '2026-05-12 00:00:00']);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('dry-run prints rendered output and sends nothing', function () {
    Http::fake();

    $this->artisan('monthly-choices:broadcast', ['--dry-run' => true])
        ->expectsOutputToContain('Upcoming window: 2026-05-01 → 2026-05-31')
        ->expectsOutputToContain('Dry Run Game')
        ->assertSuccessful();

    Http::assertNothingSent();
});

it('dry-run with --preview marks the window as PREVIEW and tags the header', function () {
    Http::fake();

    $this->artisan('monthly-choices:broadcast', ['--dry-run' => true, '--preview' => true])
        ->expectsOutputToContain('PREVIEW')
        ->assertSuccessful();

    Http::assertNothingSent();
});

it('actual run dispatches to telegram', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true], 200),
    ]);

    $this->artisan('monthly-choices:broadcast')->assertSuccessful();

    Http::assertSentCount(1);
});

it('rejects an unknown channel', function () {
    $this->artisan('monthly-choices:broadcast', ['--channel' => 'discord'])
        ->assertExitCode(2);
});

it('rejects --channel=x while X is not wired for monthly', function () {
    $this->artisan('monthly-choices:broadcast', ['--channel' => 'x'])
        ->assertExitCode(2);
});

it('--channel=telegram is the default', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true], 200),
    ]);

    $this->artisan('monthly-choices:broadcast')->assertSuccessful();

    Http::assertSentCount(1);
});

it('--channel=all sends to every enabled channel (telegram only for now)', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true], 200),
    ]);

    $this->artisan('monthly-choices:broadcast', ['--channel' => 'all'])->assertSuccessful();

    Http::assertSentCount(1);
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'api.x.com'));
});

it('--current targets the current calendar month and tags the dry-run output', function () {
    $game = Game::factory()->create(['name' => 'Current April Game', 'slug' => 'current-april-game']);
    $this->list->games()->attach($game->id, ['release_date' => '2026-04-15 00:00:00']);

    Http::fake();

    $this->artisan('monthly-choices:broadcast', ['--dry-run' => true, '--current' => true])
        ->expectsOutputToContain('Current window: 2026-04-01')
        ->expectsOutputToContain('Current April Game')
        ->assertSuccessful();

    Http::assertNothingSent();
});

it('live --current dispatches with isCurrent=true', function () {
    $game = Game::factory()->create(['name' => 'Current April Game', 'slug' => 'current-april-game']);
    $this->list->games()->attach($game->id, ['release_date' => '2026-04-15 00:00:00']);

    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true], 200),
    ]);

    $this->artisan('monthly-choices:broadcast', ['--current' => true])->assertSuccessful();

    Http::assertSent(fn ($request) => str_contains($request['text'], "This Month's Choices"));
});
