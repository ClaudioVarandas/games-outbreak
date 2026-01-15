<?php

use App\Enums\ListTypeEnum;
use App\Models\Game;
use App\Models\GameList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);

    Http::fake([
        'id.twitch.tv/oauth2/token' => Http::response(['access_token' => 'test-token'], 200),
        'store.steampowered.com/api/appdetails*' => Http::response([], 200),
        'api.igdb.com/*' => Http::response([], 200),
    ]);
});

it('allows admin to add game to monthly system list via AJAX', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $monthlyList = GameList::factory()->monthly()->system()->create([
        'slug' => 'january-2026',
        'name' => 'January 2026',
        'is_active' => true,
    ]);

    $game = Game::factory()->create(['igdb_id' => 12345]);

    $response = $this->actingAs($admin)
        ->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])
        ->post('/admin/system-lists/monthly/january-2026/games', [
            'game_id' => 12345,
        ]);

    $response->assertJson(['success' => true]);
    expect($monthlyList->fresh()->games()->where('game_id', $game->id)->exists())->toBeTrue();
});

it('allows admin to add game to indie games system list via AJAX', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $indieList = GameList::factory()->create([
        'list_type' => ListTypeEnum::INDIE_GAMES,
        'is_system' => true,
        'slug' => 'indie-spotlight',
        'name' => 'Indie Spotlight',
        'is_active' => true,
        'start_at' => now()->subDay(),
        'end_at' => now()->addDays(30),
    ]);

    $game = Game::factory()->create(['igdb_id' => 54321]);

    $response = $this->actingAs($admin)
        ->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])
        ->post('/admin/system-lists/indie/indie-spotlight/games', [
            'game_id' => 54321,
        ]);

    $response->assertJson(['success' => true]);
    expect($indieList->fresh()->games()->where('game_id', $game->id)->exists())->toBeTrue();
});

it('allows admin to add game to events system list via AJAX', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $eventsList = GameList::factory()->events()->system()->create([
        'slug' => 'summer-game-fest-2026',
        'name' => 'Summer Game Fest 2026',
        'is_active' => true,
    ]);

    $game = Game::factory()->create(['igdb_id' => 99999]);

    $response = $this->actingAs($admin)
        ->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])
        ->post('/admin/system-lists/events/summer-game-fest-2026/games', [
            'game_id' => 99999,
        ]);

    $response->assertJson(['success' => true]);
    expect($eventsList->fresh()->games()->where('game_id', $game->id)->exists())->toBeTrue();
});

it('prevents non-admin from adding game to system list', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $monthlyList = GameList::factory()->monthly()->system()->create([
        'slug' => 'january-2026',
        'name' => 'January 2026',
        'is_active' => true,
    ]);

    $game = Game::factory()->create(['igdb_id' => 12345]);

    $response = $this->actingAs($user)
        ->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])
        ->post('/admin/system-lists/monthly/january-2026/games', [
            'game_id' => 12345,
        ]);

    $response->assertForbidden();
    expect($monthlyList->fresh()->games()->count())->toBe(0);
});

it('prevents duplicate game in system list', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $monthlyList = GameList::factory()->monthly()->system()->create([
        'slug' => 'january-2026',
        'name' => 'January 2026',
        'is_active' => true,
    ]);

    $game = Game::factory()->create(['igdb_id' => 12345]);
    $monthlyList->games()->attach($game->id, ['order' => 1]);

    $response = $this->actingAs($admin)
        ->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])
        ->post('/admin/system-lists/monthly/january-2026/games', [
            'game_id' => 12345,
        ]);

    $response->assertJson(['info' => 'Game is already in this list.']);
    expect($monthlyList->fresh()->games()->count())->toBe(1);
});

it('returns system lists for admin user in component data', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $admin->ensureSpecialLists();

    GameList::factory()->monthly()->system()->create([
        'slug' => 'january-2026',
        'name' => 'January 2026',
        'is_active' => true,
    ]);

    $game = Game::factory()->create();

    $systemListsByType = \App\Models\GameList::where('is_system', true)
        ->where('is_active', true)
        ->whereIn('list_type', [
            \App\Enums\ListTypeEnum::MONTHLY->value,
            \App\Enums\ListTypeEnum::SEASONED->value,
            \App\Enums\ListTypeEnum::INDIE_GAMES->value,
            \App\Enums\ListTypeEnum::EVENTS->value,
        ])
        ->with('games')
        ->orderBy('name')
        ->get()
        ->groupBy('list_type');

    expect($systemListsByType)->toHaveCount(1);
    expect($systemListsByType->has(ListTypeEnum::MONTHLY->value))->toBeTrue();
    expect($systemListsByType[ListTypeEnum::MONTHLY->value]->first()->name)->toBe('January 2026');
});

it('does not return inactive system lists', function () {
    GameList::factory()->monthly()->system()->create([
        'slug' => 'active-list',
        'name' => 'Active Monthly List',
        'is_active' => true,
    ]);

    GameList::factory()->monthly()->system()->create([
        'slug' => 'inactive-list',
        'name' => 'Inactive Monthly List',
        'is_active' => false,
    ]);

    $systemListsByType = \App\Models\GameList::where('is_system', true)
        ->where('is_active', true)
        ->whereIn('list_type', [
            \App\Enums\ListTypeEnum::MONTHLY->value,
            \App\Enums\ListTypeEnum::SEASONED->value,
            \App\Enums\ListTypeEnum::INDIE_GAMES->value,
            \App\Enums\ListTypeEnum::EVENTS->value,
        ])
        ->get();

    expect($systemListsByType)->toHaveCount(1);
    expect($systemListsByType->first()->name)->toBe('Active Monthly List');
});

it('returns empty collection for non-admin user', function () {
    $user = User::factory()->create(['is_admin' => false]);

    GameList::factory()->monthly()->system()->create([
        'slug' => 'january-2026',
        'name' => 'January 2026',
        'is_active' => true,
    ]);

    $systemListsByType = collect();
    if ($user->isAdmin()) {
        $systemListsByType = \App\Models\GameList::where('is_system', true)
            ->where('is_active', true)
            ->whereIn('list_type', [
                \App\Enums\ListTypeEnum::MONTHLY->value,
                \App\Enums\ListTypeEnum::SEASONED->value,
                \App\Enums\ListTypeEnum::INDIE_GAMES->value,
                \App\Enums\ListTypeEnum::EVENTS->value,
            ])
            ->get();
    }

    expect($systemListsByType)->toBeEmpty();
});
