<?php

use App\Models\Game;
use App\Models\GameList;
use App\Models\GameReleaseDate;
use App\Models\Platform;
use App\Models\ReleaseDateStatus;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(VerifyCsrfToken::class);
    Http::fake([
        'id.twitch.tv/oauth2/token' => Http::response(['access_token' => 'test-token'], 200),
        'api.igdb.com/*' => Http::response([], 200),
    ]);
});

function eaAdminAndList(): array
{
    $admin = User::factory()->create(['is_admin' => true]);
    $list = GameList::factory()->yearly()->system()->create([
        'user_id' => $admin->id,
        'slug' => 'february-2026',
    ]);

    return [$admin, $list];
}

it('adds a game flagged as early access with a release date', function () {
    [$admin, $list] = eaAdminAndList();
    $game = Game::factory()->create(['igdb_id' => 348166]);

    $this->actingAs($admin)
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post('/admin/system-lists/yearly/february-2026/games', [
            'game_id' => 348166,
            'release_date' => '2026-02-15',
            'is_early_access' => '1',
        ])
        ->assertJson(['success' => true]);

    $pivot = $list->games()->where('game_id', $game->id)->first()->pivot;
    expect((bool) $pivot->is_early_access)->toBeTrue()
        ->and((bool) $pivot->is_tba)->toBeFalse();
});

it('rejects a game flagged as both early access and TBA', function () {
    [$admin] = eaAdminAndList();
    Game::factory()->create(['igdb_id' => 348166]);

    $this->actingAs($admin)
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post('/admin/system-lists/yearly/february-2026/games', [
            'game_id' => 348166,
            'is_tba' => '1',
            'is_early_access' => '1',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('is_early_access');
});

it('rejects early access without a release date', function () {
    [$admin] = eaAdminAndList();
    Game::factory()->create(['igdb_id' => 348166, 'first_release_date' => null]);

    $this->actingAs($admin)
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post('/admin/system-lists/yearly/february-2026/games', [
            'game_id' => 348166,
            'is_early_access' => '1',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('release_date');
});

it('suggests early access from IGDB release-date status', function () {
    [$admin, $list] = eaAdminAndList();
    $game = Game::factory()->create(['igdb_id' => 348166]);
    $list->games()->attach($game->id, ['order' => 1]);

    $eaStatus = ReleaseDateStatus::firstOrCreate(['igdb_id' => 3], ['name' => 'Early Access', 'abbreviation' => 'EA']);
    $pc = Platform::firstOrCreate(['igdb_id' => 6], ['name' => 'PC (Microsoft Windows)']);
    GameReleaseDate::create([
        'game_id' => $game->id,
        'platform_id' => $pc->id,
        'status_id' => $eaStatus->id,
        'date' => '2024-12-11',
        'year' => 2024, 'month' => 12, 'day' => 11,
        'human_readable' => '11 Dec 2024',
        'is_manual' => false,
    ]);

    $this->actingAs($admin)
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->get('/admin/system-lists/yearly/february-2026/games/'.$game->id.'/genres')
        ->assertOk()
        ->assertJson([
            'is_early_access' => false,
            'suggested_early_access' => true,
            'suggested_early_access_date' => '2024-12-11',
        ])
        ->assertJsonPath('suggested_early_access_label', fn ($label) => str_contains($label, 'PC') && str_contains($label, '11 Dec 2024'));
});

it('does not suggest early access when no early-access release date exists', function () {
    [$admin, $list] = eaAdminAndList();
    $game = Game::factory()->create(['igdb_id' => 348166]);
    $list->games()->attach($game->id, ['order' => 1]);

    $this->actingAs($admin)
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->get('/admin/system-lists/yearly/february-2026/games/'.$game->id.'/genres')
        ->assertOk()
        ->assertJson(['suggested_early_access' => false]);
});
