<?php

use App\Jobs\BroadcastWeeklyChoicesJob;
use App\Models\Game;
use App\Models\GameList;
use App\Services\Broadcasts\Exceptions\BroadcastFailedException;
use App\Services\Broadcasts\WeeklyChoicesBroadcaster;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
});

afterEach(function () {
    Carbon::setTestNow();
});

function attachUpcomingGame(GameList $list, string $name = 'Upcoming Hit'): Game
{
    $game = Game::factory()->create(['name' => $name, 'slug' => str()->slug($name)]);
    $list->games()->attach($game->id, ['release_date' => '2026-04-29 00:00:00']);

    return $game;
}

it('posts to Telegram with the formatted message', function () {
    attachUpcomingGame($this->list);

    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true], 200),
    ]);

    (new BroadcastWeeklyChoicesJob)->handle(app(WeeklyChoicesBroadcaster::class));

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'api.telegram.org/bottest-token/sendMessage')
            && $request['chat_id'] === '@test-channel'
            && $request['parse_mode'] === 'MarkdownV2'
            && str_contains($request['text'], 'Upcoming Hit');
    });
});

it('does not call the X endpoint when X is disabled', function () {
    attachUpcomingGame($this->list);

    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true], 200),
    ]);

    (new BroadcastWeeklyChoicesJob)->handle(app(WeeklyChoicesBroadcaster::class));

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'api.x.com'));
});

it('skips silently and sends nothing when the week has no games', function () {
    Http::fake();
    Log::spy();

    (new BroadcastWeeklyChoicesJob)->handle(app(WeeklyChoicesBroadcaster::class));

    Http::assertNothingSent();
    Log::shouldHaveReceived('info')->withArgs(fn ($msg) => $msg === 'weekly-choices.skipped')->once();
});

it('throws a BroadcastFailedException when every channel fails', function () {
    attachUpcomingGame($this->list);

    Http::fake([
        'api.telegram.org/*' => Http::response(['error' => 'nope'], 500),
    ]);

    expect(fn () => (new BroadcastWeeklyChoicesJob)->handle(
        app(WeeklyChoicesBroadcaster::class)
    ))->toThrow(BroadcastFailedException::class);
});

it('only targets the requested channel when onlyChannel is set', function () {
    attachUpcomingGame($this->list);

    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true], 200),
    ]);

    (new BroadcastWeeklyChoicesJob(onlyChannel: 'telegram'))
        ->handle(app(WeeklyChoicesBroadcaster::class));

    Http::assertSentCount(1);
});
