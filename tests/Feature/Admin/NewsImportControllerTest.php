<?php

use App\Jobs\News\ImportNewsUrlJob;
use App\Models\NewsImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['features.news' => true]);
    Queue::fake();
});

it('redirects unauthenticated users', function () {
    $this->get(route('admin.news-imports.index'))->assertRedirect('/login');
});

it('forbids non-admin users from accessing import index', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)
        ->get(route('admin.news-imports.index'))
        ->assertForbidden();
});

it('admin can view the import index', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    NewsImport::factory()->count(3)->create(['user_id' => $admin->id]);

    $this->actingAs($admin)
        ->get(route('admin.news-imports.index'))
        ->assertOk();
});

it('admin can view the create import form', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->get(route('admin.news-imports.create'))
        ->assertOk();
});

it('admin can queue an import', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->post(route('admin.news-imports.store'), ['url' => 'https://ign.com/articles/test'])
        ->assertRedirect(route('admin.news-imports.index'));

    Queue::assertPushed(ImportNewsUrlJob::class, fn ($job) => $job->url === 'https://ign.com/articles/test');
});

it('rejects invalid URLs', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->post(route('admin.news-imports.store'), ['url' => 'not-a-url'])
        ->assertSessionHasErrors('url');
});

it('admin can view an import detail', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $import = NewsImport::factory()->create(['user_id' => $admin->id]);

    $this->actingAs($admin)
        ->get(route('admin.news-imports.show', $import))
        ->assertOk();
});
