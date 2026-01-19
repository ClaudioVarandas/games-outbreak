# News Feature Specification

## Overview

A news feed system for gaming news articles with public viewing and admin management. Features include:
- Public feed page (X.com-style: title + short summary)
- Admin CRUD with Tiptap WYSIWYG editor
- URL import via jina.ai for auto-filling articles from external sources
- Feature-flagged URL import for easy enable/disable

---

## Database Schema

### Migration: `create_news_table`

```php
Schema::create('news', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->string('slug')->unique();
    $table->string('image_path')->nullable();
    $table->string('summary', 280);           // Tweet-length summary
    $table->json('content')->nullable();       // Tiptap JSON content
    $table->string('status')->default('draft');
    $table->string('source_url')->nullable();
    $table->string('source_name')->nullable();
    $table->json('tags')->nullable();
    $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
    $table->timestamp('published_at')->nullable();
    $table->timestamps();

    $table->index('status');
    $table->index('published_at');
});
```

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `title` | string(255) | Article title |
| `slug` | string | URL-friendly unique identifier |
| `image_path` | string | Path to uploaded image or external URL |
| `summary` | string(280) | Short summary (tweet-length) |
| `content` | json | Tiptap JSON document |
| `status` | string | NewsStatusEnum value |
| `source_url` | string | Original article URL (if imported) |
| `source_name` | string | Source website name |
| `tags` | json | Array of tag strings |
| `user_id` | foreignId | Author (admin who created) |
| `published_at` | timestamp | Publication date |

---

## Enum: NewsStatusEnum

```php
// app/Enums/NewsStatusEnum.php
enum NewsStatusEnum: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case ARCHIVED = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::PUBLISHED => 'Published',
            self::ARCHIVED => 'Archived',
        };
    }

    public function colorClass(): string
    {
        return match ($this) {
            self::DRAFT => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
            self::PUBLISHED => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
            self::ARCHIVED => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
        };
    }
}
```

---

## Feature Flag & Service Abstraction

### Config: features.php

```php
// config/features.php
return [
    // Master toggle for the entire news feature (public feed + admin management)
    'news' => env('FEATURE_NEWS', true),

    // Sub-feature: URL import functionality (only checked if news is enabled)
    'news_url_import' => env('FEATURE_NEWS_URL_IMPORT', true),
];
```

### Feature Flag Middleware

```php
// app/Http/Middleware/EnsureNewsFeatureEnabled.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureNewsFeatureEnabled
{
    public function handle(Request $request, Closure $next)
    {
        if (!config('features.news')) {
            abort(404);
        }

        return $next($request);
    }
}
```

### Route Protection

```php
// routes/web.php - Public news routes
Route::middleware([EnsureNewsFeatureEnabled::class])->group(function () {
    Route::get('/news', [NewsController::class, 'index'])->name('news.index');
    Route::get('/news/{news:slug}', [NewsController::class, 'show'])->name('news.show');
});

// Admin news routes (inside existing admin group)
Route::middleware([EnsureNewsFeatureEnabled::class])->group(function () {
    // ... news admin routes
});
```

### Navigation Visibility

```blade
{{-- releases-nav.blade.php - Only show News link if feature enabled --}}
@if(config('features.news'))
    <a href="{{ route('news.index') }}" ...>News</a>
@endif

{{-- header.blade.php - Only show admin News link if feature enabled --}}
@if(config('features.news'))
    <a href="{{ route('admin.news.index') }}" ...>News</a>
@endif
```

### ContentExtractorInterface (Swappable Service)

```php
// app/Contracts/ContentExtractorInterface.php
namespace App\Contracts;

interface ContentExtractorInterface
{
    /**
     * Extract content from a URL
     *
     * @param string $url The URL to extract content from
     * @return array{
     *     title: ?string,
     *     summary: ?string,
     *     content: ?string,
     *     image: ?string,
     *     source_name: ?string,
     *     source_url: string
     * }
     */
    public function extract(string $url): array;
}
```

