<?php

use App\Enums\ListTypeEnum;
use App\Models\Game;
use App\Models\GameList;
use App\Models\User;
use App\Services\GameListImportService;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(VerifyCsrfToken::class);

    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->target = GameList::factory()->yearly()->system()->create([
        'user_id' => $this->admin->id,
        'slug' => 'releases-2026',
        'start_at' => '2026-01-01',
        'end_at' => '2026-12-31',
    ]);
    $this->staging = app(GameListImportService::class)->stagingListFor($this->target);
});

function promoteUrl(): string
{
    return '/admin/system-lists/import/releases-2026-import/games/promote';
}

it('promotes a dated game into the target year list and detaches it from staging', function () {
    $game = Game::factory()->create(['igdb_id' => 100, 'name' => 'Dated Game']);
    $this->staging->games()->attach($game->id, [
        'order' => 1,
        'release_date' => '2026-10-15',
        'platforms' => json_encode([6, 167]),
    ]);

    $this->actingAs($this->admin)
        ->postJson(promoteUrl(), ['all' => true])
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('result.inserted', 1)
        ->assertJsonPath('result.detached', 1);

    $pivot = $this->target->games()->where('games.id', $game->id)->first()?->pivot;

    expect($pivot)->not->toBeNull()
        ->and($pivot->release_date)->toStartWith('2026-10-15')
        ->and(json_decode($pivot->platforms, true))->toBe([6, 167])
        ->and($this->staging->games()->count())->toBe(0);
});

it('routes a TBA game to its tagged release year, auto-creating that year list', function () {
    $game = Game::factory()->create(['igdb_id' => 101, 'name' => 'Future Game', 'first_release_date' => null]);
    $this->staging->games()->attach($game->id, [
        'order' => 1,
        'is_tba' => true,
        'release_year' => 2027,
    ]);

    $this->actingAs($this->admin)
        ->postJson(promoteUrl(), ['game_ids' => [$game->id]])
        ->assertSuccessful()
        ->assertJsonPath('result.inserted', 1);

    $yearList2027 = GameList::yearly()->where('is_system', true)->whereYear('start_at', 2027)->first();

    expect($yearList2027)->not->toBeNull()
        ->and($yearList2027->games()->where('games.id', $game->id)->exists())->toBeTrue()
        ->and($this->target->games()->where('games.id', $game->id)->exists())->toBeFalse()
        ->and($this->staging->games()->count())->toBe(0);
});

it('fills missing fields for games already on the target list and detaches them', function () {
    $game = Game::factory()->create(['igdb_id' => 102, 'name' => 'Existing Game']);
    $this->target->games()->attach($game->id, ['order' => 1, 'release_date' => null]);
    $this->staging->games()->attach($game->id, [
        'order' => 1,
        'release_date' => '2026-03-03',
        'video_url' => 'https://www.youtube.com/watch?v=abc12345678',
    ]);

    $this->actingAs($this->admin)
        ->postJson(promoteUrl(), ['all' => true])
        ->assertSuccessful()
        ->assertJsonPath('result.inserted', 0)
        ->assertJsonPath('result.detached', 1);

    $pivot = $this->target->games()->where('games.id', $game->id)->first()->pivot;

    expect($pivot->release_date)->toStartWith('2026-03-03')
        ->and($pivot->video_url)->toBe('https://www.youtube.com/watch?v=abc12345678')
        ->and($this->target->games()->where('games.id', $game->id)->count())->toBe(1)
        ->and($this->staging->games()->count())->toBe(0);
});

it('rejects promote on a non-import list', function () {
    $this->actingAs($this->admin)
        ->postJson('/admin/system-lists/yearly/releases-2026/games/promote', ['all' => true])
        ->assertUnprocessable();
});

it('requires game_ids or all', function () {
    $this->actingAs($this->admin)
        ->postJson(promoteUrl(), [])
        ->assertUnprocessable();
});

