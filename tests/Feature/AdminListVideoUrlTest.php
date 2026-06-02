<?php

use App\Models\Game;
use App\Models\GameList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function videoListAndGame(): array
{
    $admin = User::factory()->create(['is_admin' => true]);
    $list = GameList::factory()->events()->system()->create([
        'user_id' => $admin->id,
        'slug' => 'nacon-connect-2026',
    ]);
    $game = Game::factory()->create(['igdb_id' => 555111]);

    return [$admin, $list, $game];
}

it('stores video_url when adding a game to an events list', function () {
    [$admin, $list, $game] = videoListAndGame();

    $this->actingAs($admin)
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post('/admin/system-lists/events/nacon-connect-2026/games', [
            'game_id' => 555111,
            'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ])
        ->assertJson(['success' => true]);

    $pivot = $list->games()->where('game_id', $game->id)->first()->pivot;
    expect($pivot->video_url)->toBe('https://www.youtube.com/watch?v=dQw4w9WgXcQ');
});

it('updates video_url via the pivot endpoint', function () {
    [$admin, $list, $game] = videoListAndGame();
    $list->games()->attach($game->id, ['order' => 1]);

    $this->actingAs($admin)
        ->patchJson('/admin/system-lists/events/nacon-connect-2026/games/'.$game->id.'/pivot', [
            'video_url' => 'https://youtu.be/dQw4w9WgXcQ',
        ])
        ->assertJson(['success' => true]);

    $pivot = $list->games()->where('game_id', $game->id)->first()->pivot;
    expect($pivot->video_url)->toBe('https://youtu.be/dQw4w9WgXcQ');
});

it('clears video_url when an empty value is submitted', function () {
    [$admin, $list, $game] = videoListAndGame();
    $list->games()->attach($game->id, ['order' => 1, 'video_url' => 'https://youtu.be/dQw4w9WgXcQ']);

    $this->actingAs($admin)
        ->patchJson('/admin/system-lists/events/nacon-connect-2026/games/'.$game->id.'/pivot', [
            'video_url' => '',
        ])
        ->assertJson(['success' => true]);

    $pivot = $list->games()->where('game_id', $game->id)->first()->pivot;
    expect($pivot->video_url)->toBeNull();
});

it('rejects a non-youtube video_url', function () {
    [$admin, $list, $game] = videoListAndGame();

    $this->actingAs($admin)
        ->withHeaders(['Accept' => 'application/json'])
        ->post('/admin/system-lists/events/nacon-connect-2026/games', [
            'game_id' => 555111,
            'video_url' => 'https://example.com/not-youtube',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['video_url']);
});

it('leaves an existing video_url untouched when the field is omitted from a pivot update', function () {
    [$admin, $list, $game] = videoListAndGame();
    $list->games()->attach($game->id, ['order' => 1, 'video_url' => 'https://youtu.be/dQw4w9WgXcQ']);

    $this->actingAs($admin)
        ->patchJson('/admin/system-lists/events/nacon-connect-2026/games/'.$game->id.'/pivot', [
            'is_tba' => true,
        ])
        ->assertJson(['success' => true]);

    $pivot = $list->games()->where('game_id', $game->id)->first()->pivot;
    expect($pivot->video_url)->toBe('https://youtu.be/dQw4w9WgXcQ');
});
