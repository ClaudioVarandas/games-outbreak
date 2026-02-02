<?php

use App\Enums\ListTypeEnum;
use App\Models\Game;
use App\Models\GameList;
use App\Models\Genre;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create platforms
    $this->pcPlatform = Platform::factory()->create(['name' => 'PC (Windows)', 'igdb_id' => 6]);
    $this->ps5Platform = Platform::factory()->create(['name' => 'PlayStation 5', 'igdb_id' => 167]);

    // Create genres with unique igdb_ids
    $this->actionGenre = Genre::factory()->create(['name' => 'Action', 'igdb_id' => 25]);
    $this->rpgGenre = Genre::factory()->create(['name' => 'RPG', 'igdb_id' => 12]);

    // Create system list
    $this->systemList = GameList::factory()->create([
        'name' => 'Test Event List',
        'slug' => 'test-event-list',
        'list_type' => ListTypeEnum::EVENTS,
        'is_system' => true,
        'is_public' => true,
        'is_active' => true,
    ]);
});

test('system list page loads successfully', function () {
    $response = $this->get('/list/events/test-event-list');

    $response->assertStatus(200);
    $response->assertSee('Test Event List');
});

test('system list shows all games without filters', function () {
    $game1 = Game::factory()->create(['name' => 'Game One']);
    $game2 = Game::factory()->create(['name' => 'Game Two']);

    $this->systemList->games()->attach([$game1->id, $game2->id]);

    $response = $this->get('/list/events/test-event-list');

    $response->assertStatus(200);
    $response->assertSee('Game One');
    $response->assertSee('Game Two');
});

test('system list passes filter options to view', function () {
    $game = Game::factory()->create(['name' => 'Action Game']);
    $game->platforms()->attach($this->pcPlatform);
    $game->genres()->attach($this->actionGenre);

    $this->systemList->games()->attach($game->id);

    $response = $this->get('/list/events/test-event-list');

    $response->assertStatus(200);
    $response->assertViewHas('filterOptions');
    $response->assertViewHas('gamesData');
});

test('system list parses platform query parameter', function () {
    $game = Game::factory()->create(['name' => 'PC Game']);
    $game->platforms()->attach($this->pcPlatform);
    $this->systemList->games()->attach($game->id);

    $response = $this->get('/list/events/test-event-list?platform='.$this->pcPlatform->id);

    $response->assertStatus(200);
    $response->assertViewHas('initialFilters', function ($filters) {
        return in_array($this->pcPlatform->id, $filters['platforms']);
    });
});

test('system list parses genre query parameter', function () {
    $game = Game::factory()->create(['name' => 'Action Game']);
    $game->genres()->attach($this->actionGenre);
    $this->systemList->games()->attach($game->id);

    $response = $this->get('/list/events/test-event-list?genre='.$this->actionGenre->id);

    $response->assertStatus(200);
    $response->assertViewHas('initialFilters', function ($filters) {
        return in_array($this->actionGenre->id, $filters['genres']);
    });
});

test('system list parses multiple filter parameters', function () {
    $game = Game::factory()->create(['name' => 'Multi-filter Game']);
    $game->platforms()->attach($this->pcPlatform);
    $game->genres()->attach($this->actionGenre);
    $this->systemList->games()->attach($game->id);

    $response = $this->get('/list/events/test-event-list?platform='.$this->pcPlatform->id.'&genre='.$this->actionGenre->id);

    $response->assertStatus(200);
    $response->assertViewHas('initialFilters', function ($filters) {
        return in_array($this->pcPlatform->id, $filters['platforms']) &&
               in_array($this->actionGenre->id, $filters['genres']);
    });
});

test('system list includes JSON-LD schema in head', function () {
    $game = Game::factory()->create(['name' => 'Schema Game']);
    $this->systemList->games()->attach($game->id);

    $response = $this->get('/list/events/test-event-list');

    $response->assertStatus(200);
    $response->assertSee('application/ld+json', false);
    $response->assertSee('"@type": "ItemList"', false);
});

test('system list includes OG meta tags', function () {
    $response = $this->get('/list/events/test-event-list');

    $response->assertStatus(200);
    $response->assertSee('og:title', false);
    $response->assertSee('og:description', false);
});

