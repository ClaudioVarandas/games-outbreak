<?php

use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->user = User::factory()->create(['is_admin' => false]);
});

describe('Genre Admin Page Access', function () {
    it('shows genre index to admin', function () {
        Genre::factory()->count(3)->create();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.genres.index'));

        $response->assertStatus(200);
        $response->assertSee('Genre Management');
    });

    it('prevents non-admin from accessing genre admin', function () {
        $response = $this->actingAs($this->user)
            ->get(route('admin.genres.index'));

        $response->assertForbidden();
    });

    it('prevents guests from accessing genre admin', function () {
        $response = $this->get(route('admin.genres.index'));

        $response->assertRedirect(route('login'));
    });
});

describe('Genre CRUD', function () {
    it('allows admin to create a genre', function () {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.genres.store'), [
                'name' => 'New Test Genre',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseHas('genres', [
            'name' => 'New Test Genre',
            'slug' => 'new-test-genre',
        ]);
    });

    it('auto-generates slug from name', function () {
        $this->actingAs($this->admin)
            ->post(route('admin.genres.store'), [
                'name' => 'Metroidvania Games',
            ]);

        $this->assertDatabaseHas('genres', [
            'name' => 'Metroidvania Games',
            'slug' => 'metroidvania-games',
        ]);
    });

    it('allows admin to update genre name', function () {
        $genre = Genre::factory()->create(['name' => 'Original Name']);

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.genres.update', $genre), [
                'name' => 'Updated Name',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseHas('genres', [
            'id' => $genre->id,
            'name' => 'Updated Name',
        ]);
    });

    it('allows deletion of unused genre', function () {
        $genre = Genre::factory()->create();

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.genres.destroy', $genre));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('genres', [
            'id' => $genre->id,
        ]);
    });

    it('prevents deletion of system genre', function () {
        $genre = Genre::factory()->system()->create();

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.genres.destroy', $genre));

        $response->assertSessionHas('error');

        $this->assertDatabaseHas('genres', [
            'id' => $genre->id,
        ]);
    });

    it('validates required name field', function () {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.genres.store'), [
                'name' => '',
            ]);

        $response->assertSessionHasErrors(['name']);
    });

    it('validates unique name', function () {
        Genre::factory()->create(['name' => 'Existing Genre']);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.genres.store'), [
                'name' => 'Existing Genre',
            ]);

        $response->assertSessionHasErrors(['name']);
    });
});

describe('Genre Visibility', function () {
    it('toggles genre visibility', function () {
        $genre = Genre::factory()->create(['is_visible' => true]);

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.genres.toggle-visibility', $genre));

        $response->assertRedirect();
        expect($genre->fresh()->is_visible)->toBeFalse();

        $this->actingAs($this->admin)
            ->patch(route('admin.genres.toggle-visibility', $genre));

        expect($genre->fresh()->is_visible)->toBeTrue();
    });

    it('prevents hiding system genre', function () {
        $genre = Genre::factory()->system()->create(['is_visible' => true]);

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.genres.toggle-visibility', $genre));

        $response->assertSessionHas('error');
        expect($genre->fresh()->is_visible)->toBeTrue();
    });
});

describe('Genre Review Queue', function () {
    it('shows pending genres from IGDB sync', function () {
        $pendingGenre = Genre::factory()->pendingReview()->create(['name' => 'Pending Genre']);
        $approvedGenre = Genre::factory()->create(['name' => 'Approved Genre']);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.genres.index'));

        $response->assertStatus(200);
        $response->assertSee('Pending Review');
        $response->assertSee('Pending Genre');
    });

    it('approves pending genre', function () {
        $genre = Genre::factory()->pendingReview()->create();

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.genres.approve', $genre));

        $response->assertRedirect();
        expect($genre->fresh()->is_pending_review)->toBeFalse();
        expect($genre->fresh()->is_visible)->toBeTrue();
    });

    it('rejects and deletes pending genre', function () {
        $genre = Genre::factory()->pendingReview()->create();

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.genres.reject', $genre));

        $response->assertRedirect();
        $this->assertDatabaseMissing('genres', [
            'id' => $genre->id,
        ]);
    });
});

