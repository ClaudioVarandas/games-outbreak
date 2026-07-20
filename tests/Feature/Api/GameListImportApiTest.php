<?php

use App\Enums\ListTypeEnum;
use App\Models\Game;
use App\Models\GameList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.import.token' => 'test-import-token']);
});

function importHeaders(): array
{
    return ['Authorization' => 'Bearer test-import-token'];
}

function makeYearlyList(string $slug = 'releases-2026'): GameList
{
    return GameList::factory()->yearly()->system()->create([
        'user_id' => User::factory()->create(['is_admin' => true])->id,
        'slug' => $slug,
    ]);
}

it('returns 503 when no import token is configured', function () {
    config(['services.import.token' => null]);

    $this->postJson('/api/v1/import/check', ['items' => [['name' => 'X']]])
        ->assertServiceUnavailable();
});

it('returns 401 for a missing or wrong bearer token', function () {
    $this->postJson('/api/v1/import/check', ['items' => [['name' => 'X']]])
        ->assertUnauthorized();

    $this->withHeaders(['Authorization' => 'Bearer wrong-token'])
        ->postJson('/api/v1/import/check', ['items' => [['name' => 'X']]])
        ->assertUnauthorized();
});

it('reports unknown games as missing in check', function () {
    $this->withHeaders(importHeaders())
        ->postJson('/api/v1/import/check', ['items' => [['name' => 'Totally Unknown Game']]])
        ->assertSuccessful()
        ->assertJsonPath('results.0.exists', false)
        ->assertJsonPath('results.0.name', 'Totally Unknown Game');
});

it('reports existing games with their system list membership', function () {
    $list = makeYearlyList();
    $game = Game::factory()->create(['igdb_id' => 4321, 'name' => 'Phantom Blade Zero']);
    $list->games()->attach($game->id, ['order' => 1]);

    $this->withHeaders(importHeaders())
        ->postJson('/api/v1/import/check', [
            'list_slug' => 'releases-2026',
            'items' => [['name' => 'phantom blade zero']],
        ])
        ->assertSuccessful()
        ->assertJsonPath('results.0.exists', true)
        ->assertJsonPath('results.0.igdb_id', 4321)
        ->assertJsonPath('results.0.on_target_list', true)
        ->assertJsonPath('results.0.lists.0.slug', 'releases-2026');
});

it('reports staged games via on_staging_list in check', function () {
    makeYearlyList();
    Game::factory()->create(['igdb_id' => 555, 'name' => 'Staged Game']);

    $this->withHeaders(importHeaders())
        ->postJson('/api/v1/import/list-items', [
            'list_slug' => 'releases-2026',
            'items' => [['igdb_id' => 555]],
        ])
        ->assertSuccessful();

    $this->withHeaders(importHeaders())
        ->postJson('/api/v1/import/check', [
            'list_slug' => 'releases-2026',
            'items' => [['name' => 'Staged Game']],
        ])
        ->assertSuccessful()
        ->assertJsonPath('results.0.on_staging_list', true)
        ->assertJsonPath('results.0.on_target_list', false);
});

it('matches check items by igdb_id before name', function () {
    Game::factory()->create(['igdb_id' => 111, 'name' => 'Same Name']);
    $byId = Game::factory()->create(['igdb_id' => 222, 'name' => 'Other Name']);

    $this->withHeaders(importHeaders())
        ->postJson('/api/v1/import/check', [
            'items' => [['name' => 'Same Name', 'igdb_id' => 222]],
        ])
        ->assertSuccessful()
        ->assertJsonPath('results.0.game_slug', $byId->slug);
});

it('validates check payload', function () {
    $this->withHeaders(importHeaders())
        ->postJson('/api/v1/import/check', ['items' => []])
        ->assertUnprocessable();

    $this->withHeaders(importHeaders())
        ->postJson('/api/v1/import/check', ['items' => [['igdb_id' => 5]]])
        ->assertUnprocessable();
});

