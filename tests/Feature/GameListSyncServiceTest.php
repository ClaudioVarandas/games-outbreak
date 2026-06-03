<?php

use App\Models\Game;
use App\Models\GameList;
use App\Models\Genre;
use App\Models\User;
use App\Services\GameListSyncService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function syncService(): GameListSyncService
{
    return app(GameListSyncService::class);
}

beforeEach(function () {
    User::factory()->create(); // ensures user_id = 1 exists for system list ownership
});

it('finds nothing when no yearly list exists for the year', function () {
    expect(syncService()->findYearlyList(2031))->toBeNull();
});

it('creates a yearly system list with the expected attributes', function () {
    $list = syncService()->firstOrCreateYearlyList(2031);

    expect($list->list_type->value)->toBe('yearly')
        ->and($list->is_system)->toBeTrue()
        ->and($list->is_public)->toBeTrue()
        ->and($list->is_active)->toBeTrue()
        ->and($list->name)->toBe('Game Releases 2031')
        ->and($list->slug)->toBe('game-releases-2031')
        ->and($list->start_at->format('Y-m-d'))->toBe('2031-01-01')
        ->and($list->end_at->format('Y-m-d'))->toBe('2031-12-31');
});

it('returns the existing yearly list instead of creating a duplicate', function () {
    $first = syncService()->firstOrCreateYearlyList(2031);
    $second = syncService()->firstOrCreateYearlyList(2031);

    expect($second->id)->toBe($first->id)
        ->and(GameList::yearly()->whereYear('start_at', 2031)->count())->toBe(1);
});

it('resolvePlatforms decodes a json pivot string', function () {
    $game = Game::factory()->create();

    expect(syncService()->resolvePlatforms($game, '[6,48]'))->toBe([6, 48]);
});

it('resolvePlatforms returns an integer array unchanged', function () {
    $game = Game::factory()->create();

    expect(syncService()->resolvePlatforms($game, [6, 48]))->toBe([6, 48]);
});

it('insertGame attaches with order, platform_group, encoded platforms and video_url', function () {
    $list = syncService()->firstOrCreateYearlyList(2031);
    $game = Game::factory()->create();
    $genre = Genre::factory()->create();

    syncService()->insertGame($list, $game, [
        'release_date' => '2031-03-14',
        'platforms' => [6, 48],
        'is_tba' => false,
        'is_early_access' => false,
        'genre_ids' => [$genre->id],
        'primary_genre_id' => $genre->id,
        'video_url' => 'https://youtu.be/dQw4w9WgXcQ',
    ]);

    $pivot = $list->games()->where('games.id', $game->id)->first()->pivot;
    expect((int) $pivot->order)->toBe(1)
        ->and(json_decode($pivot->platforms, true))->toBe([6, 48])
        ->and(json_decode($pivot->genre_ids, true))->toBe([$genre->id])
        ->and($pivot->video_url)->toBe('https://youtu.be/dQw4w9WgXcQ')
        ->and($pivot->platform_group)->not->toBeNull();
});

it('fillMissing only fills empty fields and reports them', function () {
    $list = syncService()->firstOrCreateYearlyList(2031);
    $game = Game::factory()->create();

    $list->games()->attach($game->id, [
        'order' => 1,
        'release_date' => '2031-05-01',
        'platforms' => json_encode([6]),
        'video_url' => null,
    ]);

    $filled = syncService()->fillMissing($list, $game, [
        'release_date' => '2031-09-09',
        'platforms' => [6, 48],
        'video_url' => 'https://youtu.be/dQw4w9WgXcQ',
    ]);

    $pivot = $list->games()->where('games.id', $game->id)->first()->pivot;
    expect($filled)->toBe(['video_url'])
        ->and($pivot->video_url)->toBe('https://youtu.be/dQw4w9WgXcQ')
        ->and($pivot->release_date)->not->toBeNull()
        ->and(Carbon::parse($pivot->release_date)->format('Y-m-d'))->toBe('2031-05-01')
        ->and(json_decode($pivot->platforms, true))->toBe([6]);
});

it('fillMissing writes nothing and returns [] when the row is already complete', function () {
    $list = syncService()->firstOrCreateYearlyList(2031);
    $game = Game::factory()->create();

    $list->games()->attach($game->id, [
        'order' => 1,
        'release_date' => '2031-05-01',
        'platforms' => json_encode([6]),
        'video_url' => 'https://youtu.be/existing',
    ]);

    $filled = syncService()->fillMissing($list, $game, [
        'release_date' => '2031-09-09',
        'platforms' => [6, 48],
        'video_url' => 'https://youtu.be/dQw4w9WgXcQ',
    ]);

    $pivot = $list->games()->where('games.id', $game->id)->first()->pivot;
    expect($filled)->toBe([])
        ->and($pivot->video_url)->toBe('https://youtu.be/existing')
        ->and(Carbon::parse($pivot->release_date)->format('Y-m-d'))->toBe('2031-05-01')
        ->and(json_decode($pivot->platforms, true))->toBe([6]);
});

it('fillMissing returns [] when the game is not in the list', function () {
    $list = syncService()->firstOrCreateYearlyList(2031);
    $game = Game::factory()->create();

    expect(syncService()->fillMissing($list, $game, ['video_url' => 'https://youtu.be/x']))->toBe([])
        ->and($list->games()->where('games.id', $game->id)->exists())->toBeFalse();
});