describe('Genre Reordering', function () {
    it('reorders genres', function () {
        $genre1 = Genre::factory()->create(['sort_order' => 1]);
        $genre2 = Genre::factory()->create(['sort_order' => 2]);
        $genre3 = Genre::factory()->create(['sort_order' => 3]);

        $response = $this->actingAs($this->admin)
            ->patchJson(route('admin.genres.reorder'), [
                'order' => [$genre3->id, $genre1->id, $genre2->id],
            ]);

        $response->assertJson(['success' => true]);

        expect($genre3->fresh()->sort_order)->toBe(0);
        expect($genre1->fresh()->sort_order)->toBe(1);
        expect($genre2->fresh()->sort_order)->toBe(2);
    });
});

describe('Genre Merge', function () {
    it('merges source genre into target', function () {
        $source = Genre::factory()->create(['name' => 'Source Genre']);
        $target = Genre::factory()->create(['name' => 'Target Genre']);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.genres.merge'), [
                'source_genre_id' => $source->id,
                'target_genre_id' => $target->id,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('genres', ['id' => $source->id]);
        $this->assertDatabaseHas('genres', ['id' => $target->id]);
    });

    it('prevents merging system genre', function () {
        $source = Genre::factory()->system()->create();
        $target = Genre::factory()->create();

        $response = $this->actingAs($this->admin)
            ->post(route('admin.genres.merge'), [
                'source_genre_id' => $source->id,
                'target_genre_id' => $target->id,
            ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('genres', ['id' => $source->id]);
    });

    it('validates different source and target', function () {
        $genre = Genre::factory()->create();

        $response = $this->actingAs($this->admin)
            ->post(route('admin.genres.merge'), [
                'source_genre_id' => $genre->id,
                'target_genre_id' => $genre->id,
            ]);

        $response->assertSessionHasErrors(['target_genre_id']);
    });
});

describe('Genre API Search', function () {
    it('returns visible genres matching query', function () {
        Genre::factory()->create(['name' => 'Metroidvania', 'is_visible' => true]);
        Genre::factory()->create(['name' => 'Roguelike', 'is_visible' => true]);
        Genre::factory()->hidden()->create(['name' => 'Metro Hidden']);

        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.api.genres.search', ['q' => 'metro']));

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['name' => 'Metroidvania']);
    });

    it('excludes pending review genres from search', function () {
        Genre::factory()->create(['name' => 'Approved Genre', 'is_visible' => true]);
        Genre::factory()->pendingReview()->create(['name' => 'Pending Genre', 'is_visible' => true]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.api.genres.search'));

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => 'Approved Genre']);
        $response->assertJsonMissing(['name' => 'Pending Genre']);
    });
});

describe('Genre Model', function () {
    it('generates slug on creation when not provided', function () {
        $genre = Genre::create([
            'name' => 'Test Genre Name',
        ]);

        expect($genre->slug)->toBe('test-genre-name');
    });

    it('identifies system genres as protected', function () {
        $systemGenre = Genre::factory()->system()->create();
        $normalGenre = Genre::factory()->create();

        expect($systemGenre->isProtected())->toBeTrue();
        expect($normalGenre->isProtected())->toBeFalse();
    });

    it('scopes visible genres correctly', function () {
        Genre::factory()->count(2)->create(['is_visible' => true]);
        Genre::factory()->hidden()->count(3)->create();

        expect(Genre::visible()->count())->toBe(2);
    });

    it('scopes pending review genres correctly', function () {
        Genre::factory()->count(2)->create();
        Genre::factory()->pendingReview()->count(3)->create();

        expect(Genre::pendingReview()->count())->toBe(3);
    });

    it('orders genres by sort_order and name', function () {
        Genre::factory()->create(['name' => 'Zebra', 'sort_order' => 1]);
        Genre::factory()->create(['name' => 'Alpha', 'sort_order' => 1]);
        Genre::factory()->create(['name' => 'Beta', 'sort_order' => 0]);

        $ordered = Genre::ordered()->get();

        expect($ordered[0]->name)->toBe('Beta');
        expect($ordered[1]->name)->toBe('Alpha');
        expect($ordered[2]->name)->toBe('Zebra');
    });
});
