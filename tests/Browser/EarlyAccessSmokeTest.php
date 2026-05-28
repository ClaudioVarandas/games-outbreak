<?php

use App\Models\Game;
use App\Models\GameList;
use App\Models\GameReleaseDate;
use App\Models\Platform;
use App\Models\ReleaseDateStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * Seed a yearly system list owned by an admin, containing one game that has an
 * IGDB "Early Access" release date (so the modal's suggestion hint fires).
 *
 * @return array{0: User, 1: GameList, 2: Game}
 */
function eaBrowserFixture(): array
{
    $admin = User::factory()->create(['is_admin' => true]);
    $list = GameList::factory()->yearly()->system()->create([
        'user_id' => $admin->id,
        'slug' => 'february-2026',
        'is_public' => true,
        'is_active' => true,
    ]);

    $game = Game::factory()->create(['name' => 'Early Access Smoke Game']);
    $list->games()->attach($game->id, ['order' => 1, 'release_date' => now()->subDays(5)]);

    $eaStatus = ReleaseDateStatus::firstOrCreate(['igdb_id' => 3], ['name' => 'Early Access', 'abbreviation' => 'EA']);
    $pc = Platform::firstOrCreate(['igdb_id' => 6], ['name' => 'PC (Microsoft Windows)']);
    GameReleaseDate::create([
        'game_id' => $game->id,
        'platform_id' => $pc->id,
        'status_id' => $eaStatus->id,
        'date' => now()->subDays(5),
        'year' => (int) now()->format('Y'),
        'human_readable' => now()->subDays(5)->format('j M Y'),
        'is_manual' => false,
    ]);

    return [$admin, $list, $game];
}

it('loads public pages without JavaScript errors', function () {
    [$admin, $list] = eaBrowserFixture();
    $list->games()->updateExistingPivot($list->games()->first()->id, ['is_early_access' => true]);

    $pages = visit(['/', '/upcoming', '/list/yearly/february-2026']);

    $pages->assertNoJavaScriptErrors();
});

it('renders the EA badge on the public list page', function () {
    [$admin, $list] = eaBrowserFixture();
    $list->games()->updateExistingPivot($list->games()->first()->id, ['is_early_access' => true]);

    visit('/list/yearly/february-2026')
        ->assertNoJavaScriptErrors()
        ->assertSee('EA');
});

it('boots the admin list editor (Vue modals) without JavaScript errors', function () {
    [$admin] = eaBrowserFixture();

    $this->actingAs($admin);

    visit('/admin/system-lists/yearly/february-2026/edit')
        ->assertNoJavaScriptErrors()
        ->assertSee('Early Access Smoke Game');
});

it('opens the edit modal, shows the EA suggestion, and enforces EA/TBA exclusivity', function () {
    [$admin, $list, $game] = eaBrowserFixture();

    $this->actingAs($admin);

    $page = visit('/admin/system-lists/yearly/february-2026/edit');
    $page->assertNoJavaScriptErrors();

    // Open the edit modal directly via the custom event the edit buttons dispatch.
    $page->script(<<<JS
        window.dispatchEvent(new CustomEvent('open-game-edit-modal', {
            detail: { gameId: {$game->id}, mode: 'edit', gameName: 'Early Access Smoke Game' }
        }));
    JS);

    $page->waitForText('Early Access')
        ->assertSee('IGDB lists this game in Early Access')   // suggestion hint
        ->check('#game-form-tba')                              // turn TBA on first
        ->check('#game-form-early-access')                     // ticking EA must clear TBA
        ->assertChecked('#game-form-early-access')
        ->assertNotChecked('#game-form-tba');
});
