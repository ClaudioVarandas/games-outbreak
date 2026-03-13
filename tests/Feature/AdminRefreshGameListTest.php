<?php

use App\Jobs\RefreshGameListGamesJob;
use App\Models\Game;
use App\Models\GameList;
use App\Models\User;
use App\Services\GameListRefreshService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
});

it('redirects guests to login', function () {
    $list = GameList::factory()->yearly()->system()->create();

    $response = $this->post("/admin/system-lists/yearly/{$list->slug}/refresh");

    $response->assertRedirect('/login');
});

it('returns 403 for non-admin users', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $list = GameList::factory()->yearly()->system()->create();

    $response = $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->post("/admin/system-lists/yearly/{$list->slug}/refresh");

    $response->assertForbidden();
});

it('dispatches refresh job for a yearly list', function () {
    Queue::fake();

    $admin = User::factory()->create(['is_admin' => true]);
    $list = GameList::factory()->yearly()->system()->create(['user_id' => $admin->id]);

    $response = $this->actingAs($admin)
        ->withHeaders(['Accept' => 'application/json'])
        ->post("/admin/system-lists/yearly/{$list->slug}/refresh");

    $response->assertOk()->assertJson(['success' => true]);
    Queue::assertPushed(RefreshGameListGamesJob::class, fn ($job) => $job->gameListId === $list->id);
});

it('dispatches refresh job for a seasoned list', function () {
    Queue::fake();

    $admin = User::factory()->create(['is_admin' => true]);
    $list = GameList::factory()->seasoned()->system()->create(['user_id' => $admin->id]);

    $response = $this->actingAs($admin)
        ->withHeaders(['Accept' => 'application/json'])
        ->post("/admin/system-lists/seasoned/{$list->slug}/refresh");

    $response->assertOk()->assertJson(['success' => true]);
    Queue::assertPushed(RefreshGameListGamesJob::class, fn ($job) => $job->gameListId === $list->id);
});

it('dispatches refresh job for an events list', function () {
    Queue::fake();

    $admin = User::factory()->create(['is_admin' => true]);
    $list = GameList::factory()->events()->system()->create(['user_id' => $admin->id]);

    $response = $this->actingAs($admin)
        ->withHeaders(['Accept' => 'application/json'])
        ->post("/admin/system-lists/events/{$list->slug}/refresh");

    $response->assertOk()->assertJson(['success' => true]);
    Queue::assertPushed(RefreshGameListGamesJob::class, fn ($job) => $job->gameListId === $list->id);
});

it('returns 404 for an invalid list type slug', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $response = $this->actingAs($admin)
        ->withHeaders(['Accept' => 'application/json'])
        ->post('/admin/system-lists/invalid-type/some-slug/refresh');

    $response->assertNotFound();
});

it('returns 404 for a non-existent list slug', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $response = $this->actingAs($admin)
        ->withHeaders(['Accept' => 'application/json'])
        ->post('/admin/system-lists/yearly/does-not-exist/refresh');

    $response->assertNotFound();
});

it('dispatches job on the low queue', function () {
    Queue::fake();

    $admin = User::factory()->create(['is_admin' => true]);
    $list = GameList::factory()->yearly()->system()->create(['user_id' => $admin->id]);

    $this->actingAs($admin)
        ->withHeaders(['Accept' => 'application/json'])
        ->post("/admin/system-lists/yearly/{$list->slug}/refresh");

    Queue::assertPushedOn('low', RefreshGameListGamesJob::class);
});

it('service skips games without an igdb_id', function () {
    Http::fake();

    $admin = User::factory()->create(['is_admin' => true]);
    $list = GameList::factory()->yearly()->system()->create(['user_id' => $admin->id]);
    $game = Game::factory()->create(['igdb_id' => 0]);
    $list->games()->attach($game->id);

    $list->load('games');

    $service = app(GameListRefreshService::class);
    $service->refreshList($list);

    Http::assertNothingSent();
});

it('service skips recently synced games when force is false', function () {
    Http::fake();

    $admin = User::factory()->create(['is_admin' => true]);
    $list = GameList::factory()->yearly()->system()->create(['user_id' => $admin->id]);
    $game = Game::factory()->create([
        'igdb_id' => 12345,
        'last_igdb_sync_at' => now()->subHours(1),
    ]);
    $list->games()->attach($game->id);

    $list->load('games');

    $service = app(GameListRefreshService::class);
    $service->refreshList($list, force: false);

    Http::assertNothingSent();
});

it('service refreshes all games when force is true', function () {
    Http::fake([
        'id.twitch.tv/oauth2/token' => Http::response(['access_token' => 'test-token'], 200),
        'api.igdb.com/v4/games' => Http::response([[
            'id' => 12345,
            'name' => 'Test Game',
            'first_release_date' => 1704067200,
            'cover' => ['image_id' => 'co1234'],
            'platforms' => [],
            'genres' => [],
            'game_modes' => [],
            'involved_companies' => [],
            'game_engines' => [],
            'player_perspectives' => [],
            'external_games' => [],
            'release_dates' => [],
            'game_type' => 0,
        ]], 200),
        '*' => Http::response([], 200),
    ]);

    $admin = User::factory()->create(['is_admin' => true]);
    $list = GameList::factory()->yearly()->system()->create(['user_id' => $admin->id]);
    $game = Game::factory()->create([
        'igdb_id' => 12345,
        'last_igdb_sync_at' => now()->subHours(1),
    ]);
    $list->games()->attach($game->id);

    $list->load('games');

    $service = app(GameListRefreshService::class);
    $service->refreshList($list, force: true);

    $game->refresh();
    expect($game->last_igdb_sync_at)->not->toBeNull();
});
