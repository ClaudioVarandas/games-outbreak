<?php

use App\Enums\ListTypeEnum;
use App\Enums\PlatformGroupEnum;
use App\Models\Game;
use App\Models\GameList;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
});

describe('Highlights Page', function () {
    it('displays the highlights page when no active list exists', function () {
        $response = $this->get(route('highlights'));

        $response->assertStatus(200);
        $response->assertSee('Highlights');
        $response->assertSee('No active highlights list at the moment.');
    });

    it('displays the active highlights list with games grouped by platform', function () {
        $list = GameList::factory()->create([
            'name' => 'January 2026 Highlights',
            'list_type' => ListTypeEnum::HIGHLIGHTS,
            'is_active' => true,
            'is_public' => true,
            'is_system' => true,
        ]);

        $game1 = Game::factory()->create(['name' => 'Game One']);
        $game2 = Game::factory()->create(['name' => 'Game Two']);

        $list->games()->attach($game1->id, [
            'order' => 1,
            'platform_group' => PlatformGroupEnum::MULTIPLATFORM->value,
        ]);
        $list->games()->attach($game2->id, [
            'order' => 2,
            'platform_group' => PlatformGroupEnum::PLAYSTATION->value,
        ]);

        $response = $this->get(route('highlights'));

        $response->assertStatus(200);
        $response->assertSee('January 2026 Highlights');
        $response->assertSee('Game One');
        $response->assertSee('Game Two');
        $response->assertSee('Multiplatform');
        $response->assertSee('PlayStation');
    });

    it('does not show inactive highlights lists', function () {
        $list = GameList::factory()->create([
            'name' => 'Inactive Highlights',
            'list_type' => ListTypeEnum::HIGHLIGHTS,
            'is_active' => false,
            'is_public' => true,
            'is_system' => true,
        ]);

        $response = $this->get(route('highlights'));

        $response->assertStatus(200);
        $response->assertDontSee('Inactive Highlights');
        $response->assertSee('No active highlights list at the moment.');
    });
});

describe('Admin Highlights List Management', function () {
    it('allows admin to create a highlights list', function () {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.system-lists.store'), [
                'name' => 'February 2026 Highlights',
                'list_type' => 'highlights',
                'is_public' => true,
                'is_active' => true,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('game_lists', [
            'name' => 'February 2026 Highlights',
            'list_type' => ListTypeEnum::HIGHLIGHTS->value,
        ]);
    });

    it('auto-suggests platform group when adding a game to highlights list', function () {
        $list = GameList::factory()->create([
            'name' => 'Test Highlights',
            'list_type' => ListTypeEnum::HIGHLIGHTS,
            'is_active' => true,
            'is_public' => true,
            'is_system' => true,
        ]);

        $game = Game::factory()->create([
            'name' => 'Test Game',
            'igdb_id' => 12345,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.system-lists.games.add', ['type' => 'highlights', 'slug' => $list->slug]), [
                'game_id' => $game->igdb_id,
            ]);

        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('game_list_game', [
            'game_list_id' => $list->id,
            'game_id' => $game->id,
        ]);

        // Verify platform_group was auto-assigned
        $pivotRecord = $list->games()->where('game_id', $game->id)->first();
        expect($pivotRecord->pivot->platform_group)->not->toBeNull();
    });

    it('allows updating platform group for a game in highlights list', function () {
        $list = GameList::factory()->create([
            'name' => 'Test Highlights',
            'list_type' => ListTypeEnum::HIGHLIGHTS,
            'is_active' => true,
            'is_public' => true,
            'is_system' => true,
        ]);

        $game = Game::factory()->create(['name' => 'Test Game']);

        $list->games()->attach($game->id, [
            'order' => 1,
            'platform_group' => PlatformGroupEnum::MULTIPLATFORM->value,
        ]);

        $response = $this->actingAs($this->admin)
            ->patchJson(route('admin.system-lists.games.platform-group', [
                'type' => 'highlights',
                'slug' => $list->slug,
                'game' => $game->id,
            ]), [
                'platform_group' => PlatformGroupEnum::NINTENDO->value,
            ]);

        $response->assertJson(['success' => true]);

        $pivotRecord = $list->games()->where('game_id', $game->id)->first();
        expect($pivotRecord->pivot->platform_group)->toBe(PlatformGroupEnum::NINTENDO->value);
    });
});

