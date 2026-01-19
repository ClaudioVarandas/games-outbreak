<?php

use App\Enums\NewsStatusEnum;
use App\Models\News;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->user = User::factory()->create(['is_admin' => false]);
});

describe('Public News Pages', function () {
    beforeEach(function () {
        config(['features.news' => true]);
    });

    it('displays the news index page', function () {
        $response = $this->get(route('news.index'));

        $response->assertStatus(200);
        $response->assertSee('News');
    });

    it('displays published news articles on index', function () {
        $publishedNews = News::factory()->published()->create([
            'title' => 'Published Article Title',
        ]);

        $draftNews = News::factory()->create([
            'title' => 'Draft Article Title',
        ]);

        $response = $this->get(route('news.index'));

        $response->assertStatus(200);
        $response->assertSee('Published Article Title');
        $response->assertDontSee('Draft Article Title');
    });

    it('displays a single published news article', function () {
        $news = News::factory()->published()->create([
            'title' => 'Test News Article',
            'summary' => 'This is a test summary',
        ]);

        $response = $this->get(route('news.show', $news));

        $response->assertStatus(200);
        $response->assertSee('Test News Article');
        $response->assertSee('This is a test summary');
    });

    it('returns 404 for unpublished news to guests', function () {
        $news = News::factory()->create([
            'status' => NewsStatusEnum::Draft,
        ]);

        $response = $this->get(route('news.show', $news));

        $response->assertNotFound();
    });

    it('allows admin to view unpublished news', function () {
        $news = News::factory()->create([
            'title' => 'Draft Article',
            'status' => NewsStatusEnum::Draft,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('news.show', $news));

        $response->assertStatus(200);
        $response->assertSee('Draft Article');
    });
});

describe('Feature Flag', function () {
    it('returns 404 when news feature is disabled', function () {
        config(['features.news' => false]);

        $response = $this->get(route('news.index'));

        $response->assertNotFound();
    });

    it('returns 404 for guests when news feature is admin-only', function () {
        config(['features.news' => 'admin']);

        $response = $this->get(route('news.index'));

        $response->assertNotFound();
    });

    it('returns 404 for regular users when news feature is admin-only', function () {
        config(['features.news' => 'admin']);

        $response = $this->actingAs($this->user)
            ->get(route('news.index'));

        $response->assertNotFound();
    });

    it('allows admin to access news when feature is admin-only', function () {
        config(['features.news' => 'admin']);

        $response = $this->actingAs($this->admin)
            ->get(route('news.index'));

        $response->assertStatus(200);
    });
});

describe('Admin News Management', function () {
    it('shows news index to admin', function () {
        $news = News::factory()->count(3)->create();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.news.index'));

        $response->assertStatus(200);
        $response->assertSee('News Management');
    });

    it('prevents non-admin from accessing news admin', function () {
        $response = $this->actingAs($this->user)
            ->get(route('admin.news.index'));

        $response->assertForbidden();
    });

    it('shows create form to admin', function () {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.news.create'));

        $response->assertStatus(200);
        $response->assertSee('Create Article');
    });

    it('allows admin to create news article', function () {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.news.store'), [
                'title' => 'New Test Article',
                'summary' => 'This is a test summary for the article',
                'content' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Content']]]]],
                'status' => NewsStatusEnum::Draft->value,
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseHas('news', [
            'title' => 'New Test Article',
            'summary' => 'This is a test summary for the article',
            'status' => NewsStatusEnum::Draft->value,
        ]);
    });

    it('auto-sets published_at when publishing', function () {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.news.store'), [
                'title' => 'Published Test Article',
                'summary' => 'This is a test summary',
                'content' => ['type' => 'doc', 'content' => []],
                'status' => NewsStatusEnum::Published->value,
            ]);

        $response->assertRedirect();

        $news = News::where('title', 'Published Test Article')->first();
        expect($news->published_at)->not->toBeNull();
    });

    it('allows admin to update news article', function () {
        $news = News::factory()->create([
            'title' => 'Original Title',
        ]);

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.news.update', $news), [
                'title' => 'Updated Title',
                'summary' => 'Updated summary text here',
                'content' => json_encode(['type' => 'doc', 'content' => []]),
                'status' => NewsStatusEnum::Draft->value,
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseHas('news', [
            'id' => $news->id,
            'title' => 'Updated Title',
        ]);
    });

    it('allows admin to delete news article', function () {
        $news = News::factory()->create();

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.news.destroy', $news));

        $response->assertRedirect(route('admin.news.index'));

        $this->assertDatabaseMissing('news', [
            'id' => $news->id,
        ]);
    });

    it('validates required fields on create', function () {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.news.store'), [
                'title' => '',
                'summary' => '',
                'content' => [],
                'status' => '',
            ]);

        $response->assertSessionHasErrors(['title', 'summary', 'content', 'status']);
    });

    it('validates summary max length', function () {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.news.store'), [
                'title' => 'Test Article',
                'summary' => str_repeat('a', 281),
                'content' => ['type' => 'doc', 'content' => []],
                'status' => NewsStatusEnum::Draft->value,
            ]);

        $response->assertSessionHasErrors(['summary']);
    });
});

describe('News Model', function () {
    it('generates unique slug on creation', function () {
        $news = News::factory()->create(['title' => 'Test Article']);

        expect($news->slug)->not->toBeEmpty();
    });

    it('generates unique slug for duplicate titles', function () {
        $news1 = News::create([
            'title' => 'Same Title',
            'summary' => 'Summary 1',
            'content' => ['type' => 'doc', 'content' => []],
            'status' => NewsStatusEnum::Draft,
        ]);

        $news2 = News::create([
            'title' => 'Same Title',
            'summary' => 'Summary 2',
            'content' => ['type' => 'doc', 'content' => []],
            'status' => NewsStatusEnum::Draft,
        ]);

        expect($news1->slug)->not->toBe($news2->slug);
    });

    it('returns correct published scope', function () {
        News::factory()->published()->count(2)->create();
        News::factory()->count(3)->create(['status' => NewsStatusEnum::Draft]);

        expect(News::published()->count())->toBe(2);
    });

    it('calculates reading time', function () {
        $news = News::factory()->create([
            'content' => [
                'type' => 'doc',
                'content' => [
                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => str_repeat('word ', 400)]]],
                ],
            ],
        ]);

        expect($news->reading_time)->toBe(2);
    });

    it('handles image url for external urls', function () {
        $news = News::factory()->create([
            'image_path' => 'https://example.com/image.jpg',
        ]);

        expect($news->image_url)->toBe('https://example.com/image.jpg');
    });

    it('returns null for missing image', function () {
        $news = News::factory()->create([
            'image_path' => null,
        ]);

        expect($news->image_url)->toBeNull();
    });
});

describe('URL Import Feature', function () {
    it('returns 403 when url import is disabled', function () {
        config(['features.news_url_import' => false]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.news.import-url'), [
                'url' => 'https://example.com/article',
            ]);

        $response->assertForbidden();
    });

    it('validates url is required', function () {
        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.news.import-url'), [
                'url' => '',
            ]);

        $response->assertStatus(422);
    });

    it('validates url format', function () {
        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.news.import-url'), [
                'url' => 'not-a-valid-url',
            ]);

        $response->assertStatus(422);
    });
});
