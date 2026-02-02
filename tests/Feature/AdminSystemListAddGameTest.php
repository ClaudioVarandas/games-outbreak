<?php

use App\Models\Game;
use App\Models\GameList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
});

it('adds existing game to system list by igdb_id', function () {
    Http::fake([
        'id.twitch.tv/oauth2/token' => Http::response(['access_token' => 'test-token'], 200),
        'api.igdb.com/*' => Http::response([], 200),
    ]);

    $admin = User::factory()->create(['is_admin' => true]);
    $list = GameList::factory()->yearly()->system()->create([
        'user_id' => $admin->id,
        'slug' => 'february-2026',
    ]);

    $game = Game::factory()->create(['igdb_id' => 348166]);

    $response = $this->actingAs($admin)
        ->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])
        ->post('/admin/system-lists/yearly/february-2026/games', [
            'game_id' => 348166,
        ]);

    $response->assertJson(['success' => true]);

    $list->refresh();
    expect($list->games()->where('game_id', $game->id)->exists())->toBeTrue();
});

it('fetches game from IGDB if not found locally', function () {
    $igdbId = 348166;

    Http::fake([
        'id.twitch.tv/oauth2/token' => Http::response(['access_token' => 'test-token'], 200),
        'api.igdb.com/v4/games' => Http::response([[
            'id' => $igdbId,
            'name' => 'Test Game from IGDB',
            'first_release_date' => 1704067200,
            'summary' => 'A test game',
            'cover' => ['image_id' => 'co1234'],
            'platforms' => [],
            'genres' => [],
            'game_modes' => [],
            'screenshots' => [],
            'videos' => [],
            'external_games' => [],
            'websites' => [],
            'release_dates' => [],
            'involved_companies' => [],
            'game_engines' => [],
            'player_perspectives' => [],
        ]], 200),
        'store.steampowered.com/*' => Http::response([], 200),
    ]);

    $admin = User::factory()->create(['is_admin' => true]);
    $list = GameList::factory()->yearly()->system()->create([
        'user_id' => $admin->id,
        'slug' => 'february-2026',
    ]);

    expect(Game::where('igdb_id', $igdbId)->exists())->toBeFalse();

    $response = $this->actingAs($admin)
        ->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])
        ->post('/admin/system-lists/yearly/february-2026/games', [
            'game_id' => $igdbId,
        ]);

    $response->assertJson(['success' => true]);

    $game = Game::where('igdb_id', $igdbId)->first();
    expect($game)->not->toBeNull();
    expect($game->name)->toBe('Test Game from IGDB');

    $list->refresh();
    expect($list->games()->where('game_id', $game->id)->exists())->toBeTrue();
});

it('returns 404 when game not found locally or on IGDB', function () {
    $igdbId = 999999999;

    Http::fake([
        'id.twitch.tv/oauth2/token' => Http::response(['access_token' => 'test-token'], 200),
        'api.igdb.com/v4/games' => Http::response([], 200),
    ]);

    $admin = User::factory()->create(['is_admin' => true]);
    $list = GameList::factory()->yearly()->system()->create([
        'user_id' => $admin->id,
        'slug' => 'february-2026',
    ]);

    $response = $this->actingAs($admin)
        ->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])
        ->post('/admin/system-lists/yearly/february-2026/games', [
            'game_id' => $igdbId,
        ]);

    $response->assertStatus(404);
    $response->assertJson(['error' => 'Game not found.']);
});

it('prevents adding duplicate game to system list', function () {
    Http::fake([
        'id.twitch.tv/oauth2/token' => Http::response(['access_token' => 'test-token'], 200),
    ]);

    $admin = User::factory()->create(['is_admin' => true]);
    $list = GameList::factory()->yearly()->system()->create([
        'user_id' => $admin->id,
        'slug' => 'february-2026',
    ]);

    $game = Game::factory()->create(['igdb_id' => 348166]);
    $list->games()->attach($game->id, ['order' => 1]);

    $response = $this->actingAs($admin)
        ->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])
        ->post('/admin/system-lists/yearly/february-2026/games', [
            'game_id' => 348166,
        ]);

    $response->assertJson(['info' => 'Game is already in this list.']);
    expect($list->games()->count())->toBe(1);
});

it('adds game with custom release date and platforms', function () {
    Http::fake([
        'id.twitch.tv/oauth2/token' => Http::response(['access_token' => 'test-token'], 200),
    ]);

    $admin = User::factory()->create(['is_admin' => true]);
    $list = GameList::factory()->yearly()->system()->create([
        'user_id' => $admin->id,
        'slug' => 'february-2026',
    ]);

    $game = Game::factory()->create(['igdb_id' => 348166]);

    $response = $this->actingAs($admin)
        ->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])
        ->post('/admin/system-lists/yearly/february-2026/games', [
            'game_id' => 348166,
            'release_date' => '2026-02-15',
            'platforms' => json_encode([6, 167]),
        ]);

    $response->assertJson(['success' => true]);

    $pivot = $list->games()->where('game_id', $game->id)->first()->pivot;
    $releaseDate = $pivot->release_date instanceof \Carbon\Carbon
        ? $pivot->release_date->format('Y-m-d')
        : \Carbon\Carbon::parse($pivot->release_date)->format('Y-m-d');
    expect($releaseDate)->toBe('2026-02-15');
    expect(json_decode($pivot->platforms, true))->toBe([6, 167]);
});
