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
        'api.igdb.com/*' => Http::response([], 200),
        'store.steampowered.com/*' => Http::response([], 200),
    ]);
});

it('can create events list via admin', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $response = $this->actingAs($admin)
        ->post('/admin/system-lists', [
            'name' => 'Summer Games Fest 2026',
            'description' => 'Games announced at Summer Games Fest',
            'list_type' => 'events',
            'is_active' => true,
            'is_public' => true,
        ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('game_lists', [
        'name' => 'Summer Games Fest 2026',
        'list_type' => 'events',
        'is_system' => true,
        'is_active' => true,
        'is_public' => true,
    ]);
});

it('shows events lists in system lists index', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    GameList::factory()->events()->system()->create([
        'name' => 'Test Event 1',
        'slug' => 'test-event-1',
    ]);

    GameList::factory()->events()->system()->create([
        'name' => 'Test Event 2',
        'slug' => 'test-event-2',
    ]);

    $response = $this->actingAs($admin)->get('/admin/system-lists');

    $response->assertStatus(200);
    $response->assertSee('Events Lists');
    $response->assertSee('Test Event 1');
    $response->assertSee('Test Event 2');
});

it('can add game to events list', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $list = GameList::factory()->events()->system()->create([
        'slug' => 'summer-games-fest-2026',
    ]);

    $game = Game::factory()->create(['igdb_id' => 12345]);

    $response = $this->actingAs($admin)
        ->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])
        ->post('/admin/system-lists/events/summer-games-fest-2026/games', [
            'game_id' => 12345,
        ]);

    $response->assertJson(['success' => true]);

    $list->refresh();
    expect($list->games()->where('game_id', $game->id)->exists())->toBeTrue();
});

it('can remove game from events list', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $list = GameList::factory()->events()->system()->create([
        'slug' => 'summer-games-fest-2026',
    ]);

    $game = Game::factory()->create(['igdb_id' => 12345]);
    $list->games()->attach($game->id, ['order' => 1]);

    $response = $this->actingAs($admin)
        ->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])
        ->delete("/admin/system-lists/events/summer-games-fest-2026/games/{$game->id}");

    $response->assertJson(['success' => true]);

    $list->refresh();
    expect($list->games()->where('game_id', $game->id)->exists())->toBeFalse();
});

it('can edit events list', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $list = GameList::factory()->events()->system()->create([
        'name' => 'Old Event Name',
        'slug' => 'old-event-name',
    ]);

    $response = $this->actingAs($admin)
        ->patch('/admin/system-lists/events/old-event-name', [
            'name' => 'New Event Name',
            'description' => 'Updated description',
        ]);

    $response->assertRedirect();

    $list->refresh();
    expect($list->name)->toBe('New Event Name');
    expect($list->description)->toBe('Updated description');
});

it('can delete events list', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $list = GameList::factory()->events()->system()->create([
        'name' => 'Event to Delete',
        'slug' => 'event-to-delete',
    ]);

    $response = $this->actingAs($admin)
        ->delete('/admin/system-lists/events/event-to-delete');

    $response->assertRedirect('/admin/system-lists');

    $this->assertDatabaseMissing('game_lists', [
        'slug' => 'event-to-delete',
    ]);
});

it('can toggle events list active status', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $list = GameList::factory()->events()->system()->create([
        'slug' => 'test-event',
        'is_active' => true,
    ]);

    $response = $this->actingAs($admin)
        ->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])
        ->patch('/admin/system-lists/events/test-event/toggle');

    $response->assertJson(['success' => true, 'is_active' => false]);

    $list->refresh();
    expect($list->is_active)->toBeFalse();
});

it('events list type is recognized as system list type', function () {
    expect(ListTypeEnum::EVENTS->isSystemListType())->toBeTrue();
});

it('events list scope works correctly', function () {
    GameList::factory()->events()->system()->create(['name' => 'Event List']);
    GameList::factory()->monthly()->system()->create(['name' => 'Monthly List']);

    $eventsLists = GameList::events()->get();

    expect($eventsLists)->toHaveCount(1);
    expect($eventsLists->first()->name)->toBe('Event List');
});

it('isEvents helper method works correctly', function () {
    $eventsList = GameList::factory()->events()->system()->create();
    $monthlyList = GameList::factory()->monthly()->system()->create();

    expect($eventsList->isEvents())->toBeTrue();
    expect($monthlyList->isEvents())->toBeFalse();
});

it('prevents non-admin from creating events list', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $response = $this->actingAs($user)
        ->post('/admin/system-lists', [
            'name' => 'Unauthorized Event',
            'list_type' => 'events',
        ]);

    $response->assertStatus(403);
});
