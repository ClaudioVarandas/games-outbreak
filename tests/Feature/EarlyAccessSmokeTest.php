<?php

use App\Models\Game;
use App\Models\GameList;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(VerifyCsrfToken::class);
});

it('admin system-list edit page loads and mounts the Vue add/edit modals', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $list = GameList::factory()->yearly()->system()->create([
        'user_id' => $admin->id,
        'slug' => 'february-2026',
    ]);
    $list->games()->attach(Game::factory()->create()->id, ['order' => 1]);

    $this->actingAs($admin)
        ->get('/admin/system-lists/yearly/february-2026/edit')
        ->assertOk()
        ->assertSee('game-edit-modals', false)             // GameEditModals mount point
        ->assertSee('data-vue-component="add-game-to-list"', false); // AddGameToList mount point
});

it('public list page renders the EA badge for an early-access entry', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $list = GameList::factory()->events()->system()->create([
        'user_id' => $admin->id,
        'slug' => 'ea-smoke',
        'is_public' => true,
        'is_active' => true,
    ]);
    $list->games()->attach(Game::factory()->create()->id, [
        'order' => 1,
        'is_early_access' => true,
        'release_date' => now()->subDays(5),
    ]);

    $this->get('/list/events/ea-smoke')
        ->assertOk()
        ->assertSee('>EA<', false);
});

it('completes the add-to-list round-trip with the EA flag', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $list = GameList::factory()->yearly()->system()->create([
        'user_id' => $admin->id,
        'slug' => 'february-2026',
    ]);
    $game = Game::factory()->create(['igdb_id' => 348166]);

    $this->actingAs($admin)
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post('/admin/system-lists/yearly/february-2026/games', [
            'game_id' => 348166,
            'release_date' => '2026-02-15',
            'is_early_access' => '1',
        ])
        ->assertJson(['success' => true]);

    expect((bool) $list->games()->first()->pivot->is_early_access)->toBeTrue();
});
