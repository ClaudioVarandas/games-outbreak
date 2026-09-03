<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function fakeIgdbEventSearch(array $rows): void
{
    Http::fake([
        'id.twitch.tv/oauth2/token' => Http::response(['access_token' => 'token'], 200),
        'api.igdb.com/v4/events' => Http::response($rows, 200),
    ]);
}

it('returns IGDB event matches for an admin', function () {
    fakeIgdbEventSearch([
        ['id' => 251, 'name' => 'Future Games Show', 'slug' => 'future-games-show', 'start_time' => 1749500000],
        ['id' => 252, 'name' => 'Future Games Show Spring', 'slug' => 'fgs-spring', 'start_time' => 1717000000],
    ]);

    $admin = User::factory()->create(['is_admin' => true]);

    $response = $this->actingAs($admin)
        ->getJson(route('admin.system-lists.igdb-events.search', ['q' => 'Future Games Show']));

    $response->assertSuccessful()
        ->assertJsonPath('results.0.id', 251)
        ->assertJsonPath('results.0.name', 'Future Games Show')
        ->assertJsonPath('results.0.slug', 'future-games-show')
        ->assertJsonPath('results.0.url', 'https://www.igdb.com/events/future-games-show')
        ->assertJsonCount(2, 'results');
});

it('requires a query of at least two characters', function () {
    fakeIgdbEventSearch([]);
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->getJson(route('admin.system-lists.igdb-events.search', ['q' => '']))
        ->assertStatus(422);
});

it('requires either a query or an id', function () {
    fakeIgdbEventSearch([]);
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->getJson(route('admin.system-lists.igdb-events.search'))
        ->assertStatus(422);
});

it('looks up a single IGDB event by id, ignoring the name', function () {
    fakeIgdbEventSearch([
        ['id' => 1170, 'name' => 'State of Play: 2026-9-3', 'slug' => 'state-of-play-2026-9-3', 'start_time' => 1788556800],
    ]);

    $admin = User::factory()->create(['is_admin' => true]);

    $response = $this->actingAs($admin)
        ->getJson(route('admin.system-lists.igdb-events.search', ['id' => 1170]));

    $response->assertSuccessful()
        ->assertJsonCount(1, 'results')
        ->assertJsonPath('results.0.id', 1170)
        ->assertJsonPath('results.0.name', 'State of Play: 2026-9-3')
        ->assertJsonPath('results.0.slug', 'state-of-play-2026-9-3');
});

it('returns no results when the id does not exist on IGDB', function () {
    fakeIgdbEventSearch([]);
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->getJson(route('admin.system-lists.igdb-events.search', ['id' => 999999999]))
        ->assertSuccessful()
        ->assertJsonCount(0, 'results');
});

it('forbids non-admin users from searching', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)
        ->getJson(route('admin.system-lists.igdb-events.search', ['q' => 'whatever']))
        ->assertForbidden();
});
