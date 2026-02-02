<?php

use App\Enums\ListTypeEnum;
use App\Models\Game;
use App\Models\GameList;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
});

describe('Edit Pivot Data', function () {
    it('can update release_date via pivot edit endpoint', function () {
        $list = GameList::factory()->create([
            'name' => 'January 2026',
            'list_type' => ListTypeEnum::YEARLY,
            'is_system' => true,
        ]);

        $game = Game::factory()->create(['name' => 'Test Game']);
        $list->games()->attach($game->id, [
            'order' => 1,
            'release_date' => '2026-01-15',
        ]);

        $response = $this->actingAs($this->admin)
            ->patchJson(route('admin.system-lists.games.update-pivot', [
                'type' => 'yearly',
                'slug' => $list->slug,
                'game' => $game->id,
            ]), [
                'release_date' => '2026-02-20',
            ]);

        $response->assertJson(['success' => true]);

        $pivot = $list->games()->where('game_id', $game->id)->first()->pivot;
        expect($pivot->release_date)->toContain('2026-02-20');
    });

    it('can update platforms via pivot edit endpoint', function () {
        $list = GameList::factory()->create([
            'name' => 'January 2026',
            'list_type' => ListTypeEnum::YEARLY,
            'is_system' => true,
        ]);

        $game = Game::factory()->create(['name' => 'Test Game']);
        $list->games()->attach($game->id, [
            'order' => 1,
            'platforms' => json_encode([6]),
        ]);

        $response = $this->actingAs($this->admin)
            ->patchJson(route('admin.system-lists.games.update-pivot', [
                'type' => 'yearly',
                'slug' => $list->slug,
                'game' => $game->id,
            ]), [
                'platforms' => [6, 167, 169],
            ]);

        $response->assertJson(['success' => true]);

        $pivot = $list->games()->where('game_id', $game->id)->first()->pivot;
        $platforms = json_decode($pivot->platforms, true);
        expect($platforms)->toBe([6, 167, 169]);
    });

    it('can update is_tba via pivot edit endpoint', function () {
        $list = GameList::factory()->create([
            'name' => 'January 2026',
            'list_type' => ListTypeEnum::YEARLY,
            'is_system' => true,
        ]);

        $game = Game::factory()->create(['name' => 'Test Game']);
        $list->games()->attach($game->id, [
            'order' => 1,
            'release_date' => '2026-01-15',
            'is_tba' => false,
        ]);

        $response = $this->actingAs($this->admin)
            ->patchJson(route('admin.system-lists.games.update-pivot', [
                'type' => 'yearly',
                'slug' => $list->slug,
                'game' => $game->id,
            ]), [
                'is_tba' => true,
            ]);

        $response->assertJson(['success' => true]);

        $pivot = $list->games()->where('game_id', $game->id)->first()->pivot;
        expect((bool) $pivot->is_tba)->toBeTrue();
        expect($pivot->release_date)->toBeNull();
    });

    it('can update genre_ids and primary_genre_id via pivot edit endpoint', function () {
        $list = GameList::factory()->create([
            'name' => 'January 2026',
            'list_type' => ListTypeEnum::YEARLY,
            'is_system' => true,
        ]);

        $genre1 = Genre::factory()->create(['name' => 'Action']);
        $genre2 = Genre::factory()->create(['name' => 'RPG']);

        $game = Game::factory()->create(['name' => 'Test Game']);
        $list->games()->attach($game->id, [
            'order' => 1,
        ]);

        $response = $this->actingAs($this->admin)
            ->patchJson(route('admin.system-lists.games.update-pivot', [
                'type' => 'yearly',
                'slug' => $list->slug,
                'game' => $game->id,
            ]), [
                'genre_ids' => [$genre1->id, $genre2->id],
                'primary_genre_id' => $genre1->id,
            ]);

        $response->assertJson(['success' => true]);

        $pivot = $list->games()->where('game_id', $game->id)->first()->pivot;
        $genreIds = json_decode($pivot->genre_ids, true);
        expect($genreIds)->toBe([$genre1->id, $genre2->id]);
        expect($pivot->primary_genre_id)->toBe($genre1->id);
    });

    it('does not change is_highlight or is_indie when editing pivot data', function () {
        $list = GameList::factory()->create([
            'name' => 'January 2026',
            'list_type' => ListTypeEnum::YEARLY,
            'is_system' => true,
        ]);

        $game = Game::factory()->create(['name' => 'Test Game']);
        $list->games()->attach($game->id, [
            'order' => 1,
            'is_highlight' => true,
            'is_indie' => true,
            'release_date' => '2026-01-15',
        ]);

        $response = $this->actingAs($this->admin)
            ->patchJson(route('admin.system-lists.games.update-pivot', [
                'type' => 'yearly',
                'slug' => $list->slug,
                'game' => $game->id,
            ]), [
                'release_date' => '2026-03-01',
            ]);

        $response->assertJson(['success' => true]);

        $pivot = $list->games()->where('game_id', $game->id)->first()->pivot;
        expect((int) $pivot->is_highlight)->toBe(1);
        expect((int) $pivot->is_indie)->toBe(1);
        expect($pivot->release_date)->toContain('2026-03-01');
    });

    it('returns 404 for game not in list', function () {
        $list = GameList::factory()->create([
            'name' => 'January 2026',
            'list_type' => ListTypeEnum::YEARLY,
            'is_system' => true,
        ]);

        $game = Game::factory()->create(['name' => 'Not In List']);

        $response = $this->actingAs($this->admin)
            ->patchJson(route('admin.system-lists.games.update-pivot', [
                'type' => 'yearly',
                'slug' => $list->slug,
                'game' => $game->id,
            ]), [
                'release_date' => '2026-03-01',
            ]);

        $response->assertNotFound();
        $response->assertJson(['error' => 'Game not found in this list.']);
    });

    it('requires authentication and admin middleware', function () {
        $list = GameList::factory()->create([
            'name' => 'January 2026',
            'list_type' => ListTypeEnum::YEARLY,
            'is_system' => true,
        ]);

        $game = Game::factory()->create(['name' => 'Test Game']);
        $list->games()->attach($game->id, ['order' => 1]);

        // Unauthenticated
        $this->patchJson(route('admin.system-lists.games.update-pivot', [
            'type' => 'yearly',
            'slug' => $list->slug,
            'game' => $game->id,
        ]), ['release_date' => '2026-03-01'])
            ->assertUnauthorized();

        // Non-admin user
        $regularUser = User::factory()->create(['is_admin' => false]);
        $this->actingAs($regularUser)
            ->patchJson(route('admin.system-lists.games.update-pivot', [
                'type' => 'yearly',
                'slug' => $list->slug,
                'game' => $game->id,
            ]), ['release_date' => '2026-03-01'])
            ->assertForbidden();
    });

    it('works for all system list types', function (string $typeSlug, string $listTypeValue) {
        $list = GameList::factory()->create([
            'name' => 'Test List',
            'list_type' => $listTypeValue,
            'is_system' => true,
            'is_active' => true,
        ]);

        $game = Game::factory()->create(['name' => 'Test Game']);
        $list->games()->attach($game->id, [
            'order' => 1,
            'release_date' => '2026-01-01',
        ]);

        $response = $this->actingAs($this->admin)
            ->patchJson(route('admin.system-lists.games.update-pivot', [
                'type' => $typeSlug,
                'slug' => $list->slug,
                'game' => $game->id,
            ]), [
                'release_date' => '2026-06-15',
            ]);

        $response->assertJson(['success' => true]);

        $pivot = $list->games()->where('game_id', $game->id)->first()->pivot;
        expect($pivot->release_date)->toContain('2026-06-15');
    })->with([
        'yearly' => ['yearly', ListTypeEnum::YEARLY->value],
        'seasoned' => ['seasoned', ListTypeEnum::SEASONED->value],
        'events' => ['events', ListTypeEnum::EVENTS->value],
    ]);

    it('validates genre_ids max of 3', function () {
        $list = GameList::factory()->create([
            'name' => 'January 2026',
            'list_type' => ListTypeEnum::YEARLY,
            'is_system' => true,
        ]);

        $genres = Genre::factory()->count(4)->create();
        $game = Game::factory()->create(['name' => 'Test Game']);
        $list->games()->attach($game->id, ['order' => 1]);

        $response = $this->actingAs($this->admin)
            ->patchJson(route('admin.system-lists.games.update-pivot', [
                'type' => 'yearly',
                'slug' => $list->slug,
                'game' => $game->id,
            ]), [
                'genre_ids' => $genres->pluck('id')->toArray(),
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('genre_ids');
    });
});
