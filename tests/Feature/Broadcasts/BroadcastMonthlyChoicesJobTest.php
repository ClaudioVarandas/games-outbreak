<?php

use App\Jobs\BroadcastMonthlyChoicesJob;
use App\Models\Game;
use App\Models\GameList;
use App\Services\Broadcasts\Exceptions\BroadcastFailedException;
use App\Services\Broadcasts\MonthlyChoicesBroadcaster;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
});

afterEach(function () {
    Carbon::setTestNow();
});

function attachUpcomingMonthGame(GameList $list, string $name = 'Upcoming Hit'): Game
{
    $game = Game::factory()->create(['name' => $name, 'slug' => str()->slug($name)]);
    $list->games()->attach($game->id, ['release_date' => '2026-05-12 00:00:00']);

    return $game;
}

it('posts to Telegram with the formatted message', function () {
    attachUpcomingMonthGame($this->list);

    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true], 200),
    ]);

    (new BroadcastMonthlyChoicesJob)->handle(app(MonthlyChoicesBroadcaster::class));

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'api.telegram.org/bottest-token/sendMessage')
            && $request['chat_id'] === '@test-channel'
            && $request['parse_mode'] === 'MarkdownV2'
            && str_contains($request['text'], 'Upcoming Hit')
            && str_contains($request['text'], "Next Month's Choices");
    });
});

it('preview mode flows the PREVIEW marker through to the sent text', function () {
    attachUpcomingMonthGame($this->list);

    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true], 200),
    ]);

    (new BroadcastMonthlyChoicesJob(isPreview: true))
        ->handle(app(MonthlyChoicesBroadcaster::class));

    Http::assertSent(fn ($request) => str_contains($request['text'], 'PREVIEW'));
});

it('does not call the X endpoint (X is not registered for monthly)', function () {
    attachUpcomingMonthGame($this->list);

    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true], 200),
    ]);

    (new BroadcastMonthlyChoicesJob)->handle(app(MonthlyChoicesBroadcaster::class));

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'api.x.com'));
});

it('skips silently and sends nothing when the month has no games', function () {
    Http::fake();
    Log::spy();

    (new BroadcastMonthlyChoicesJob)->handle(app(MonthlyChoicesBroadcaster::class));

    Http::assertNothingSent();
    Log::shouldHaveReceived('info')->withArgs(fn ($msg) => $msg === 'monthly-choices.skipped')->once();
});

it('throws a BroadcastFailedException when every channel fails', function () {
    attachUpcomingMonthGame($this->list);

    Http::fake([
        'api.telegram.org/*' => Http::response(['error' => 'nope'], 500),
    ]);

    expect(fn () => (new BroadcastMonthlyChoicesJob)->handle(
        app(MonthlyChoicesBroadcaster::class)
    ))->toThrow(BroadcastFailedException::class);
});

it('only targets the requested channel when onlyChannel is set', function () {
    attachUpcomingMonthGame($this->list);

    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true], 200),
    ]);

    (new BroadcastMonthlyChoicesJob(onlyChannel: 'telegram'))
        ->handle(app(MonthlyChoicesBroadcaster::class));

    Http::assertSentCount(1);
});
