<?php

use App\Models\Game;
use App\Models\User;
use App\Models\UserGame;
use App\Models\UserGameCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ============================================================================
// GET /u/{username}/games (index page)
// ============================================================================

it('shows my games page for authenticated owner', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get("/u/{$user->username}/games");

    $response->assertSuccessful();
    $response->assertViewIs('user-games.index');
});

it('shows my games page publicly', function () {
    $user = User::factory()->create();

    $response = $this->get("/u/{$user->username}/games");

    $response->assertSuccessful();
});

it('shows games filtered by status', function () {
    $user = User::factory()->create();

    $playingGame = Game::factory()->create(['name' => 'Playing Game']);
    $backlogGame = Game::factory()->create(['name' => 'Backlog Game']);

    UserGame::factory()->playing()->create([
        'user_id' => $user->id,
        'game_id' => $playingGame->id,
    ]);
    UserGame::factory()->backlog()->create([
        'user_id' => $user->id,
        'game_id' => $backlogGame->id,
    ]);

    $response = $this->actingAs($user)->get("/u/{$user->username}/games?status=playing");

    $response->assertSuccessful();
    $response->assertSee('Playing Game');
    $response->assertDontSee('Backlog Game');
});

it('shows wishlisted games', function () {
    $user = User::factory()->create();

    $wishlistedGame = Game::factory()->create(['name' => 'Wishlisted Game']);
    $normalGame = Game::factory()->create(['name' => 'Normal Game']);

    UserGame::factory()->wishlisted()->create([
        'user_id' => $user->id,
        'game_id' => $wishlistedGame->id,
    ]);
    UserGame::factory()->playing()->create([
        'user_id' => $user->id,
        'game_id' => $normalGame->id,
    ]);

    $response = $this->actingAs($user)->get("/u/{$user->username}/games?wishlist=1");

    $response->assertSuccessful();
    $response->assertSee('Wishlisted Game');
    $response->assertDontSee('Normal Game');
});

it('shows stats in view data', function () {
    $user = User::factory()->create();

    UserGame::factory()->playing()->count(2)->create(['user_id' => $user->id]);
    UserGame::factory()->played()->count(3)->create(['user_id' => $user->id]);
    UserGame::factory()->backlog()->count(1)->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->get("/u/{$user->username}/games");

    $response->assertSuccessful();
    $response->assertViewHas('stats');
});

it('returns 404 for non-existent user', function () {
    $response = $this->get('/u/nonexistentuser/games');

    $response->assertNotFound();
});

// ============================================================================
// Settings
// ============================================================================

it('shows settings page for owner', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get("/u/{$user->username}/games/settings");

    $response->assertSuccessful();
    $response->assertViewIs('user-games.settings');
});

it('prevents non-owner from accessing settings', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $response = $this->actingAs($other)->get("/u/{$owner->username}/games/settings");

    $response->assertForbidden();
});

it('updates collection settings', function () {
    $user = User::factory()->create();
    UserGameCollection::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->patch("/u/{$user->username}/games/settings", [
        'name' => 'My Epic Games',
        'description' => 'My collection of games',
        'privacy_playing' => '1',
        'privacy_played' => '1',
    ]);

    $response->assertRedirect();

    $user->refresh();
    expect($user->gameCollection->name)->toBe('My Epic Games');
    expect($user->gameCollection->description)->toBe('My collection of games');
    expect($user->gameCollection->privacy_playing)->toBeTrue();
    expect($user->gameCollection->privacy_played)->toBeTrue();
    expect($user->gameCollection->privacy_backlog)->toBeFalse();
    expect($user->gameCollection->privacy_wishlist)->toBeFalse();
});

// ============================================================================
// Model Tests
// ============================================================================

it('formats time played correctly', function () {
    $userGame = UserGame::factory()->withTimePlayed(2.5)->create();

    expect($userGame->getFormattedTimePlayed())->toBe('2h 30m');
});

it('formats zero time played as 0m', function () {
    $userGame = UserGame::factory()->create(['time_played' => 0]);

    expect($userGame->getFormattedTimePlayed())->toBe('0m');
});

it('returns null for null time played', function () {
    $userGame = UserGame::factory()->create(['time_played' => null]);

    expect($userGame->getFormattedTimePlayed())->toBeNull();
});