it('rejects imports into an unknown or non yearly/seasoned list', function () {
    $this->withHeaders(importHeaders())
        ->postJson('/api/v1/import/list-items', [
            'list_slug' => 'nope',
            'items' => [['igdb_id' => 1]],
        ])
        ->assertUnprocessable();

    GameList::factory()->events()->system()->create([
        'user_id' => User::factory()->create()->id,
        'slug' => 'some-event',
    ]);

    $this->withHeaders(importHeaders())
        ->postJson('/api/v1/import/list-items', [
            'list_slug' => 'some-event',
            'items' => [['igdb_id' => 1]],
        ])
        ->assertUnprocessable();
});

it('rejects more than 10 items per request', function () {
    makeYearlyList();

    $items = collect(range(1, 11))->map(fn (int $i) => ['igdb_id' => $i])->all();

    $this->withHeaders(importHeaders())
        ->postJson('/api/v1/import/list-items', [
            'list_slug' => 'releases-2026',
            'items' => $items,
        ])
        ->assertUnprocessable();
});

it('stages games on a hidden import list instead of the target list', function () {
    $list = makeYearlyList();
    $game = Game::factory()->create(['igdb_id' => 777, 'name' => 'Gears of War: E-Day']);

    $this->withHeaders(importHeaders())
        ->postJson('/api/v1/import/list-items', [
            'list_slug' => 'releases-2026',
            'items' => [[
                'igdb_id' => 777,
                'release_date' => '2026-10-15',
                'platforms' => [6, 169],
                'confidence' => 'high',
                'sources' => ['igdb', 'steam'],
            ]],
        ])
        ->assertSuccessful()
        ->assertJsonPath('results.0.status', 'attached')
        ->assertJsonPath('results.0.confidence', 'high')
        ->assertJsonPath('staging_list_slug', 'releases-2026-import');

    $staging = GameList::where('slug', 'releases-2026-import')->first();

    expect($staging)->not->toBeNull()
        ->and($staging->list_type)->toBe(ListTypeEnum::IMPORT)
        ->and($staging->is_public)->toBeFalse()
        ->and($staging->is_active)->toBeFalse()
        ->and($staging->is_system)->toBeTrue()
        ->and($staging->import_target_list_id)->toBe($list->id)
        ->and($list->games()->count())->toBe(0);

    $pivot = $staging->games()->where('games.id', $game->id)->first()->pivot;

    expect($pivot->release_date)->toStartWith('2026-10-15')
        ->and(json_decode($pivot->platforms, true))->toBe([6, 169])
        ->and((bool) $pivot->is_tba)->toBeFalse()
        ->and($pivot->import_confidence)->toBe('high')
        ->and(json_decode($pivot->import_sources, true))->toBe(['igdb', 'steam']);
});

it('persists the import note on the staging pivot', function () {
    makeYearlyList();
    Game::factory()->create(['igdb_id' => 606]);

    $this->withHeaders(importHeaders())
        ->postJson('/api/v1/import/list-items', [
            'list_slug' => 'releases-2026',
            'items' => [[
                'igdb_id' => 606,
                'confidence' => 'medium',
                'note' => 'single-source date',
            ]],
        ])
        ->assertSuccessful();

    $pivot = GameList::where('slug', 'releases-2026-import')->first()->games()->first()->pivot;

    expect($pivot->import_confidence)->toBe('medium')
        ->and($pivot->import_note)->toBe('single-source date');
});

it('rejects an unknown confidence value', function () {
    makeYearlyList();

    $this->withHeaders(importHeaders())
        ->postJson('/api/v1/import/list-items', [
            'list_slug' => 'releases-2026',
            'items' => [['igdb_id' => 1, 'confidence' => 'certain']],
        ])
        ->assertUnprocessable();
});