test('private system list is not accessible to guests', function () {
    $privateList = GameList::factory()->create([
        'name' => 'Private List',
        'slug' => 'private-list',
        'list_type' => ListTypeEnum::EVENTS,
        'is_system' => true,
        'is_public' => false,
        'is_active' => true,
    ]);

    $response = $this->get('/list/events/private-list');

    $response->assertStatus(404);
});

test('inactive system list is not accessible to guests', function () {
    $inactiveList = GameList::factory()->create([
        'name' => 'Inactive List',
        'slug' => 'inactive-list',
        'list_type' => ListTypeEnum::EVENTS,
        'is_system' => true,
        'is_public' => true,
        'is_active' => false,
    ]);

    $response = $this->get('/list/events/inactive-list');

    $response->assertStatus(404);
});

test('admin can view inactive system list', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $inactiveList = GameList::factory()->create([
        'name' => 'Inactive List',
        'slug' => 'inactive-list',
        'list_type' => ListTypeEnum::EVENTS,
        'is_system' => true,
        'is_public' => true,
        'is_active' => false,
    ]);

    $response = $this->actingAs($admin)->get('/list/events/inactive-list');

    $response->assertStatus(200);
});

test('game list model returns correct filter options', function () {
    $game = Game::factory()->create(['name' => 'Filter Test Game']);
    $game->platforms()->attach($this->pcPlatform);
    $game->genres()->attach($this->actionGenre);
    $this->systemList->games()->attach($game->id);

    // Reload with relationships
    $this->systemList->load(['games.platforms', 'games.genres', 'games.gameModes', 'games.playerPerspectives']);

    $filterOptions = $this->systemList->getFilterOptions();

    expect($filterOptions)->toBeArray();
    expect($filterOptions['platforms'])->toBeArray();
    expect($filterOptions['genres'])->toBeArray();
    expect(collect($filterOptions['platforms'])->pluck('id'))->toContain($this->pcPlatform->id);
    expect(collect($filterOptions['genres'])->pluck('id'))->toContain($this->actionGenre->id);
});

test('game list model returns games data for filtering', function () {
    $game = Game::factory()->create([
        'name' => 'Data Test Game',
        'slug' => 'data-test-game',
        'first_release_date' => now()->addMonth(),
    ]);
    $game->platforms()->attach($this->pcPlatform);
    $game->genres()->attach($this->actionGenre);
    $this->systemList->games()->attach($game->id);

    $this->systemList->load(['games.platforms', 'games.genres', 'games.gameModes', 'games.playerPerspectives']);

    $gamesData = $this->systemList->getGamesForFiltering();

    expect($gamesData)->toBeArray();
    expect($gamesData)->toHaveCount(1);
    expect($gamesData[0]['name'])->toBe('Data Test Game');
    expect($gamesData[0]['platforms'])->toBeArray();
    expect($gamesData[0]['genres'])->toBeArray();
});

test('system list parses comma-separated platform filters', function () {
    $game = Game::factory()->create(['name' => 'Multi-Platform Game']);
    $game->platforms()->attach([$this->pcPlatform->id, $this->ps5Platform->id]);
    $this->systemList->games()->attach($game->id);

    $response = $this->get('/list/events/test-event-list?platform='.$this->pcPlatform->id.','.$this->ps5Platform->id);

    $response->assertStatus(200);
    $response->assertViewHas('initialFilters', function ($filters) {
        return count($filters['platforms']) === 2 &&
               in_array($this->pcPlatform->id, $filters['platforms']) &&
               in_array($this->ps5Platform->id, $filters['platforms']);
    });
});

test('system list parses comma-separated genre filters', function () {
    $game = Game::factory()->create(['name' => 'Multi-Genre Game']);
    $game->genres()->attach([$this->actionGenre->id, $this->rpgGenre->id]);
    $this->systemList->games()->attach($game->id);

    $response = $this->get('/list/events/test-event-list?genre='.$this->actionGenre->id.','.$this->rpgGenre->id);

    $response->assertStatus(200);
    $response->assertViewHas('initialFilters', function ($filters) {
        return count($filters['genres']) === 2 &&
               in_array($this->actionGenre->id, $filters['genres']) &&
               in_array($this->rpgGenre->id, $filters['genres']);
    });
});

