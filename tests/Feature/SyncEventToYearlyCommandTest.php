<?php

use App\Models\Game;
use App\Models\GameList;
use App\Models\User;
use App\Services\EventYearlySyncService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    User::factory()->create(); // user_id = 1 owner for any auto-created yearly list
});

function eventWithGames(): array
{
    $event = GameList::factory()->events()->system()->create([
        'slug' => 'nacon-connect-2026',
        'start_at' => now()->setDate(2026, 6, 2),
        'end_at' => now()->setDate(2026, 6, 2),
    ]);

    $in2026 = Game::factory()->create(['name' => 'Game A']);
    $in2028 = Game::factory()->create(['name' => 'Game B']);
    $tba = Game::factory()->create(['name' => 'Game C']);

    $event->games()->attach($in2026->id, ['order' => 1, 'release_date' => now()->setDate(2026, 9, 1), 'video_url' => 'https://youtu.be/dQw4w9WgXcQ']);
    $event->games()->attach($in2028->id, ['order' => 2, 'release_date' => now()->setDate(2028, 3, 1)]);
    $event->games()->attach($tba->id, ['order' => 3, 'is_tba' => true]);

    return [$event->fresh('games'), $in2026, $in2028, $tba];
}

it('routes each game to the yearly list for its own release year, TBA to the event year', function () {
    [$event, $in2026, $in2028, $tba] = eventWithGames();

    $plan = app(EventYearlySyncService::class)->plan($event);
    $byId = collect($plan)->keyBy(fn ($p) => $p['game']->id);

    expect($byId[$in2026->id]['target_year'])->toBe(2026)
        ->and($byId[$in2026->id]['has_video'])->toBeTrue()
        ->and($byId[$in2028->id]['target_year'])->toBe(2028)
        ->and($byId[$tba->id]['target_year'])->toBe(2026)
        ->and($byId[$tba->id]['release_label'])->toBe('TBA');
});

it('marks a game already complete in its yearly list as skip', function () {
    [$event, $in2026] = eventWithGames();

    $yearly = GameList::factory()->yearly()->system()->create([
        'slug' => 'game-releases-2026',
        'start_at' => now()->setDate(2026, 1, 1),
        'end_at' => now()->setDate(2026, 12, 31),
    ]);
    $yearly->games()->attach($in2026->id, [
        'order' => 1,
        'release_date' => now()->setDate(2026, 9, 1),
        'platforms' => json_encode([6]),
        'video_url' => 'https://youtu.be/dQw4w9WgXcQ',
    ]);

    $plan = app(EventYearlySyncService::class)->plan($event);
    $entry = collect($plan)->firstWhere(fn ($p) => $p['game']->id === $in2026->id);

    expect($entry['action'])->toBe('skip')
        ->and($entry['fills'])->toBe([]);
});

it('marks a game present in its yearly list but missing a trailer as fill', function () {
    [$event, $in2026] = eventWithGames();

    $yearly = GameList::factory()->yearly()->system()->create([
        'slug' => 'game-releases-2026',
        'start_at' => now()->setDate(2026, 1, 1),
        'end_at' => now()->setDate(2026, 12, 31),
    ]);
    // Present with date + platforms, but NO video_url — the event has one to fill.
    $yearly->games()->attach($in2026->id, [
        'order' => 1,
        'release_date' => now()->setDate(2026, 9, 1),
        'platforms' => json_encode([6]),
        'video_url' => null,
    ]);

    $plan = app(EventYearlySyncService::class)->plan($event);
    $entry = collect($plan)->firstWhere(fn ($p) => $p['game']->id === $in2026->id);

    expect($entry['action'])->toBe('fill')
        ->and($entry['fills'])->toBe(['video_url']);
});

it('inserts games into the correct yearly lists, auto-creating a missing one', function () {
    [$event, $in2026, $in2028, $tba] = eventWithGames();

    $result = app(EventYearlySyncService::class)->apply($event, [$in2026->id, $in2028->id, $tba->id]);

    $list2026 = GameList::yearly()->whereYear('start_at', 2026)->first();
    $list2028 = GameList::yearly()->whereYear('start_at', 2028)->first();

    expect($list2026)->not->toBeNull()
        ->and($list2028)->not->toBeNull()
        ->and($result['inserted'])->toBe(3)
        ->and($list2026->games()->where('games.id', $in2026->id)->exists())->toBeTrue()
        ->and($list2026->games()->where('games.id', $tba->id)->exists())->toBeTrue()
        ->and($list2028->games()->where('games.id', $in2028->id)->exists())->toBeTrue();

    $videoPivot = $list2026->games()->where('games.id', $in2026->id)->first()->pivot;
    expect($videoPivot->video_url)->toBe('https://youtu.be/dQw4w9WgXcQ');

    $tbaPivot = $list2026->games()->where('games.id', $tba->id)->first()->pivot;
    expect((bool) $tbaPivot->is_tba)->toBeTrue();

    // Public-contract bookkeeping the command's summary consumes.
    expect($result['per_year'][2026])->toBe(2) // in2026 + tba
        ->and($result['per_year'][2028])->toBe(1)
        ->and($result['created_years'])->toContain(2026)
        ->and($result['created_years'])->toContain(2028);
});