it('reuses the same staging list across imports', function () {
    makeYearlyList();
    Game::factory()->create(['igdb_id' => 701]);
    Game::factory()->create(['igdb_id' => 702]);

    foreach ([701, 702] as $igdbId) {
        $this->withHeaders(importHeaders())
            ->postJson('/api/v1/import/list-items', [
                'list_slug' => 'releases-2026',
                'items' => [['igdb_id' => $igdbId]],
            ])
            ->assertSuccessful();
    }

    expect(GameList::where('list_type', ListTypeEnum::IMPORT)->count())->toBe(1)
        ->and(GameList::where('slug', 'releases-2026-import')->first()->games()->count())->toBe(2);
});

it('attaches a TBA game without a release date but with a release year', function () {
    makeYearlyList();
    $game = Game::factory()->create(['igdb_id' => 888, 'name' => 'Silent Hill Townfall']);

    $this->withHeaders(importHeaders())
        ->postJson('/api/v1/import/list-items', [
            'list_slug' => 'releases-2026',
            'items' => [[
                'igdb_id' => 888,
                'is_tba' => true,
                'release_year' => 2026,
            ]],
        ])
        ->assertSuccessful()
        ->assertJsonPath('results.0.status', 'attached');

    $staging = GameList::where('slug', 'releases-2026-import')->first();
    $pivot = $staging->games()->where('games.id', $game->id)->first()->pivot;

    expect((bool) $pivot->is_tba)->toBeTrue()
        ->and($pivot->release_date)->toBeNull()
        ->and((int) $pivot->release_year)->toBe(2026);
});

it('reports games already staged without duplicating them', function () {
    makeYearlyList();
    $game = Game::factory()->create(['igdb_id' => 999]);

    foreach (range(1, 2) as $attempt) {
        $response = $this->withHeaders(importHeaders())
            ->postJson('/api/v1/import/list-items', [
                'list_slug' => 'releases-2026',
                'items' => [['igdb_id' => 999]],
            ])
            ->assertSuccessful();
    }

    $response->assertJsonPath('results.0.status', 'already_on_list');

    $staging = GameList::where('slug', 'releases-2026-import')->first();
    expect($staging->games()->where('games.id', $game->id)->count())->toBe(1);
});

it('fetches missing games from IGDB before attaching', function () {
    Http::fake([
        'id.twitch.tv/oauth2/token' => Http::response(['access_token' => 'token'], 200),
        'api.igdb.com/v4/games' => Http::response([[
            'id' => 51000,
            'name' => 'Brand New Game',
            'first_release_date' => 1760486400,
            'summary' => 'Fresh from IGDB',
            'platforms' => [],
            'genres' => [],
            'game_modes' => [],
            'screenshots' => [],
            'videos' => [],
            'external_games' => [],
            'websites' => [],
            'release_dates' => [],
            'involved_companies' => [],
        ]], 200),
    ]);

    $list = makeYearlyList();

    $this->withHeaders(importHeaders())
        ->postJson('/api/v1/import/list-items', [
            'list_slug' => 'releases-2026',
            'items' => [[
                'igdb_id' => 51000,
                'release_date' => '2025-10-15',
                'platforms' => [6],
            ]],
        ])
        ->assertSuccessful()
        ->assertJsonPath('results.0.status', 'attached')
        ->assertJsonPath('results.0.game_name', 'Brand New Game');

    expect(Game::where('igdb_id', 51000)->exists())->toBeTrue()
        ->and($list->games()->count())->toBe(0)
        ->and(GameList::where('slug', 'releases-2026-import')->first()->games()->count())->toBe(1);
});

it('reports unknown igdb ids as game_not_found', function () {
    Http::fake([
        'id.twitch.tv/oauth2/token' => Http::response(['access_token' => 'token'], 200),
        'api.igdb.com/v4/games' => Http::response([], 200),
    ]);

    makeYearlyList();

    $this->withHeaders(importHeaders())
        ->postJson('/api/v1/import/list-items', [
            'list_slug' => 'releases-2026',
            'items' => [['igdb_id' => 424242]],
        ])
        ->assertSuccessful()
        ->assertJsonPath('results.0.status', 'game_not_found');
});