it('blocks non-admin users', function () {
    $this->actingAs(User::factory()->create(['is_admin' => false]))
        ->postJson(promoteUrl(), ['all' => true])
        ->assertForbidden();
});

it('shows the import staging section on the admin index', function () {
    Game::factory()->create(['igdb_id' => 300])
        ->gameLists()->attach($this->staging->id, ['order' => 1]);

    $this->actingAs($this->admin)
        ->get('/admin/system-lists')
        ->assertSuccessful()
        ->assertSee('Import Staging')
        ->assertSee('Import: '.$this->target->name)
        ->assertSee('Review &amp; Promote', false);
});

it('shows promote controls on the staging edit page', function () {
    $game = Game::factory()->create(['igdb_id' => 301, 'name' => 'Staged Thing']);
    $this->staging->games()->attach($game->id, ['order' => 1]);

    $this->actingAs($this->admin)
        ->get('/admin/system-lists/import/releases-2026-import/edit')
        ->assertSuccessful()
        ->assertSee('Promote all')
        ->assertSee('Promote selected')
        ->assertSee('Select all')
        ->assertSee('toggleSelected(', false)
        ->assertSee('Import staging list.');
});

it('renders the global toast container and confirm dialog without native alerts', function () {
    $game = Game::factory()->create(['igdb_id' => 304]);
    $this->staging->games()->attach($game->id, ['order' => 1]);

    $response = $this->actingAs($this->admin)
        ->get('/admin/system-lists/import/releases-2026-import/edit')
        ->assertSuccessful()
        ->assertSee('id="toast-container"', false)
        ->assertSee('id="confirm-dialog"', false);

    $content = $response->getContent();

    expect(str_contains($content, 'alert('))->toBeFalse()
        ->and(preg_match('/(?<!\w)confirm\(/', $content))->toBe(0);
});

it('does not show promote selection controls on non-import lists', function () {
    $this->actingAs($this->admin)
        ->get('/admin/system-lists/yearly/releases-2026/edit')
        ->assertSuccessful()
        ->assertDontSee('Promote selected');
});

it('shows import review metadata and existing list membership on the staging page', function () {
    $game = Game::factory()->create(['igdb_id' => 302, 'name' => 'Reviewable Game']);
    $this->target->games()->attach($game->id, ['order' => 1, 'release_date' => '2026-05-05']);
    $this->staging->games()->attach($game->id, [
        'order' => 1,
        'release_date' => '2026-06-06',
        'import_confidence' => 'medium',
        'import_sources' => json_encode(['igdb', 'steam']),
        'import_note' => 'single-source date',
    ]);

    $this->actingAs($this->admin)
        ->get('/admin/system-lists/import/releases-2026-import/edit')
        ->assertSuccessful()
        ->assertSee('Medium')
        ->assertSee('igdb + steam')
        ->assertSee('single-source date')
        ->assertSee('Already on: '.$this->target->name)
        ->assertSee('05/05/2026');
});

it('forces list view on staging pages and hides the view toggle', function () {
    $game = Game::factory()->create(['igdb_id' => 303]);
    $this->staging->games()->attach($game->id, ['order' => 1]);

    session(['game_view_mode' => 'grid']);

    $this->actingAs($this->admin)
        ->get('/admin/system-lists/import/releases-2026-import/edit')
        ->assertSuccessful()
        ->assertDontSee("toggleViewMode('grid')", false);
});

it('keeps the view toggle on non-import lists', function () {
    $this->actingAs($this->admin)
        ->get('/admin/system-lists/yearly/releases-2026/edit')
        ->assertSuccessful()
        ->assertSee("toggleViewMode('grid')", false);
});

it('hides import staging lists from public visitors', function () {
    $this->get('/list/import/releases-2026-import')->assertNotFound();

    expect($this->staging->list_type)->toBe(ListTypeEnum::IMPORT)
        ->and($this->staging->is_public)->toBeFalse()
        ->and($this->staging->is_active)->toBeFalse();
});
