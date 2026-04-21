<?php

use App\Models\Game;
use App\Models\GameList;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-04-26 21:00:00', 'Europe/Lisbon'));
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
    $this->list->games()->attach($game->id, ['release_date' => '2026-04-29 00:00:00']);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('dry-run prints rendered output and sends nothing', function () {
    Http::fake();

    $this->artisan('weekly-choices:broadcast', ['--dry-run' => true])
        ->expectsOutputToContain('Upcoming window: 2026-04-27 → 2026-05-03')
        ->expectsOutputToContain('Dry Run Game')
        ->assertSuccessful();

    Http::assertNothingSent();
});

it('actual run dispatches to telegram', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true], 200),
    ]);

    $this->artisan('weekly-choices:broadcast')->assertSuccessful();

    Http::assertSentCount(1);
});

it('rejects an unknown channel', function () {
    $this->artisan('weekly-choices:broadcast', ['--channel' => 'discord'])
        ->assertExitCode(2);
});

it('scopes to a single channel with --channel=telegram', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true], 200),
    ]);

    $this->artisan('weekly-choices:broadcast', ['--channel' => 'telegram'])->assertSuccessful();

    Http::assertSentCount(1);
});
