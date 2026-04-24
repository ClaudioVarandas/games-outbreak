<?php

use App\Jobs\Videos\ImportYoutubeVideoJob;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
});

it('redirects unauthenticated users', function () {
    $this->get(route('admin.videos.index'))->assertRedirect('/login');
});

it('forbids non-admin users from the index', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)
        ->get(route('admin.videos.index'))
        ->assertForbidden();
});

it('admin can view the video import index', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    Video::factory()->count(3)->create(['user_id' => $admin->id]);

    $this->actingAs($admin)
        ->get(route('admin.videos.index'))
        ->assertOk();
});

it('admin can view the create form', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->get(route('admin.videos.create'))
        ->assertOk();
});

it('admin can queue an import from a YouTube URL', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->post(route('admin.videos.store'), ['url' => 'https://www.youtube.com/watch?v=QdBZY2fkU-0'])
        ->assertRedirect(route('admin.videos.index'));

    Queue::assertPushed(ImportYoutubeVideoJob::class, fn ($job) => str_contains($job->url, 'QdBZY2fkU-0'));
});

it('rejects non-YouTube URLs', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $response = $this->actingAs($admin)
        ->post(route('admin.videos.store'), ['url' => 'https://vimeo.com/123']);

    $response->assertSessionHasErrors('url');

    Queue::assertNothingPushed();
});

it('rejects invalid URLs', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->post(route('admin.videos.store'), ['url' => 'not-a-url'])
        ->assertSessionHasErrors('url');
});

it('toggle-featured sets only the chosen video as featured', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $target = Video::factory()->create(['user_id' => $admin->id]);
    $other = Video::factory()->featured()->create(['user_id' => $admin->id]);

    $this->actingAs($admin)
        ->patch(route('admin.videos.toggle-featured', $target))
        ->assertRedirect();

    expect($target->fresh()->is_featured)->toBeTrue()
        ->and($other->fresh()->is_featured)->toBeFalse();
});

it('toggle-active flips the is_active flag', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $video = Video::factory()->create(['user_id' => $admin->id, 'is_active' => true]);

    $this->actingAs($admin)
        ->patch(route('admin.videos.toggle-active', $video))
        ->assertRedirect();

    expect($video->fresh()->is_active)->toBeFalse();
});

it('admin can delete a video', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $video = Video::factory()->create(['user_id' => $admin->id]);

    $this->actingAs($admin)
        ->delete(route('admin.videos.destroy', $video))
        ->assertRedirect(route('admin.videos.index'));

    expect(Video::find($video->id))->toBeNull();
});
