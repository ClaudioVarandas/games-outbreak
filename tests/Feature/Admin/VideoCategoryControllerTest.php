<?php

use App\Models\User;
use App\Models\Video;
use App\Models\VideoCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects unauthenticated users', function () {
    $this->get(route('admin.video-categories.index'))->assertRedirect('/login');
});

it('forbids non-admin users', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)
        ->get(route('admin.video-categories.index'))
        ->assertForbidden();
});

it('admin can view the categories index', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    VideoCategory::factory()->count(2)->create();

    $this->actingAs($admin)
        ->get(route('admin.video-categories.index'))
        ->assertOk();
});

it('admin can create a category', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->post(route('admin.video-categories.store'), [
            'name' => 'Trailers',
            'slug' => 'trailers',
            'color' => '#ff8a2a',
            'icon' => 'film',
            'is_active' => '1',
        ])
        ->assertRedirect();

    expect(VideoCategory::where('slug', 'trailers')->exists())->toBeTrue();
});

it('rejects invalid slugs', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->post(route('admin.video-categories.store'), [
            'name' => 'Bad',
            'slug' => 'BAD SLUG!!',
        ])
        ->assertSessionHasErrors('slug');
});

it('rejects duplicate slugs', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    VideoCategory::factory()->create(['slug' => 'trailers']);

    $this->actingAs($admin)
        ->post(route('admin.video-categories.store'), [
            'name' => 'Another',
            'slug' => 'trailers',
        ])
        ->assertSessionHasErrors('slug');
});

it('admin can update a category', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $cat = VideoCategory::factory()->create(['slug' => 'gameplay', 'name' => 'Gameplay']);

    $this->actingAs($admin)
        ->patch(route('admin.video-categories.update', $cat), [
            'name' => 'Live Gameplay',
            'slug' => 'gameplay',
            'color' => '#63f3ff',
        ])
        ->assertRedirect();

    expect($cat->fresh()->name)->toBe('Live Gameplay');
});

it('admin can delete an empty category', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $cat = VideoCategory::factory()->create();

    $this->actingAs($admin)
        ->delete(route('admin.video-categories.destroy', $cat))
        ->assertRedirect();

    expect(VideoCategory::find($cat->id))->toBeNull();
});

it('null-outs video.video_category_id when category is deleted', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $cat = VideoCategory::factory()->create();
    $video = Video::factory()->create(['video_category_id' => $cat->id]);

    $this->actingAs($admin)
        ->delete(route('admin.video-categories.destroy', $cat))
        ->assertRedirect();

    expect($video->fresh()->video_category_id)->toBeNull();
});
