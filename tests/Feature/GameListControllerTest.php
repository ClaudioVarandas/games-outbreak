<?php

namespace Tests\Feature;

use App\Enums\ListTypeEnum;
use App\Models\Game;
use App\Models\GameList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameListControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_index_requires_authentication(): void
    {
        $response = $this->get('/lists');

        $response->assertRedirect('/login');
    }

    public function test_lists_index_displays_user_lists(): void
    {
        $user = User::factory()->create();
        $list = GameList::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get('/lists');

        $response->assertStatus(200);
        $response->assertViewIs('lists.index');
        $response->assertViewHas('regularLists', function ($lists) use ($list) {
            return $lists->contains('id', $list->id);
        });
    }

    public function test_lists_index_creates_special_lists_if_missing(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/lists');

        $response->assertStatus(200);
        $this->assertDatabaseHas('game_lists', [
            'user_id' => $user->id,
            'list_type' => ListTypeEnum::BACKLOG->value,
        ]);
        $this->assertDatabaseHas('game_lists', [
            'user_id' => $user->id,
            'list_type' => ListTypeEnum::WISHLIST->value,
        ]);
    }

    public function test_create_list_form_requires_authentication(): void
    {
        $response = $this->get('/lists/create');

        $response->assertRedirect('/login');
    }

    public function test_create_list_form_loads(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/lists/create');

        $response->assertStatus(200);
        $response->assertViewIs('lists.create');
    }

    public function test_store_creates_new_list(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/lists', [
            'name' => 'My New List',
            'description' => 'Test description',
            'is_public' => false,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('game_lists', [
            'user_id' => $user->id,
            'name' => 'My New List',
            'list_type' => ListTypeEnum::REGULAR->value,
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/lists', []);

        $response->assertSessionHasErrors('name');
    }

    public function test_store_prevents_creating_backlog_wishlist_manually(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/lists', [
            'name' => 'Backlog',
            'list_type' => 'backlog',
        ]);

        $response->assertStatus(403);
    }

    public function test_show_displays_list(): void
    {
        $user = User::factory()->create();
        $list = GameList::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get('/lists/' . $list->id);

        $response->assertStatus(200);
        $response->assertViewIs('lists.show');
        $response->assertViewHas('gameList', $list);
    }

    public function test_show_requires_authentication(): void
    {
        $list = GameList::factory()->create();

        $response = $this->get('/lists/' . $list->id);

        $response->assertRedirect('/login');
    }

    public function test_add_game_to_list(): void
    {
        $user = User::factory()->create();
        $list = GameList::factory()->create(['user_id' => $user->id]);
        $game = Game::factory()->create();

        $response = $this->actingAs($user)->post('/lists/' . $list->id . '/games', [
            'game_id' => $game->igdb_id,
        ]);

        $response->assertRedirect();
        $list->refresh();
        $this->assertTrue($list->games()->where('game_id', $game->id)->exists());
    }

    public function test_remove_game_from_list(): void
    {
        $user = User::factory()->create();
        $list = GameList::factory()->create(['user_id' => $user->id]);
        $game = Game::factory()->create();
        $list->games()->attach($game->id);

        $response = $this->actingAs($user)->delete('/lists/' . $list->id . '/games/' . $game->id);

        $response->assertRedirect();
        $this->assertFalse($list->fresh()->games->contains($game));
    }

    public function test_backlog_page_loads(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/backlog');

        $response->assertStatus(200);
        $response->assertViewIs('backlog.index');
    }

    public function test_wishlist_page_loads(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/wishlist');

        $response->assertStatus(200);
        $response->assertViewIs('wishlist.index');
    }

    public function test_public_system_list_is_accessible_without_auth(): void
    {
        $list = GameList::factory()->system()->public()->create([
            'slug' => 'test-list',
        ]);

        $response = $this->get('/list/test-list');

        $response->assertStatus(200);
        $response->assertViewHas('gameList', $list);
    }
}