### JinaReaderService Implementation

```php
// app/Services/JinaReaderService.php
namespace App\Services;

use App\Contracts\ContentExtractorInterface;
use Illuminate\Support\Facades\Http;

class JinaReaderService implements ContentExtractorInterface
{
    private string $baseUrl = 'https://r.jina.ai/';

    public function extract(string $url): array
    {
        $response = Http::timeout(30)
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Return-Format' => 'markdown',
            ])
            ->get($this->baseUrl . $url);

        if ($response->failed()) {
            throw new \RuntimeException('Failed to extract content: ' . $response->status());
        }

        $data = $response->json();

        return [
            'title' => $data['data']['title'] ?? null,
            'summary' => $this->generateSummary($data['data']['content'] ?? null),
            'content' => $data['data']['content'] ?? null,
            'image' => $data['data']['image'] ?? $data['data']['images'][0] ?? null,
            'source_name' => str_replace('www.', '', parse_url($url, PHP_URL_HOST) ?? ''),
            'source_url' => $url,
        ];
    }

    private function generateSummary(?string $content): ?string
    {
        if (!$content) return null;

        $text = preg_replace('/[#*_\[\]()]+/', '', $content);
        $text = preg_replace('/\s+/', ' ', trim($text));

        return strlen($text) > 280 ? substr($text, 0, 277) . '...' : $text;
    }
}
```

### Service Binding

```php
// app/Providers/AppServiceProvider.php - register() method
$this->app->bind(
    \App\Contracts\ContentExtractorInterface::class,
    \App\Services\JinaReaderService::class
);
```

### Future Replacement

To swap jina.ai for another service:
1. Create new class implementing `ContentExtractorInterface`
2. Update binding in `AppServiceProvider`
3. No controller/view changes needed

---

## Routes

### Public Routes

```php
Route::get('/news', [NewsController::class, 'index'])->name('news.index');
Route::get('/news/{news:slug}', [NewsController::class, 'show'])->name('news.show');
```

### Admin Routes (inside existing admin group)

```php
Route::get('/news', [AdminNewsController::class, 'index'])->name('news.index');
Route::get('/news/create', [AdminNewsController::class, 'create'])->name('news.create');
Route::post('/news', [AdminNewsController::class, 'store'])->name('news.store');
Route::get('/news/{news}/edit', [AdminNewsController::class, 'edit'])->name('news.edit');
Route::patch('/news/{news}', [AdminNewsController::class, 'update'])->name('news.update');
Route::delete('/news/{news}', [AdminNewsController::class, 'destroy'])->name('news.destroy');
Route::post('/news/import-url', [AdminNewsController::class, 'importFromUrl'])->name('news.import-url');
```

---

## UI Mockups