it('only syncs the selected game ids', function () {
    [$event, $in2026, $in2028] = eventWithGames();

    $result = app(EventYearlySyncService::class)->apply($event, [$in2026->id]);

    expect($result['inserted'])->toBe(1)
        ->and(GameList::yearly()->whereYear('start_at', 2026)->first()->games()->where('games.id', $in2026->id)->exists())->toBeTrue()
        ->and(GameList::yearly()->whereYear('start_at', 2028)->exists())->toBeFalse(); // excluded game never created its year list
});

it('fills a missing video_url on an existing year row without overwriting curated fields', function () {
    [$event, $in2026] = eventWithGames();

    $yearly = GameList::factory()->yearly()->system()->create([
        'slug' => 'game-releases-2026',
        'start_at' => now()->setDate(2026, 1, 1),
        'end_at' => now()->setDate(2026, 12, 31),
    ]);
    $yearly->games()->attach($in2026->id, [
        'order' => 1,
        'release_date' => now()->setDate(2026, 12, 25),
        'platforms' => json_encode([6]),
        'video_url' => null,
        'is_highlight' => true,
    ]);

    $result = app(EventYearlySyncService::class)->apply($event, [$in2026->id]);

    $pivot = $yearly->games()->where('games.id', $in2026->id)->first()->pivot;
    expect($result['inserted'])->toBe(0)
        ->and($result['filled'])->toHaveKey($in2026->id)
        ->and($pivot->video_url)->toBe('https://youtu.be/dQw4w9WgXcQ')
        ->and(Carbon::parse($pivot->release_date)->format('Y-m-d'))->toBe('2026-12-25')
        ->and(json_decode($pivot->platforms, true))->toBe([6])
        ->and((bool) $pivot->is_highlight)->toBeTrue();
});

it('skips a game that is already complete in the year list', function () {
    [$event, $in2026] = eventWithGames();

    $yearly = GameList::factory()->yearly()->system()->create([
        'slug' => 'game-releases-2026',
        'start_at' => now()->setDate(2026, 1, 1),
        'end_at' => now()->setDate(2026, 12, 31),
    ]);
    $yearly->games()->attach($in2026->id, [
        'order' => 1,
        'release_date' => now()->setDate(2026, 9, 1),
        'platforms' => json_encode([6]),
        'video_url' => 'https://youtu.be/dQw4w9WgXcQ',
    ]);

    $result = app(EventYearlySyncService::class)->apply($event, [$in2026->id]);

    expect($result['skipped'])->toBe(1)
        ->and($result['inserted'])->toBe(0)
        ->and($yearly->games()->where('games.id', $in2026->id)->count())->toBe(1);
});

it('syncs all eligible games with --all', function () {
    [$event, $in2026, $in2028, $tba] = eventWithGames();

    $this->artisan('events:sync-to-yearly', ['event' => 'nacon-connect-2026', '--all' => true])
        ->assertSuccessful();

    expect(GameList::yearly()->whereYear('start_at', 2026)->first()->games()->where('games.id', $in2026->id)->exists())->toBeTrue()
        ->and(GameList::yearly()->whereYear('start_at', 2028)->first()->games()->where('games.id', $in2028->id)->exists())->toBeTrue();
});

it('accepts a numeric id as the event argument', function () {
    [$event, $in2026] = eventWithGames();

    $this->artisan('events:sync-to-yearly', ['event' => (string) $event->id, '--all' => true])
        ->assertSuccessful();

    expect(GameList::yearly()->whereYear('start_at', 2026)->first()->games()->where('games.id', $in2026->id)->exists())->toBeTrue();
});

it('fails when the events list does not exist', function () {
    $this->artisan('events:sync-to-yearly', ['event' => 'does-not-exist', '--all' => true])
        ->assertFailed();
});

it('fails when the slug resolves to a non-events list', function () {
    GameList::factory()->yearly()->system()->create(['slug' => 'game-releases-2026']);

    $this->artisan('events:sync-to-yearly', ['event' => 'game-releases-2026', '--all' => true])
        ->assertFailed();
});