describe('PlatformGroupEnum', function () {
    it('suggests multiplatform for games on multiple major platforms', function () {
        // PC (6) and PS5 (167)
        $result = PlatformGroupEnum::suggestFromPlatforms([6, 167]);
        expect($result)->toBe(PlatformGroupEnum::MULTIPLATFORM);
    });

    it('suggests playstation for PS-only games', function () {
        // PS4 (48) and PS5 (167)
        $result = PlatformGroupEnum::suggestFromPlatforms([48, 167]);
        expect($result)->toBe(PlatformGroupEnum::PLAYSTATION);
    });

    it('suggests nintendo for Switch-only games', function () {
        // Switch (130)
        $result = PlatformGroupEnum::suggestFromPlatforms([130]);
        expect($result)->toBe(PlatformGroupEnum::NINTENDO);
    });

    it('suggests xbox for Xbox-only games', function () {
        // Xbox One (49) and Xbox X/S (169)
        $result = PlatformGroupEnum::suggestFromPlatforms([49, 169]);
        expect($result)->toBe(PlatformGroupEnum::XBOX);
    });

    it('suggests pc exclusive for PC-only games', function () {
        // PC (6)
        $result = PlatformGroupEnum::suggestFromPlatforms([6]);
        expect($result)->toBe(PlatformGroupEnum::PC);
    });

    it('suggests mobile for mobile-only games', function () {
        // Android (34) and iOS (39)
        $result = PlatformGroupEnum::suggestFromPlatforms([34, 39]);
        expect($result)->toBe(PlatformGroupEnum::MOBILE);
    });
});

describe('GameList canHaveHighlights', function () {
    it('returns true for monthly lists', function () {
        $list = GameList::factory()->create([
            'list_type' => ListTypeEnum::MONTHLY,
            'is_system' => true,
        ]);

        expect($list->canHaveHighlights())->toBeTrue();
    });

    it('returns true for indie games lists', function () {
        $list = GameList::factory()->create([
            'list_type' => ListTypeEnum::INDIE_GAMES,
            'is_system' => true,
        ]);

        expect($list->canHaveHighlights())->toBeTrue();
    });

    it('returns false for highlights lists', function () {
        $list = GameList::factory()->create([
            'list_type' => ListTypeEnum::HIGHLIGHTS,
            'is_system' => true,
        ]);

        expect($list->canHaveHighlights())->toBeFalse();
    });

    it('returns false for regular lists', function () {
        $list = GameList::factory()->create([
            'list_type' => ListTypeEnum::REGULAR,
        ]);

        expect($list->canHaveHighlights())->toBeFalse();
    });
});