test('system list parses game_type query parameter', function () {
    $game = Game::factory()->create(['name' => 'Main Game', 'game_type' => 0]);
    $this->systemList->games()->attach($game->id);

    $response = $this->get('/list/events/test-event-list?game_type=0');

    $response->assertStatus(200);
    $response->assertViewHas('initialFilters', function ($filters) {
        return in_array(0, $filters['gameTypes']);
    });
});

test('games data includes game type information', function () {
    $game = Game::factory()->create([
        'name' => 'Main Game Test',
        'game_type' => 0,
    ]);
    $this->systemList->games()->attach($game->id);
    $this->systemList->load(['games.platforms', 'games.genres', 'games.gameModes', 'games.playerPerspectives']);

    $gamesData = $this->systemList->getGamesForFiltering();

    expect($gamesData[0]['game_type'])->toBeArray();
    expect($gamesData[0]['game_type']['id'])->toBe(0);
    expect($gamesData[0]['game_type']['name'])->toBe('Main Game');
});

test('filter options include counts for each option', function () {
    $game1 = Game::factory()->create(['name' => 'PC Game 1']);
    $game2 = Game::factory()->create(['name' => 'PC Game 2']);
    $game3 = Game::factory()->create(['name' => 'PS5 Game']);

    $game1->platforms()->attach($this->pcPlatform);
    $game2->platforms()->attach($this->pcPlatform);
    $game3->platforms()->attach($this->ps5Platform);

    $this->systemList->games()->attach([$game1->id, $game2->id, $game3->id]);
    $this->systemList->load(['games.platforms', 'games.genres', 'games.gameModes', 'games.playerPerspectives']);

    $filterOptions = $this->systemList->getFilterOptions();

    $pcOption = collect($filterOptions['platforms'])->firstWhere('id', $this->pcPlatform->id);
    $ps5Option = collect($filterOptions['platforms'])->firstWhere('id', $this->ps5Platform->id);

    expect($pcOption['count'])->toBe(2);
    expect($ps5Option['count'])->toBe(1);
});

test('games data includes release date formatted', function () {
    $game = Game::factory()->create([
        'name' => 'Release Date Game',
        'first_release_date' => '2026-06-15',
    ]);
    $this->systemList->games()->attach($game->id);
    $this->systemList->load(['games.platforms', 'games.genres', 'games.gameModes', 'games.playerPerspectives']);

    $gamesData = $this->systemList->getGamesForFiltering();

    expect($gamesData[0]['release_date_formatted'])->toBe('Jun 15, 2026');
});

test('different list types are accessible via slug route', function () {
    $yearlyList = GameList::factory()->create([
        'name' => 'Yearly Test',
        'slug' => 'yearly-test',
        'list_type' => ListTypeEnum::YEARLY,
        'is_system' => true,
        'is_public' => true,
        'is_active' => true,
    ]);

    $seasonedList = GameList::factory()->create([
        'name' => 'Seasoned Test',
        'slug' => 'seasoned-test',
        'list_type' => ListTypeEnum::SEASONED,
        'is_system' => true,
        'is_public' => true,
        'is_active' => true,
    ]);

    $this->get('/list/yearly/yearly-test')->assertStatus(200);
    $this->get('/list/seasoned/seasoned-test')->assertStatus(200);
});

test('invalid list type returns 404', function () {
    $response = $this->get('/list/invalid-type/some-slug');

    $response->assertStatus(404);
});

test('empty system list displays correctly', function () {
    $response = $this->get('/list/events/test-event-list');

    $response->assertStatus(200);
    $response->assertViewHas('gamesData', function ($gamesData) {
        return count($gamesData) === 0;
    });
});

test('system list view includes Alpine.js filter component data', function () {
    $game = Game::factory()->create(['name' => 'Alpine Test Game']);
    $game->platforms()->attach($this->pcPlatform);
    $this->systemList->games()->attach($game->id);

    $response = $this->get('/list/events/test-event-list');

    $response->assertStatus(200);
    $response->assertSee('x-data="listFilter', false);
});

