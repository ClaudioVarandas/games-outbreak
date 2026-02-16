<?php

use App\Enums\UserGameStatusEnum;
use App\Models\Game;
use App\Models\User;
use App\Models\UserGame;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
});

// ============================================================================
// POST /api/user-games (store)
// ============================================================================

it('creates a user game with status', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/user-games', [
        'game_id' => $game->id,
        'status' => 'playing',
    ]);

    $response->assertStatus(201);
    $response->assertJsonPath('user_game.status', 'playing');
    $response->assertJsonPath('user_game.is_wishlisted', false);

    $this->assertDatabaseHas('user_games', [
        'user_id' => $user->id,
        'game_id' => $game->id,
        'status' => 'playing',
    ]);
});

it('creates a user game with wishlist flag', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/user-games', [
        'game_id' => $game->id,
        'is_wishlisted' => true,
    ]);

    $response->assertStatus(201);
    $response->assertJsonPath('user_game.is_wishlisted', true);
    $response->assertJsonPath('user_game.status', null);
});

it('creates a user game collection lazily on first add', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create();

    expect($user->gameCollection)->toBeNull();

    $this->actingAs($user)->postJson('/api/user-games', [
        'game_id' => $game->id,
        'status' => 'backlog',
    ]);

    $user->refresh();
    expect($user->gameCollection)->not->toBeNull();
    expect($user->gameCollection->name)->toBe($user->username."'s Games");
});

it('returns existing entry for duplicate user game', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create();

    UserGame::factory()->create([
        'user_id' => $user->id,
        'game_id' => $game->id,
        'status' => UserGameStatusEnum::Playing,
    ]);

    $response = $this->actingAs($user)->postJson('/api/user-games', [
        'game_id' => $game->id,
        'status' => 'played',
    ]);

    $response->assertSuccessful();
    $response->assertJsonPath('message', 'Game already in collection.');
    $response->assertJsonPath('user_game.status', 'playing');
});

it('requires authentication to store', function () {
    $game = Game::factory()->create();

    $response = $this->postJson('/api/user-games', [
        'game_id' => $game->id,
        'status' => 'playing',
    ]);

    $response->assertUnauthorized();
});

// ============================================================================
// PATCH /api/user-games/{userGame} (update)
// ============================================================================

it('updates user game status', function () {
    $user = User::factory()->create();
    $userGame = UserGame::factory()->playing()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->patchJson("/api/user-games/{$userGame->id}", [
        'status' => 'played',
    ]);

    $response->assertSuccessful();
    $response->assertJsonPath('user_game.status', 'played');

    $userGame->refresh();
    expect($userGame->status)->toBe(UserGameStatusEnum::Played);
});

it('clears status when setting null', function () {
    $user = User::factory()->create();
    $userGame = UserGame::factory()->playing()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->patchJson("/api/user-games/{$userGame->id}", [
        'status' => null,
    ]);

    $response->assertSuccessful();
    $response->assertJsonPath('user_game.status', null);
});

it('toggles wishlist flag', function () {
    $user = User::factory()->create();
    $userGame = UserGame::factory()->create([
        'user_id' => $user->id,
        'is_wishlisted' => false,
    ]);

    $response = $this->actingAs($user)->patchJson("/api/user-games/{$userGame->id}", [
        'is_wishlisted' => true,
    ]);

    $response->assertSuccessful();
    $response->assertJsonPath('user_game.is_wishlisted', true);

    $userGame->refresh();
    expect($userGame->is_wishlisted)->toBeTrue();
    expect($userGame->wishlisted_at)->not->toBeNull();
});

it('updates time played', function () {
    $user = User::factory()->create();
    $userGame = UserGame::factory()->playing()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->patchJson("/api/user-games/{$userGame->id}", [
        'time_played' => 25.5,
    ]);

    $response->assertSuccessful();

    $userGame->refresh();
    expect((float) $userGame->time_played)->toBe(25.5);
});

it('updates rating', function () {
    $user = User::factory()->create();
    $userGame = UserGame::factory()->playing()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->patchJson("/api/user-games/{$userGame->id}", [
        'rating' => 85,
    ]);

    $response->assertSuccessful();

    $userGame->refresh();
    expect($userGame->rating)->toBe(85);
});

it('prevents updating another users game', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $userGame = UserGame::factory()->create(['user_id' => $otherUser->id]);

    $response = $this->actingAs($user)->patchJson("/api/user-games/{$userGame->id}", [
        'status' => 'played',
    ]);

    $response->assertForbidden();
});

// ============================================================================
// DELETE /api/user-games/{userGame} (destroy)
// ============================================================================

it('deletes a user game', function () {
    $user = User::factory()->create();
    $userGame = UserGame::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->deleteJson("/api/user-games/{$userGame->id}");

    $response->assertSuccessful();
    $this->assertDatabaseMissing('user_games', ['id' => $userGame->id]);
});

it('prevents deleting another users game', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $userGame = UserGame::factory()->create(['user_id' => $otherUser->id]);

    $response = $this->actingAs($user)->deleteJson("/api/user-games/{$userGame->id}");

    $response->assertForbidden();
    $this->assertDatabaseHas('user_games', ['id' => $userGame->id]);
});

// ============================================================================
// GET /api/user-games/status/{game} (status check)
// ============================================================================

it('returns user game status for a game in collection', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create();
    $userGame = UserGame::factory()->playing()->wishlisted()->create([
        'user_id' => $user->id,
        'game_id' => $game->id,
    ]);

    $response = $this->actingAs($user)->getJson("/api/user-games/status/{$game->id}");

    $response->assertSuccessful();
    $response->assertJsonPath('user_game.id', $userGame->id);
    $response->assertJsonPath('user_game.status', 'playing');
    $response->assertJsonPath('user_game.is_wishlisted', true);
});

it('returns null for game not in collection', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create();

    $response = $this->actingAs($user)->getJson("/api/user-games/status/{$game->id}");

    $response->assertSuccessful();
    $response->assertJsonPath('user_game', null);
});

it('requires authentication for status check', function () {
    $game = Game::factory()->create();

    $response = $this->getJson("/api/user-games/status/{$game->id}");

    $response->assertUnauthorized();
});

// ============================================================================
// PATCH /api/user-games/{userGame} — save all fields at once
// ============================================================================

it('saves all fields in a single patch request', function () {
    $user = User::factory()->create();
    $userGame = UserGame::factory()->playing()->create([
        'user_id' => $user->id,
        'is_wishlisted' => false,
        'time_played' => null,
        'rating' => null,
    ]);

    $response = $this->actingAs($user)->patchJson("/api/user-games/{$userGame->id}", [
        'status' => 'played',
        'is_wishlisted' => true,
        'time_played' => 42.5,
        'rating' => 85,
    ]);

    $response->assertSuccessful();
    $response->assertJsonPath('user_game.status', 'played');
    $response->assertJsonPath('user_game.is_wishlisted', true);
    $response->assertJsonPath('user_game.rating', 85);

    $userGame->refresh();
    expect($userGame->status)->toBe(UserGameStatusEnum::Played);
    expect($userGame->is_wishlisted)->toBeTrue();
    expect((float) $userGame->time_played)->toBe(42.5);
    expect($userGame->rating)->toBe(85);
});
