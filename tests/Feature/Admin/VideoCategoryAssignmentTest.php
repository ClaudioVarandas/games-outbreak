<?php

use App\Models\User;
use App\Models\Video;
use App\Models\VideoCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('admin can assign a category to a video', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $video = Video::factory()->create();
    $cat = VideoCategory::factory()->create();

    $this->actingAs($admin)
        ->patch(route('admin.videos.update-category', $video), [
            'video_category_id' => $cat->id,
        ])
        ->assertRedirect();

    expect($video->fresh()->video_category_id)->toBe($cat->id);
});

it('admin can clear a category by submitting empty', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $cat = VideoCategory::factory()->create();
    $video = Video::factory()->create(['video_category_id' => $cat->id]);

    $this->actingAs($admin)
        ->patch(route('admin.videos.update-category', $video), [
            'video_category_id' => '',
        ])
        ->assertRedirect();

    expect($video->fresh()->video_category_id)->toBeNull();
});

it('rejects bogus category ids', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $video = Video::factory()->create();

    $this->actingAs($admin)
        ->patch(route('admin.videos.update-category', $video), [
            'video_category_id' => 99999,
        ])
        ->assertSessionHasErrors('video_category_id');
});

it('non-admin cannot assign categories', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $video = Video::factory()->create();
    $cat = VideoCategory::factory()->create();

    $this->actingAs($user)
        ->patch(route('admin.videos.update-category', $video), [
            'video_category_id' => $cat->id,
        ])
        ->assertForbidden();
});
