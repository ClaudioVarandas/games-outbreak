<?php

use App\Models\Game;
use App\Models\GameList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The default view mode is the 5th argument to listFilter(...); it sits right where the
 * call closes and the x-data attribute ends (`'list')" class="min-h-screen"`). That suffix
 * is unique to the listFilter invocation — the many other `'grid'`/`'list'` occurrences
 * (setViewMode('grid'), viewMode === 'list', …) never end the x-data attribute this way.
 */
it('defaults events lists to the list view', function () {
    $list = GameList::factory()->events()->system()->create([
        'slug' => 'nacon-connect-2026',
        'is_public' => true,
    ]);
    $list->games()->attach(Game::factory()->create()->id, [
        'order' => 1,
        'release_date' => now()->setDate(2026, 3, 14),
    ]);

    $content = $this->actingAs(User::factory()->create())
        ->get('/list/events/nacon-connect-2026')
        ->assertOk()
        ->getContent();

    expect($content)
        ->toMatch('/\'list\'\s*\)\s*"\s+class="min-h-screen"/')
        ->not->toMatch('/\'grid\'\s*\)\s*"\s+class="min-h-screen"/');
});

it('defaults non-events system lists to the grid view', function () {
    $list = GameList::factory()->yearly()->system()->create([
        'slug' => 'year-2026',
        'is_public' => true,
    ]);
    $list->games()->attach(Game::factory()->create()->id, [
        'order' => 1,
        'release_date' => now()->setDate(2026, 3, 14),
    ]);

    $content = $this->actingAs(User::factory()->create())
        ->get('/list/yearly/year-2026')
        ->assertOk()
        ->getContent();

    expect($content)
        ->toMatch('/\'grid\'\s*\)\s*"\s+class="min-h-screen"/')
        ->not->toMatch('/\'list\'\s*\)\s*"\s+class="min-h-screen"/');
});

it('renders a row trailer thumbnail for games with a video url', function () {
    $list = GameList::factory()->events()->system()->create([
        'slug' => 'nacon-connect-2026',
        'is_public' => true,
    ]);
    $list->games()->attach(Game::factory()->create()->id, [
        'order' => 1,
        'release_date' => now()->setDate(2026, 3, 14),
        'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ]);

    $this->actingAs(User::factory()->create())
        ->get('/list/events/nacon-connect-2026')
        ->assertOk()
        ->assertSee('data-video-id="dQw4w9WgXcQ"', false)
        ->assertSee('img.youtube.com/vi/dQw4w9WgXcQ/mqdefault.jpg', false);
});