it('scopes by status correctly', function () {
    $user = User::factory()->create();

    UserGame::factory()->playing()->count(2)->create(['user_id' => $user->id]);
    UserGame::factory()->played()->count(3)->create(['user_id' => $user->id]);
    UserGame::factory()->backlog()->count(1)->create(['user_id' => $user->id]);

    expect(UserGame::playing()->where('user_id', $user->id)->count())->toBe(2);
    expect(UserGame::played()->where('user_id', $user->id)->count())->toBe(3);
    expect(UserGame::backlog()->where('user_id', $user->id)->count())->toBe(1);
});

it('scopes wishlisted correctly', function () {
    $user = User::factory()->create();

    UserGame::factory()->wishlisted()->count(2)->create(['user_id' => $user->id]);
    UserGame::factory()->create(['user_id' => $user->id, 'is_wishlisted' => false]);

    expect(UserGame::wishlisted()->where('user_id', $user->id)->count())->toBe(2);
});

it('lazy creates game collection via user helper', function () {
    $user = User::factory()->create();

    expect($user->gameCollection)->toBeNull();

    $collection = $user->getOrCreateGameCollection();

    expect($collection)->not->toBeNull();
    expect($collection->user_id)->toBe($user->id);
    expect($collection->name)->toBe($user->username."'s Games");
});

// ============================================================================
// Avatar & Initials
// ============================================================================

it('returns avatar url when avatar_path is set', function () {
    $user = User::factory()->create(['avatar_path' => 'user-avatars/1/test.webp']);

    expect($user->avatar_url)->toContain('storage/user-avatars/1/test.webp');
});

it('returns null avatar url when no avatar', function () {
    $user = User::factory()->create(['avatar_path' => null]);

    expect($user->avatar_url)->toBeNull();
});

it('returns correct initials from username', function () {
    $user = User::factory()->create(['username' => 'johndoe']);

    expect($user->getInitials())->toBe('JO');
});

// ============================================================================
// Default View Mode
// ============================================================================

it('defaults to list view mode', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get("/u/{$user->username}/games");

    $response->assertSuccessful();
    $response->assertViewHas('viewMode', 'list');
});

it('defaults to all status filter', function () {
    $user = User::factory()->create();

    $playingGame = Game::factory()->create(['name' => 'Default Playing']);
    $backlogGame = Game::factory()->create(['name' => 'Default Backlog']);

    UserGame::factory()->playing()->create(['user_id' => $user->id, 'game_id' => $playingGame->id]);
    UserGame::factory()->backlog()->create(['user_id' => $user->id, 'game_id' => $backlogGame->id]);

    $response = $this->actingAs($user)->get("/u/{$user->username}/games");

    $response->assertSuccessful();
    $response->assertViewHas('statusFilter', 'all');
    $response->assertSee('Default Playing');
    $response->assertSee('Default Backlog');
});

// ============================================================================
// "All" Filter
// ============================================================================

it('shows all games when status=all filter is used', function () {
    $user = User::factory()->create();

    $playingGame = Game::factory()->create(['name' => 'Playing Game All']);
    $backlogGame = Game::factory()->create(['name' => 'Backlog Game All']);

    UserGame::factory()->playing()->create([
        'user_id' => $user->id,
        'game_id' => $playingGame->id,
    ]);
    UserGame::factory()->backlog()->create([
        'user_id' => $user->id,
        'game_id' => $backlogGame->id,
    ]);

    $response = $this->actingAs($user)->get("/u/{$user->username}/games?status=all");

    $response->assertSuccessful();
    $response->assertSee('Playing Game All');
    $response->assertSee('Backlog Game All');
});

// ============================================================================
// Avatar Upload
// ============================================================================

it('uploads avatar on settings update', function () {
    $user = User::factory()->create();
    UserGameCollection::factory()->create(['user_id' => $user->id]);

    $file = \Illuminate\Http\UploadedFile::fake()->image('avatar.jpg', 400, 400);

    $response = $this->actingAs($user)->patch("/u/{$user->username}/games/settings", [
        'name' => 'My Games',
        'avatar' => $file,
    ]);

    $response->assertRedirect();

    $user->refresh();
    expect($user->avatar_path)->not->toBeNull();
    expect($user->avatar_path)->toStartWith('user-avatars/');
});