describe('Toggle Game Highlight', function () {
    it('toggles is_highlight for a game in a monthly list', function () {
        $list = GameList::factory()->create([
            'name' => 'January 2026',
            'list_type' => ListTypeEnum::MONTHLY,
            'is_system' => true,
            'start_at' => '2026-01-01',
            'end_at' => '2026-01-31',
        ]);

        $genre = Genre::factory()->create(['name' => 'Action']);
        $game = Game::factory()->create(['name' => 'Test Game']);
        $list->games()->attach($game->id, [
            'order' => 1,
            'is_highlight' => false,
        ]);

        $response = $this->actingAs($this->admin)
            ->patchJson(route('admin.system-lists.games.toggle-highlight', [
                'type' => 'monthly',
                'slug' => $list->slug,
                'game' => $game->id,
            ]), [
                'primary_genre_id' => $genre->id,
            ]);

        $response->assertJson([
            'success' => true,
            'is_highlight' => true,
        ]);

        $pivotRecord = $list->games()->where('game_id', $game->id)->first();
        expect($pivotRecord->pivot->is_highlight)->toBe(1);
        expect($pivotRecord->pivot->primary_genre_id)->toBe($genre->id);
    });

    it('toggles is_highlight off when already highlighted', function () {
        $list = GameList::factory()->create([
            'name' => 'January 2026',
            'list_type' => ListTypeEnum::MONTHLY,
            'is_system' => true,
            'start_at' => '2026-01-01',
            'end_at' => '2026-01-31',
        ]);

        $game = Game::factory()->create(['name' => 'Test Game']);
        $list->games()->attach($game->id, [
            'order' => 1,
            'is_highlight' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->patchJson(route('admin.system-lists.games.toggle-highlight', [
                'type' => 'monthly',
                'slug' => $list->slug,
                'game' => $game->id,
            ]));

        $response->assertJson([
            'success' => true,
            'is_highlight' => false,
        ]);

        $pivotRecord = $list->games()->where('game_id', $game->id)->first();
        expect($pivotRecord->pivot->is_highlight)->toBe(0);
    });

    it('rejects highlight toggle for non-monthly/indie lists', function () {
        $list = GameList::factory()->create([
            'name' => 'Test Highlights',
            'list_type' => ListTypeEnum::HIGHLIGHTS,
            'is_system' => true,
        ]);

        $game = Game::factory()->create(['name' => 'Test Game']);
        $list->games()->attach($game->id, ['order' => 1]);

        $response = $this->actingAs($this->admin)
            ->patchJson(route('admin.system-lists.games.toggle-highlight', [
                'type' => 'highlights',
                'slug' => $list->slug,
                'game' => $game->id,
            ]));

        $response->assertStatus(400);
        $response->assertJson(['error' => 'Highlight toggle is only available for monthly and indie lists.']);
    });

    it('adds game to yearly highlights list when toggled on', function () {
        $highlightsList = GameList::factory()->create([
            'name' => 'Highlights 2026',
            'list_type' => ListTypeEnum::HIGHLIGHTS,
            'is_system' => true,
            'start_at' => '2026-01-01',
            'end_at' => '2026-12-31',
        ]);

        $monthlyList = GameList::factory()->create([
            'name' => 'January 2026',
            'list_type' => ListTypeEnum::MONTHLY,
            'is_system' => true,
            'start_at' => '2026-01-01',
            'end_at' => '2026-01-31',
        ]);

        $genre = Genre::factory()->create(['name' => 'Action']);
        $game = Game::factory()->create(['name' => 'Test Game']);
        $monthlyList->games()->attach($game->id, [
            'order' => 1,
            'is_highlight' => false,
            'platforms' => json_encode([6, 167]),
        ]);

        $this->actingAs($this->admin)
            ->patchJson(route('admin.system-lists.games.toggle-highlight', [
                'type' => 'monthly',
                'slug' => $monthlyList->slug,
                'game' => $game->id,
            ]), [
                'primary_genre_id' => $genre->id,
            ]);

        // Game should now be in the highlights list
        $this->assertDatabaseHas('game_list_game', [
            'game_list_id' => $highlightsList->id,
            'game_id' => $game->id,
        ]);
    });

    it('removes game from yearly highlights list when toggled off', function () {
        $highlightsList = GameList::factory()->create([
            'name' => 'Highlights 2026',
            'list_type' => ListTypeEnum::HIGHLIGHTS,
            'is_system' => true,
            'start_at' => '2026-01-01',
            'end_at' => '2026-12-31',
        ]);

        $monthlyList = GameList::factory()->create([
            'name' => 'January 2026',
            'list_type' => ListTypeEnum::MONTHLY,
            'is_system' => true,
            'start_at' => '2026-01-01',
            'end_at' => '2026-01-31',
        ]);

        $game = Game::factory()->create(['name' => 'Test Game']);
        $monthlyList->games()->attach($game->id, [
            'order' => 1,
            'is_highlight' => true,
        ]);
        $highlightsList->games()->attach($game->id, [
            'order' => 1,
            'platform_group' => PlatformGroupEnum::MULTIPLATFORM->value,
        ]);

        $this->actingAs($this->admin)
            ->patchJson(route('admin.system-lists.games.toggle-highlight', [
                'type' => 'monthly',
                'slug' => $monthlyList->slug,
                'game' => $game->id,
            ]));

        // Game should be removed from the highlights list
        $this->assertDatabaseMissing('game_list_game', [
            'game_list_id' => $highlightsList->id,
            'game_id' => $game->id,
        ]);
    });

    it('does not add duplicate game to yearly highlights list', function () {
        $highlightsList = GameList::factory()->create([
            'name' => 'Highlights 2026',
            'list_type' => ListTypeEnum::HIGHLIGHTS,
            'is_system' => true,
            'start_at' => '2026-01-01',
            'end_at' => '2026-12-31',
        ]);

        $monthlyList = GameList::factory()->create([
            'name' => 'January 2026',
            'list_type' => ListTypeEnum::MONTHLY,
            'is_system' => true,
            'start_at' => '2026-01-01',
            'end_at' => '2026-01-31',
        ]);

        $game = Game::factory()->create(['name' => 'Test Game']);
        $monthlyList->games()->attach($game->id, [
            'order' => 1,
            'is_highlight' => false,
        ]);
        // Pre-add game to highlights
        $highlightsList->games()->attach($game->id, [
            'order' => 1,
            'platform_group' => PlatformGroupEnum::PLAYSTATION->value,
        ]);

        $this->actingAs($this->admin)
            ->patchJson(route('admin.system-lists.games.toggle-highlight', [
                'type' => 'monthly',
                'slug' => $monthlyList->slug,
                'game' => $game->id,
            ]));

        // Should still only have one entry
        expect($highlightsList->games()->where('game_id', $game->id)->count())->toBe(1);
        // And it should keep the original platform group (PLAYSTATION, not auto-suggested)
        expect($highlightsList->games()->where('game_id', $game->id)->first()->pivot->platform_group)
            ->toBe(PlatformGroupEnum::PLAYSTATION->value);
    });
});

describe('CreateYearlyHighlightsList Command', function () {
    it('creates a yearly highlights list for the given year', function () {
        $this->artisan('highlights:create-yearly', ['--year' => 2026])
            ->assertSuccessful()
            ->expectsOutputToContain('Created: Highlights 2026');

        $this->assertDatabaseHas('game_lists', [
            'name' => 'Highlights 2026',
            'list_type' => ListTypeEnum::HIGHLIGHTS->value,
            'is_system' => true,
            'is_active' => true,
        ]);
    });

    it('fails if a highlights list already exists for the year', function () {
        GameList::factory()->create([
            'name' => 'Highlights 2026',
            'list_type' => ListTypeEnum::HIGHLIGHTS,
            'is_system' => true,
            'start_at' => '2026-01-01',
            'end_at' => '2026-12-31',
        ]);

        $this->artisan('highlights:create-yearly', ['--year' => 2026])
            ->assertFailed();
    });

    it('rejects invalid years', function () {
        $this->artisan('highlights:create-yearly', ['--year' => 1900])
            ->assertFailed()
            ->expectsOutput('Invalid year. Please enter a year between 2000 and 2100.');
    });
});

describe('SyncHighlightsGames Command', function () {
    it('syncs highlighted games from monthly lists to yearly highlights', function () {
        $highlightsList = GameList::factory()->create([
            'name' => 'Highlights 2026',
            'list_type' => ListTypeEnum::HIGHLIGHTS,
            'is_system' => true,
            'start_at' => '2026-01-01',
            'end_at' => '2026-12-31',
        ]);

        $monthlyList = GameList::factory()->create([
            'name' => 'January 2026',
            'list_type' => ListTypeEnum::MONTHLY,
            'is_system' => true,
            'start_at' => '2026-01-01',
            'end_at' => '2026-01-31',
        ]);

        $game = Game::factory()->create(['name' => 'Highlighted Game']);
        $monthlyList->games()->attach($game->id, [
            'order' => 1,
            'is_highlight' => true,
            'platforms' => json_encode([6, 167]),
        ]);

        $this->artisan('highlights:sync', ['--year' => 2026])
            ->assertSuccessful();

        $this->assertDatabaseHas('game_list_game', [
            'game_list_id' => $highlightsList->id,
            'game_id' => $game->id,
        ]);
    });

    it('skips games that are not marked as highlights', function () {
        $highlightsList = GameList::factory()->create([
            'name' => 'Highlights 2026',
            'list_type' => ListTypeEnum::HIGHLIGHTS,
            'is_system' => true,
            'start_at' => '2026-01-01',
            'end_at' => '2026-12-31',
        ]);

        $monthlyList = GameList::factory()->create([
            'name' => 'January 2026',
            'list_type' => ListTypeEnum::MONTHLY,
            'is_system' => true,
            'start_at' => '2026-01-01',
            'end_at' => '2026-01-31',
        ]);

        $game = Game::factory()->create(['name' => 'Non-Highlighted Game']);
        $monthlyList->games()->attach($game->id, [
            'order' => 1,
            'is_highlight' => false,
        ]);

        $this->artisan('highlights:sync', ['--year' => 2026])
            ->assertSuccessful();

        $this->assertDatabaseMissing('game_list_game', [
            'game_list_id' => $highlightsList->id,
            'game_id' => $game->id,
        ]);
    });

    it('skips games already in the highlights list', function () {
        $highlightsList = GameList::factory()->create([
            'name' => 'Highlights 2026',
            'list_type' => ListTypeEnum::HIGHLIGHTS,
            'is_system' => true,
            'start_at' => '2026-01-01',
            'end_at' => '2026-12-31',
        ]);

        $monthlyList = GameList::factory()->create([
            'name' => 'January 2026',
            'list_type' => ListTypeEnum::MONTHLY,
            'is_system' => true,
            'start_at' => '2026-01-01',
            'end_at' => '2026-01-31',
        ]);

        $game = Game::factory()->create(['name' => 'Already Added Game']);

        $highlightsList->games()->attach($game->id, [
            'order' => 1,
            'platform_group' => PlatformGroupEnum::MULTIPLATFORM->value,
        ]);

        $monthlyList->games()->attach($game->id, [
            'order' => 1,
            'is_highlight' => true,
            'platforms' => json_encode([6, 167]),
        ]);

        $this->artisan('highlights:sync', ['--year' => 2026])
            ->assertSuccessful()
            ->expectsOutputToContain('Skipped: 1 game(s)');
    });

    it('fails if no highlights list exists for the year', function () {
        $this->artisan('highlights:sync', ['--year' => 2026])
            ->assertFailed()
            ->expectsOutput('No highlights list found for 2026. Create one first with: php artisan highlights:create-yearly --year=2026');
    });

    it('supports dry-run mode', function () {
        $highlightsList = GameList::factory()->create([
            'name' => 'Highlights 2026',
            'list_type' => ListTypeEnum::HIGHLIGHTS,
            'is_system' => true,
            'start_at' => '2026-01-01',
            'end_at' => '2026-12-31',
        ]);

        $monthlyList = GameList::factory()->create([
            'name' => 'January 2026',
            'list_type' => ListTypeEnum::MONTHLY,
            'is_system' => true,
            'start_at' => '2026-01-01',
            'end_at' => '2026-01-31',
        ]);

        $game = Game::factory()->create(['name' => 'Dry Run Game']);
        $monthlyList->games()->attach($game->id, [
            'order' => 1,
            'is_highlight' => true,
            'platforms' => json_encode([6]),
        ]);

        $this->artisan('highlights:sync', ['--year' => 2026, '--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Would add: 1 game(s)');

        $this->assertDatabaseMissing('game_list_game', [
            'game_list_id' => $highlightsList->id,
            'game_id' => $game->id,
        ]);
    });
});