test('games data includes all required fields for filtering', function () {
    $game = Game::factory()->create([
        'name' => 'Complete Game',
        'slug' => 'complete-game',
        'first_release_date' => now()->addMonth(),
    ]);
    $game->platforms()->attach($this->pcPlatform);
    $game->genres()->attach($this->actionGenre);
    $this->systemList->games()->attach($game->id);
    $this->systemList->load(['games.platforms', 'games.genres', 'games.gameModes', 'games.playerPerspectives']);

    $gamesData = $this->systemList->getGamesForFiltering();
    $gameData = $gamesData[0];

    expect($gameData)->toHaveKeys([
        'id',
        'name',
        'slug',
        'cover_url',
        'release_date',
        'release_date_formatted',
        'platforms',
        'genres',
        'game_type',
        'modes',
        'perspectives',
    ]);
});

test('filter options structure is correct', function () {
    $game = Game::factory()->create(['name' => 'Structure Test Game']);
    $game->platforms()->attach($this->pcPlatform);
    $game->genres()->attach($this->actionGenre);
    $this->systemList->games()->attach($game->id);
    $this->systemList->load(['games.platforms', 'games.genres', 'games.gameModes', 'games.playerPerspectives']);

    $filterOptions = $this->systemList->getFilterOptions();

    expect($filterOptions)->toHaveKeys(['platforms', 'genres', 'gameTypes', 'modes', 'perspectives']);

    // Check platform option structure
    $platformOption = $filterOptions['platforms'][0];
    expect($platformOption)->toHaveKeys(['id', 'name', 'count']);
});

test('JSON-LD schema includes correct game count', function () {
    $game1 = Game::factory()->create(['name' => 'Schema Game 1', 'slug' => 'schema-game-1']);
    $game2 = Game::factory()->create(['name' => 'Schema Game 2', 'slug' => 'schema-game-2']);
    $this->systemList->games()->attach([$game1->id, $game2->id]);

    $response = $this->get('/list/events/test-event-list');

    $response->assertStatus(200);
    $response->assertSee('"numberOfItems": 2', false);
});

test('OG meta tags include game count', function () {
    $game1 = Game::factory()->create(['name' => 'OG Game 1']);
    $game2 = Game::factory()->create(['name' => 'OG Game 2']);
    $game3 = Game::factory()->create(['name' => 'OG Game 3']);
    $this->systemList->games()->attach([$game1->id, $game2->id, $game3->id]);

    $response = $this->get('/list/events/test-event-list');

    $response->assertStatus(200);
    $response->assertSee('3+ games', false);
});

test('game links use correct /game/ url pattern not /games/', function () {
    $response = $this->get('/list/events/test-event-list');

    $response->assertStatus(200);
    // Verify correct URL pattern is used in Alpine.js template
    $response->assertSee("'/game/' + game.slug", false);
    // Ensure incorrect pattern is not present
    $response->assertDontSee("'/games/' + game.slug", false);
});

test('games data includes slug for url generation', function () {
    $game = Game::factory()->create([
        'name' => 'URL Test Game',
        'slug' => 'url-test-game',
    ]);
    $this->systemList->games()->attach($game->id);
    $this->systemList->load(['games.platforms', 'games.genres', 'games.gameModes', 'games.playerPerspectives']);

    $gamesData = $this->systemList->getGamesForFiltering();

    expect($gamesData[0]['slug'])->toBe('url-test-game');
});

// Pivot data override tests

test('pivot platforms override game platforms in getGamesForFiltering', function () {
    $xboxPlatform = Platform::factory()->create(['name' => 'Xbox Series X', 'igdb_id' => 169]);

    $game = Game::factory()->create(['name' => 'Multi Platform Game']);
    $game->platforms()->attach([$this->pcPlatform->id, $this->ps5Platform->id, $xboxPlatform->id]);

    // Attach with pivot platforms overriding to only PC and PS5
    $this->systemList->games()->attach($game->id, [
        'order' => 1,
        'platforms' => json_encode([6, 167]), // PC and PS5 igdb_ids
    ]);

    $this->systemList->load(['games.platforms', 'games.genres', 'games.gameModes', 'games.playerPerspectives']);
    $gamesData = $this->systemList->getGamesForFiltering();

    $platformIds = collect($gamesData[0]['platforms'])->pluck('igdb_id')->toArray();

    expect($platformIds)->toContain(6);    // PC
    expect($platformIds)->toContain(167);  // PS5
    expect($platformIds)->not->toContain(169); // Xbox should NOT be included
    expect(count($platformIds))->toBe(2);
});

