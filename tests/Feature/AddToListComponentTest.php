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

it('allows admin to add game to yearly system list via AJAX', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $yearlyList = GameList::factory()->yearly()->system()->create([
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
        ->post('/admin/system-lists/yearly/january-2026/games', [
            'game_id' => 12345,
        ]);

    $response->assertJson(['success' => true]);
    expect($yearlyList->fresh()->games()->where('game_id', $game->id)->exists())->toBeTrue();
});

it('allows admin to add game to yearly system list with explicit type via AJAX', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $yearlyList = GameList::factory()->create([
        'list_type' => ListTypeEnum::YEARLY,
        'is_system' => true,
        'slug' => 'yearly-2026',
        'name' => 'Yearly 2026',
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
        ->post('/admin/system-lists/yearly/yearly-2026/games', [
            'game_id' => 54321,
        ]);

    $response->assertJson(['success' => true]);
    expect($yearlyList->fresh()->games()->where('game_id', $game->id)->exists())->toBeTrue();
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

    $yearlyList = GameList::factory()->yearly()->system()->create([
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
        ->post('/admin/system-lists/yearly/january-2026/games', [
            'game_id' => 12345,
        ]);

    $response->assertForbidden();
    expect($yearlyList->fresh()->games()->count())->toBe(0);
});

it('prevents duplicate game in system list', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $yearlyList = GameList::factory()->yearly()->system()->create([
        'slug' => 'january-2026',
        'name' => 'January 2026',
        'is_active' => true,
    ]);

    $game = Game::factory()->create(['igdb_id' => 12345]);
    $yearlyList->games()->attach($game->id, ['order' => 1]);

    $response = $this->actingAs($admin)
        ->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])
        ->post('/admin/system-lists/yearly/january-2026/games', [
            'game_id' => 12345,
        ]);

    $response->assertJson(['info' => 'Game is already in this list.']);
    expect($yearlyList->fresh()->games()->count())->toBe(1);
});

it('returns system lists for admin user in component data', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $admin->ensureSpecialLists();

    GameList::factory()->yearly()->system()->create([
        'slug' => 'january-2026',
        'name' => 'January 2026',
        'is_active' => true,
    ]);

    $game = Game::factory()->create();

    $systemListsByType = \App\Models\GameList::where('is_system', true)
        ->where('is_active', true)
        ->whereIn('list_type', [
            \App\Enums\ListTypeEnum::YEARLY->value,
            \App\Enums\ListTypeEnum::SEASONED->value,
            \App\Enums\ListTypeEnum::EVENTS->value,
        ])
        ->with('games')
        ->orderBy('name')
        ->get()
        ->groupBy('list_type');

    expect($systemListsByType)->toHaveCount(1);
    expect($systemListsByType->has(ListTypeEnum::YEARLY->value))->toBeTrue();
    expect($systemListsByType[ListTypeEnum::YEARLY->value]->first()->name)->toBe('January 2026');
});

it('does not return inactive system lists', function () {
    GameList::factory()->yearly()->system()->create([
        'slug' => 'active-list',
        'name' => 'Active Yearly List',
        'is_active' => true,
    ]);

    GameList::factory()->yearly()->system()->create([
        'slug' => 'inactive-list',
        'name' => 'Inactive Yearly List',
        'is_active' => false,
    ]);

    $systemListsByType = \App\Models\GameList::where('is_system', true)
        ->where('is_active', true)
        ->whereIn('list_type', [
            \App\Enums\ListTypeEnum::YEARLY->value,
            \App\Enums\ListTypeEnum::SEASONED->value,
            \App\Enums\ListTypeEnum::EVENTS->value,
        ])
        ->get();

    expect($systemListsByType)->toHaveCount(1);
    expect($systemListsByType->first()->name)->toBe('Active Yearly List');
});

it('returns empty collection for non-admin user', function () {
    $user = User::factory()->create(['is_admin' => false]);

    GameList::factory()->yearly()->system()->create([
        'slug' => 'january-2026',
        'name' => 'January 2026',
        'is_active' => true,
    ]);

    $systemListsByType = collect();
    if ($user->isAdmin()) {
        $systemListsByType = \App\Models\GameList::where('is_system', true)
            ->where('is_active', true)
            ->whereIn('list_type', [
                \App\Enums\ListTypeEnum::YEARLY->value,
                \App\Enums\ListTypeEnum::SEASONED->value,
                \App\Enums\ListTypeEnum::EVENTS->value,
            ])
            ->get();
    }

    expect($systemListsByType)->toBeEmpty();
});
