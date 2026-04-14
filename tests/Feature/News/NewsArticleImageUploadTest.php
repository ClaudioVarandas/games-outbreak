<?php

use App\Models\NewsArticle;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

it('allows admin to upload an image and persists the url to the article', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $article = NewsArticle::factory()->create(['featured_image_url' => null]);

    $response = $this->actingAs($admin)
        ->postJson(route('admin.news-articles.upload-image', $article), [
            'image' => UploadedFile::fake()->image('photo.jpg'),
        ]);

    $response->assertOk();
    $response->assertJsonStructure(['url']);

    $url = $response->json('url');
    expect($url)->toStartWith('/storage/news-article-images/');

    $storagePath = ltrim(str_replace('/storage/', '', $url), '/');
    Storage::disk('public')->assertExists($storagePath);

    expect($article->fresh()->featured_image_url)->toBe($url);
});

it('returns 403 for non-admin users', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $article = NewsArticle::factory()->create();

    $this->actingAs($user)
        ->postJson(route('admin.news-articles.upload-image', $article), [
            'image' => UploadedFile::fake()->image('photo.jpg'),
        ])
        ->assertForbidden();
});

it('returns 422 for non-image file types', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $article = NewsArticle::factory()->create();

    $this->actingAs($admin)
        ->postJson(route('admin.news-articles.upload-image', $article), [
            'image' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
        ])
        ->assertUnprocessable();
});

it('returns 422 when image is missing', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $article = NewsArticle::factory()->create();

    $this->actingAs($admin)
        ->postJson(route('admin.news-articles.upload-image', $article), [])
        ->assertUnprocessable();
});

it('allows admin to remove the featured image and clears the db', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $article = NewsArticle::factory()->create(['featured_image_url' => '/storage/news-article-images/old.jpg']);

    $this->actingAs($admin)
        ->deleteJson(route('admin.news-articles.featured-image.destroy', $article))
        ->assertOk()
        ->assertJson(['success' => true]);

    expect($article->fresh()->featured_image_url)->toBeNull();
});

it('returns 403 when non-admin tries to remove the featured image', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $article = NewsArticle::factory()->create();

    $this->actingAs($user)
        ->deleteJson(route('admin.news-articles.featured-image.destroy', $article))
        ->assertForbidden();
});
