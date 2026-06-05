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
    $this->game = Game::factory()->create();
    $this->list = GameList::factory()->create([
        'list_type' => ListTypeEnum::YEARLY->value,
        'is_system' => true,
        'is_active' => true,
        'is_public' => true,
        'slug' => 'yearly-2026',
        'start_at' => now()->startOfYear(),
    ]);
    $this->list->games()->attach($this->game->id, ['order' => 1]);
});

it('marks a game as highlight without any genre', function () {
    $this->actingAs($this->admin)
        ->patchJson(route('admin.system-lists.games.toggle-highlight', [
            'type' => 'yearly', 'slug' => 'yearly-2026', 'game' => $this->game->id,
        ]))
        ->assertSuccessful()
        ->assertJson(['is_highlight' => true]);

    $pivot = $this->list->games()->where('games.id', $this->game->id)->first()->pivot;

    expect((bool) $pivot->is_highlight)->toBeTrue()
        ->and($pivot->primary_genre_id)->toBeNull();
});

it('marks a game as indie without any genre', function () {
    $this->actingAs($this->admin)
        ->patchJson(route('admin.system-lists.games.toggle-indie', [
            'type' => 'yearly', 'slug' => 'yearly-2026', 'game' => $this->game->id,
        ]))
        ->assertSuccessful()
        ->assertJson(['is_indie' => true]);

    $pivot = $this->list->games()->where('games.id', $this->game->id)->first()->pivot;

    expect((bool) $pivot->is_indie)->toBeTrue()
        ->and($pivot->primary_genre_id)->toBeNull();
});

it('still stores the primary genre when one is provided to highlight', function () {
    $genre = Genre::factory()->create();

    $this->actingAs($this->admin)
        ->patchJson(route('admin.system-lists.games.toggle-highlight', [
            'type' => 'yearly', 'slug' => 'yearly-2026', 'game' => $this->game->id,
        ]), [
            'primary_genre_id' => $genre->id,
            'genre_ids' => [$genre->id],
        ])
        ->assertSuccessful();

    $pivot = $this->list->games()->where('games.id', $this->game->id)->first()->pivot;

    expect($pivot->primary_genre_id)->toBe($genre->id);
});