test('pivot platforms override game platforms in getFilterOptions', function () {
    $xboxPlatform = Platform::factory()->create(['name' => 'Xbox Series X', 'igdb_id' => 169]);

    $game = Game::factory()->create(['name' => 'Multi Platform Game']);
    $game->platforms()->attach([$this->pcPlatform->id, $this->ps5Platform->id, $xboxPlatform->id]);

    // Attach with pivot platforms overriding to only PC
    $this->systemList->games()->attach($game->id, [
        'order' => 1,
        'platforms' => json_encode([6]), // Only PC
    ]);

    $this->systemList->load(['games.platforms', 'games.genres', 'games.gameModes', 'games.playerPerspectives']);
    $filterOptions = $this->systemList->getFilterOptions();

    $platformIds = collect($filterOptions['platforms'])->pluck('igdb_id')->toArray();

    expect($platformIds)->toContain(6);        // PC should be included
    expect($platformIds)->not->toContain(167); // PS5 should NOT be included
    expect($platformIds)->not->toContain(169); // Xbox should NOT be included
});

test('game platforms used as fallback when pivot platforms not set', function () {
    $game = Game::factory()->create(['name' => 'Fallback Platform Game']);
    $game->platforms()->attach([$this->pcPlatform->id, $this->ps5Platform->id]);

    // Attach without pivot platforms
    $this->systemList->games()->attach($game->id, ['order' => 1]);

    $this->systemList->load(['games.platforms', 'games.genres', 'games.gameModes', 'games.playerPerspectives']);
    $gamesData = $this->systemList->getGamesForFiltering();

    $platformIds = collect($gamesData[0]['platforms'])->pluck('igdb_id')->toArray();

    expect($platformIds)->toContain(6);   // PC from game
    expect($platformIds)->toContain(167); // PS5 from game
});

test('pivot release date overrides game release date in getGamesForFiltering', function () {
    $game = Game::factory()->create([
        'name' => 'Override Release Date Game',
        'first_release_date' => '2025-01-15',
    ]);

    // Attach with pivot release_date overriding
    $this->systemList->games()->attach($game->id, [
        'order' => 1,
        'release_date' => '2026-06-20',
    ]);

    $this->systemList->load(['games.platforms', 'games.genres', 'games.gameModes', 'games.playerPerspectives']);
    $gamesData = $this->systemList->getGamesForFiltering();

    expect($gamesData[0]['release_date'])->toBe('2026-06-20');
    expect($gamesData[0]['release_date_formatted'])->toBe('Jun 20, 2026');
});

test('game release date used as fallback when pivot release date not set', function () {
    $game = Game::factory()->create([
        'name' => 'Fallback Release Date Game',
        'first_release_date' => '2025-03-10',
    ]);

    // Attach without pivot release_date
    $this->systemList->games()->attach($game->id, ['order' => 1]);

    $this->systemList->load(['games.platforms', 'games.genres', 'games.gameModes', 'games.playerPerspectives']);
    $gamesData = $this->systemList->getGamesForFiltering();

    expect($gamesData[0]['release_date'])->toBe('2025-03-10');
    expect($gamesData[0]['release_date_formatted'])->toBe('Mar 10, 2025');
});

test('pivot platforms with empty array falls back to game platforms', function () {
    $game = Game::factory()->create(['name' => 'Empty Pivot Platforms Game']);
    $game->platforms()->attach([$this->pcPlatform->id]);

    // Attach with empty pivot platforms array
    $this->systemList->games()->attach($game->id, [
        'order' => 1,
        'platforms' => json_encode([]),
    ]);

    $this->systemList->load(['games.platforms', 'games.genres', 'games.gameModes', 'games.playerPerspectives']);
    $gamesData = $this->systemList->getGamesForFiltering();

    $platformIds = collect($gamesData[0]['platforms'])->pluck('igdb_id')->toArray();

    expect($platformIds)->toContain(6); // PC from game fallback
});