### Public News Feed: /news

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  [Header]                                                                    │
├─────────────────────────────────────────────────────────────────────────────┤
│  [Releases Nav: News | Highlights | Monthly | Indie | Seasoned]             │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  ═══════════════════════════════════════════════════════════════════════════│
│  NEWS                                                                        │
│  Latest gaming news and updates                                              │
│  ═══════════════════════════════════════════════════════════════════════════│
│                                                                              │
│  ┌─────────────────────────────────────────────────────────────────────────┐│
│  │ ┌─────────┐                                                             ││
│  │ │ [Image] │  Game Title Announces Major DLC Expansion                   ││
│  │ │  thumb  │  ──────────────────────────────────────────────             ││
│  │ │         │  The highly anticipated expansion brings new areas,          ││
│  │ └─────────┘  characters, and over 20 hours of content...                ││
│  │                                                                         ││
│  │              Jan 18, 2026 • via IGN                                     ││
│  └─────────────────────────────────────────────────────────────────────────┘│
│                                                                              │
│  ┌─────────────────────────────────────────────────────────────────────────┐│
│  │ ┌─────────┐                                                             ││
│  │ │ [Image] │  PS5 Pro Gets New System Update                             ││
│  │ │  thumb  │  ──────────────────────────────────────────────             ││
│  │ │         │  Sony releases firmware update with performance             ││
│  │ └─────────┘  improvements and new features for PS5 Pro...               ││
│  │                                                                         ││
│  │              Jan 17, 2026 • via Kotaku                                  ││
│  └─────────────────────────────────────────────────────────────────────────┘│
│                                                                              │
│  [Load More / Pagination]                                                   │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Admin News List: /admin/news

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  News Management                                          [+ Create Article] │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  ┌─────────────────────────────────────────────────────────────────────────┐│
│  │ Title                        │ Status    │ Author  │ Published │ Actions││
│  ├─────────────────────────────────────────────────────────────────────────┤│
│  │ Game Announces DLC           │ [PUBLISHED]│ Admin   │ Jan 18    │ [Edit] ││
│  │ PS5 Pro Update               │ [DRAFT]   │ Admin   │ -         │ [Edit] ││
│  │ Nintendo Direct Recap        │ [ARCHIVED]│ Admin   │ Jan 10    │ [Edit] ││
│  └─────────────────────────────────────────────────────────────────────────┘│
│                                                                              │
│  [Pagination]                                                               │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Admin Create/Edit: /admin/news/create

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  Create News Article                                                         │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  ┌─────────────────────────────────────────────────────────────────────────┐│
│  │ IMPORT FROM URL (Feature Flagged - only shown if enabled)               ││
│  │ ─────────────────────────────────────────────────────────────────────── ││
│  │                                                                         ││
│  │ URL: [https://ign.com/article/...                        ] [Import]     ││
│  │                                                                         ││
│  │ Automatically extracts title, summary, content, and image from URL      ││
│  └─────────────────────────────────────────────────────────────────────────┘│
│                                                                              │
│  ═══════════════════════════════════════════════════════════════════════════│
│  ARTICLE DETAILS                                                             │
│  ═══════════════════════════════════════════════════════════════════════════│
│                                                                              │
│  Title: [_________________________________________________________]         │
│                                                                              │
│  Summary (max 280 chars):                                                   │
│  [____________________________________________________________]             │
│  [                                                            ]             │
│                                                                              │
│  Image URL: [_____________________________________________________]         │
│                                                                              │
│  Content:                                                                   │
│  ┌─────────────────────────────────────────────────────────────────────────┐│
│  │ [B] [I] [H2] [List] [Link] [Image]                        (Tiptap)     ││
│  ├─────────────────────────────────────────────────────────────────────────┤│
│  │                                                                         ││
│  │  Write your article content here...                                     ││
│  │                                                                         ││
│  │                                                                         ││
│  └─────────────────────────────────────────────────────────────────────────┘│
│                                                                              │
│  Source URL: [https://original-source.com/article           ]               │
│  Source Name: [IGN                                          ]               │
│                                                                              │
│  Tags: [gaming] [ps5] [dlc] [+]                                             │
│                                                                              │
│  Status: [Draft ▼]      Published At: [____/____/________]                  │
│                                                                              │
│  [Save Draft]  [Publish]                                                    │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## NPM Dependencies (Tiptap)

```bash
npm install @tiptap/vue-3 @tiptap/starter-kit @tiptap/extension-image @tiptap/extension-link @tiptap/extension-placeholder
```

---

## Files to Create

| File | Purpose |
|------|---------|
| `database/migrations/xxx_create_news_table.php` | Database schema |
| `app/Enums/NewsStatusEnum.php` | Status enum |
| `app/Models/News.php` | Eloquent model |
| `app/Contracts/ContentExtractorInterface.php` | Service interface |
| `app/Services/JinaReaderService.php` | jina.ai implementation |
| `app/Http/Middleware/EnsureNewsFeatureEnabled.php` | Feature flag middleware |
| `app/Http/Controllers/NewsController.php` | Public feed |
| `app/Http/Controllers/AdminNewsController.php` | Admin CRUD |
| `app/Http/Requests/StoreNewsRequest.php` | Create validation |
| `app/Http/Requests/UpdateNewsRequest.php` | Update validation |
| `config/features.php` | Feature flags config |
| `resources/js/components/TiptapEditor.vue` | WYSIWYG editor |
| `resources/views/news/index.blade.php` | Public feed |
| `resources/views/news/show.blade.php` | Single article |
| `resources/views/admin/news/index.blade.php` | Admin list |
| `resources/views/admin/news/create.blade.php` | Admin create |
| `resources/views/admin/news/edit.blade.php` | Admin edit |
| `database/factories/NewsFactory.php` | Test factory |
| `tests/Feature/NewsTest.php` | Pest tests |

## Files to Modify

| File | Changes |
|------|---------|
| `routes/web.php` | Add public and admin news routes |
| `resources/views/components/releases-nav.blade.php` | Add "News" as first item |
| `resources/views/components/header.blade.php` | Add "News" to admin dropdown |
| `resources/js/app.js` | Register TiptapEditor component |
| `package.json` | Add Tiptap dependencies |
| `app/Providers/AppServiceProvider.php` | Bind ContentExtractorInterface |

---

## Implementation Tasks

### Phase 1: Database Layer
1. Create migration for `news` table
2. Create `NewsStatusEnum` enum
3. Create `News` model with casts, scopes, helpers
4. Create `NewsFactory` factory
5. Run migration

### Phase 2: Feature Flag & Service
6. Create `config/features.php` with `news_url_import` flag
7. Create `ContentExtractorInterface` contract
8. Create `JinaReaderService` implementing the interface
9. Register binding in `AppServiceProvider`

### Phase 3: Backend Controllers
10. Create `StoreNewsRequest` and `UpdateNewsRequest`
11. Create `NewsController` (public: index, show)
12. Create `AdminNewsController` (CRUD + URL import with feature flag check)
13. Add routes to `web.php`

### Phase 4: Frontend - Tiptap
14. Install npm packages
15. Create `TiptapEditor.vue` component
16. Register in `app.js`
17. Run npm build

### Phase 5: Views
18. Create `news/index.blade.php` (public feed)
19. Create `news/show.blade.php` (single article)
20. Create `admin/news/index.blade.php`
21. Create `admin/news/create.blade.php`
22. Create `admin/news/edit.blade.php`
23. Update `releases-nav.blade.php` (add News link first)
24. Update `header.blade.php` (add admin News menu item)

### Phase 6: Testing & Cleanup
25. Create `NewsTest.php` with Pest
26. Run pint
27. Run tests

---

## Design Decisions

1. **Tags as JSON array** - Stored in model like GameList, no separate tags table
2. **Tiptap JSON content** - Stored as JSON, rendered client-side or server-side
3. **Feature-flagged URL import** - Can be disabled via config without code changes
4. **Swappable content extractor** - Interface allows easy replacement of jina.ai
5. **Auto-publish date** - Set `published_at` to now() when status changes to published
6. **Summary limit 280 chars** - Tweet-length for feed display
7. **Slug auto-generation** - Generated from title on create, unique enforced

---

## Testing Plan

```php
// tests/Feature/NewsTest.php

describe('Feature Flag', function () {
    it('returns 404 for news routes when feature disabled');
    it('hides news link in navigation when feature disabled');
    it('shows news routes when feature enabled');
});

describe('Public News Feed', function () {
    it('displays the news feed page');
    it('shows published news articles');
    it('hides draft articles from public');
    it('shows individual news article');
    it('returns 404 for unpublished article for non-admin');
    it('allows admin to view unpublished article');
});

describe('Admin News Management', function () {
    it('allows admin to view news index');
    it('prevents non-admin from accessing admin news');
    it('allows admin to create news article');
    it('auto-sets published_at when publishing');
    it('allows admin to update news article');
    it('allows admin to delete news article');
});

describe('URL Import Feature', function () {
    it('imports content from URL when feature enabled');
    it('rejects import when feature disabled');
    it('handles invalid URLs gracefully');
});
```