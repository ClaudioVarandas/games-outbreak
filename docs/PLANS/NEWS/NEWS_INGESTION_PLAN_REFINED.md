# News Ingestion & Editorial Pipeline — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a queued URL-import pipeline to the existing news module that extracts article content via JinaReader, localises it to pt-PT and pt-BR via Claude API, and presents it for admin review before publishing.

**Architecture:** Three new tables (`news_imports`, `news_articles`, `news_article_localizations`) sit alongside the existing `news` table. A three-job queue chain (Import → Extract → Generate) creates an article record with two localisation records. Admin reviews and publishes. Public localized routes (`/pt-pt/noticias/{slug}`) are added but the existing `/news` routes keep working unchanged in Phase 1.

**Tech Stack:** Laravel 12, PHP 8.4, Pest 4, Tailwind v3, Laravel Http facade (Anthropic API), JinaReaderService (existing), database queue

**AI Provider Pattern:** `NewsGenerationServiceInterface` contract in `app/Contracts/`. `AnthropicNewsGenerationService` implements it. Bound in `AppServiceProvider`. Swapping providers = new class + change one binding. Same pattern as existing `ContentExtractorInterface` → `JinaReaderService`.

---

## What Already Exists (Reuse)

| Existing | Where | Reuse how |
|----------|-------|-----------|
| `JinaReaderService::extract(string $url): array` | `app/Services/JinaReaderService.php` | Called by `ExtractNewsArticle` action |
| `ContentExtractorInterface` | `app/Contracts/ContentExtractorInterface.php` | Type-hint in ExtractNewsArticle |
| `markdownToTiptap()` | `AdminNewsController` (private) | Extract to `MarkdownToTiptapConverter` |
| `EnsureAdminUser` middleware | `app/Http/Middleware/` | Gate all admin pipeline routes |
| `EnsureNewsFeatureEnabled` | `app/Http/Middleware/` | Gate public + admin pipeline routes |
| `config('features.news')` | `config/features.php` | Already gates existing news routes |
| `NewsStatusEnum` | `app/Enums/NewsStatusEnum.php` | Keep as-is for old `News` model |

---

## Scope (Phase 1 MVP only)

Phase 2 (bulk import, retry UI, scheduling UI) and Phase 3 (RSS, duplicate detection) are **out of scope**.

---

## File Map

### New files

| Path | Responsibility |
|------|----------------|
| `app/Support/News/MarkdownToTiptapConverter.php` | Extracted from AdminNewsController private method |
| `app/Enums/NewsImportStatusEnum.php` | Pending/Fetching/Extracted/Generating/Ready/Failed |
| `app/Enums/NewsArticleStatusEnum.php` | Draft/Review/Approved/Scheduled/Published/Archived |
| `app/Enums/NewsLocaleEnum.php` | PtPt='pt-PT' / PtBr='pt-BR' |
| `database/migrations/*_create_news_imports_table.php` | Import tracking |
| `database/migrations/*_create_news_articles_table.php` | Editorial entity |
| `database/migrations/*_create_news_article_localizations_table.php` | Localized content |
| `app/Models/NewsImport.php` | hasOne NewsArticle |
| `app/Models/NewsArticle.php` | belongsTo NewsImport, hasMany NewsArticleLocalization |
| `app/Models/NewsArticleLocalization.php` | belongsTo NewsArticle |
| `database/factories/NewsImportFactory.php` | |
| `database/factories/NewsArticleFactory.php` | |
| `database/factories/NewsArticleLocalizationFactory.php` | |
| `app/Contracts/NewsGenerationServiceInterface.php` | Interface: `summarizeAndLocalize(array): array` |
| `app/Services/AnthropicNewsGenerationService.php` | Implements interface, calls Claude API via Http facade |
| `app/Actions/News/CreateNewsImport.php` | Validate URL, create import record |
| `app/Actions/News/ExtractNewsArticle.php` | Call JinaReaderService, update import |
| `app/Actions/News/GenerateLocalizedNewsContent.php` | Type-hints interface, create article + localizations |
| `app/Actions/News/PublishNewsArticle.php` | Set Published + published_at |
| `app/Actions/News/ScheduleNewsArticle.php` | Set Scheduled + scheduled_at |
| `app/Jobs/News/ImportNewsUrlJob.php` | Step 1: create import, dispatch extract |
| `app/Jobs/News/ExtractNewsArticleJob.php` | Step 2: extract, dispatch generate |
| `app/Jobs/News/GenerateNewsContentJob.php` | Step 3: generate localizations |
| `app/Jobs/News/PublishScheduledNewsJob.php` | Cron: publish past-due scheduled articles |
| `app/Http/Requests/Admin/News/StoreNewsImportRequest.php` | URL + SSRF validation |
| `app/Http/Requests/Admin/News/UpdateNewsArticleRequest.php` | Edit/save validation |
| `app/Http/Controllers/Admin/News/NewsImportController.php` | index, create, store, show |
| `app/Http/Controllers/Admin/News/NewsArticleController.php` | index, edit, update, publish, schedule, destroy |
| `app/Http/Controllers/NewsArticleController.php` | Public index + show (localized) |
| `resources/views/admin/news-imports/index.blade.php` | |
| `resources/views/admin/news-imports/create.blade.php` | |
| `resources/views/admin/news-articles/index.blade.php` | |
| `resources/views/admin/news-articles/edit.blade.php` | |
| `resources/views/news-articles/index.blade.php` | |
| `resources/views/news-articles/show.blade.php` | |
| `tests/Feature/News/NewsModelsTest.php` | |
| `tests/Feature/News/ExtractNewsArticleTest.php` | |
| `tests/Feature/News/GenerateLocalizedNewsContentTest.php` | |
| `tests/Feature/News/PublishNewsArticleTest.php` | |
| `tests/Feature/News/NewsJobsTest.php` | |
| `tests/Feature/News/NewsArticlePublicRoutesTest.php` | |
| `tests/Feature/News/NewsImportPipelineIntegrationTest.php` | |
| `tests/Feature/Admin/NewsImportControllerTest.php` | |
| `tests/Feature/Admin/NewsArticleControllerTest.php` | |

### Modified files

| Path | What changes |
|------|--------------|
| `config/services.php` | Add `anthropic` key block |
| `config/features.php` | Add `news_import_pipeline` flag |
| `.env.example` | Add `ANTHROPIC_API_KEY`, `ANTHROPIC_MODEL`, `FEATURE_NEWS_IMPORT_PIPELINE` |
| `routes/web.php` | Add `news-imports.*` and `news-articles.*` route groups inside admin middleware; add public localized routes |
| `routes/console.php` | Register `PublishScheduledNewsJob` on 5-minute schedule |
| `app/Http/Controllers/AdminNewsController.php` | Replace private `markdownToTiptap()` with injected `MarkdownToTiptapConverter` |
| `app/Providers/AppServiceProvider.php` | Bind `NewsGenerationServiceInterface` → `AnthropicNewsGenerationService` |

---

## Task 0 — Config + Extract MarkdownToTiptapConverter

**Purpose:** Groundwork only — no new features yet.

**Files:**
- Create: `app/Support/News/MarkdownToTiptapConverter.php`
- Modify: `config/services.php`
- Modify: `config/features.php`
- Modify: `.env.example`
- Modify: `app/Http/Controllers/AdminNewsController.php`

- [ ] **Step 1: Create `app/Support/News/MarkdownToTiptapConverter.php`**

```php
<?php

declare(strict_types=1);

namespace App\Support\News;

class MarkdownToTiptapConverter
{
    public function convert(string $markdown): array
    {
        $content = [];
        $lines = explode("\n", $markdown);
        $currentParagraph = '';

        foreach ($lines as $line) {
            $trimmedLine = trim($line);

            if (empty($trimmedLine)) {
                if (! empty($currentParagraph)) {
                    $content[] = [
                        'type'    => 'paragraph',
                        'content' => [['type' => 'text', 'text' => $currentParagraph]],
                    ];
                    $currentParagraph = '';
                }
                continue;
            }

            if (preg_match('/^#{1,6}\s+(.+)$/', $trimmedLine, $matches)) {
                if (! empty($currentParagraph)) {
                    $content[] = [
                        'type'    => 'paragraph',
                        'content' => [['type' => 'text', 'text' => $currentParagraph]],
                    ];
                    $currentParagraph = '';
                }
                $level    = strlen(preg_replace('/[^#]/', '', $trimmedLine));
                $content[] = [
                    'type'    => 'heading',
                    'attrs'   => ['level' => min($level, 6)],
                    'content' => [['type' => 'text', 'text' => $matches[1]]],
                ];
                continue;
            }

            if (! empty($currentParagraph)) {
                $currentParagraph .= ' ';
            }
            $currentParagraph .= $trimmedLine;
        }

        if (! empty($currentParagraph)) {
            $content[] = [
                'type'    => 'paragraph',
                'content' => [['type' => 'text', 'text' => $currentParagraph]],
            ];
        }

        return [
            'type'    => 'doc',
            'content' => $content ?: [['type' => 'paragraph', 'content' => []]],
        ];
    }
}
```

- [ ] **Step 2: Update `AdminNewsController::importFromUrl()` to inject and use `MarkdownToTiptapConverter`**

Change method signature:
```php
public function importFromUrl(Request $request, ContentExtractorInterface $extractor, MarkdownToTiptapConverter $converter): JsonResponse
```

Replace `$this->markdownToTiptap($data['content'] ?? '')` with `$converter->convert($data['content'] ?? '')`.

Delete the old `protected function markdownToTiptap(string $markdown): array` method entirely.

- [ ] **Step 3: Add to `config/services.php`**

```php
'anthropic' => [
    'api_key' => env('ANTHROPIC_API_KEY'),
    'model'   => env('ANTHROPIC_MODEL', 'claude-haiku-4-5-20251001'),
    'version' => '2023-06-01',
],
```

- [ ] **Step 4: Add to `config/features.php`**

```php
'news_import_pipeline' => env('FEATURE_NEWS_IMPORT_PIPELINE', false),
```

- [ ] **Step 5: Add to `.env.example`**

```
ANTHROPIC_API_KEY=
ANTHROPIC_MODEL=claude-haiku-4-5-20251001
FEATURE_NEWS_IMPORT_PIPELINE=false
```

- [ ] **Step 6: Verify existing tests pass**

```bash
php artisan test --compact
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 7: Commit**

```
refactor(news): extract MarkdownToTiptapConverter, add Anthropic config
```

---

## Task 1 — Enums

- [ ] **Step 1: Create `app/Enums/NewsImportStatusEnum.php`**

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum NewsImportStatusEnum: string
{
    case Pending    = 'pending';
    case Fetching   = 'fetching';
    case Extracted  = 'extracted';
    case Generating = 'generating';
    case Ready      = 'ready';
    case Failed     = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending    => 'Pending',
            self::Fetching   => 'Fetching',
            self::Extracted  => 'Extracted',
            self::Generating => 'Generating',
            self::Ready      => 'Ready',
            self::Failed     => 'Failed',
        };
    }

    public function colorClass(): string
    {
        return match ($this) {
            self::Pending    => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
            self::Fetching   => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
            self::Extracted  => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200',
            self::Generating => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
            self::Ready      => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
            self::Failed     => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
        };
    }

    public function isFinal(): bool
    {
        return match ($this) {
            self::Ready, self::Failed => true,
            default                   => false,
        };
    }
}
```

- [ ] **Step 2: Create `app/Enums/NewsArticleStatusEnum.php`**

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum NewsArticleStatusEnum: string
{
    case Draft     = 'draft';
    case Review    = 'review';
    case Approved  = 'approved';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Archived  = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft     => 'Draft',
            self::Review    => 'Review',
            self::Approved  => 'Approved',
            self::Scheduled => 'Scheduled',
            self::Published => 'Published',
            self::Archived  => 'Archived',
        };
    }

    public function colorClass(): string
    {
        return match ($this) {
            self::Draft     => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
            self::Review    => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
            self::Approved  => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
            self::Scheduled => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
            self::Published => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
            self::Archived  => 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
        };
    }

    public function isPubliclyVisible(): bool
    {
        return $this === self::Published;
    }
}
```

- [ ] **Step 3: Create `app/Enums/NewsLocaleEnum.php`**

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum NewsLocaleEnum: string
{
    case PtPt = 'pt-PT';
    case PtBr = 'pt-BR';

    public function label(): string
    {
        return match ($this) {
            self::PtPt => 'Português (Portugal)',
            self::PtBr => 'Português (Brasil)',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::PtPt => 'PT',
            self::PtBr => 'BR',
        };
    }

    public function slugPrefix(): string
    {
        return match ($this) {
            self::PtPt => 'pt-pt',
            self::PtBr => 'pt-br',
        };
    }
}
```

- [ ] **Step 4: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
```

```
feat(news): add NewsImportStatusEnum, NewsArticleStatusEnum, NewsLocaleEnum
```

---

## Task 2 — Migrations, Models, Factories

> **Critical:** Run each `make:migration` separately — same-second timestamps collide if chained.

- [ ] **Step 1: Create migrations one at a time**

```bash
php artisan make:migration create_news_imports_table --no-interaction
# wait for output, then:
php artisan make:migration create_news_articles_table --no-interaction
# wait, then:
php artisan make:migration create_news_article_localizations_table --no-interaction
```

- [ ] **Step 2: Fill `news_imports` migration `up()`**

```php
Schema::create('news_imports', function (Blueprint $table) {
    $table->id();
    $table->text('url');
    $table->text('canonical_url')->nullable();
    $table->string('source_domain')->nullable();
    $table->string('status')->default('pending');
    $table->text('failure_reason')->nullable();
    $table->string('raw_title')->nullable();
    $table->string('raw_author')->nullable();
    $table->timestamp('raw_published_at')->nullable();
    $table->longText('raw_body')->nullable();
    $table->text('raw_excerpt')->nullable();
    $table->text('raw_image_url')->nullable();
    $table->string('checksum')->nullable()->unique();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->timestamps();
});
```

- [ ] **Step 3: Fill `news_articles` migration `up()`**

```php
Schema::create('news_articles', function (Blueprint $table) {
    $table->id();
    $table->foreignId('news_import_id')->constrained('news_imports')->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('status')->default('draft');
    $table->string('source_name')->nullable();
    $table->text('source_url')->nullable();
    $table->text('original_title')->nullable();
    $table->string('original_language', 10)->default('en');
    $table->timestamp('original_published_at')->nullable();
    $table->text('featured_image_url')->nullable();
    $table->string('slug_pt_pt')->nullable()->unique();
    $table->string('slug_pt_br')->nullable()->unique();
    $table->timestamp('scheduled_at')->nullable();
    $table->timestamp('published_at')->nullable();
    $table->timestamps();
});
```

- [ ] **Step 4: Fill `news_article_localizations` migration `up()`**

```php
Schema::create('news_article_localizations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('news_article_id')->constrained('news_articles')->cascadeOnDelete();
    $table->string('locale', 10);
    $table->text('title');
    $table->text('summary_short')->nullable();
    $table->text('summary_medium')->nullable();
    $table->json('body')->nullable();
    $table->string('seo_title')->nullable();
    $table->text('seo_description')->nullable();
    $table->json('generation_metadata')->nullable();
    $table->timestamps();

    $table->unique(['news_article_id', 'locale']);
});
```

- [ ] **Step 5: Run migrations**

```bash
php artisan migrate --no-interaction
```

- [ ] **Step 6: Create models**

```bash
php artisan make:model NewsImport --factory --no-interaction
php artisan make:model NewsArticle --factory --no-interaction
php artisan make:model NewsArticleLocalization --factory --no-interaction
```

- [ ] **Step 7: Fill `app/Models/NewsImport.php`**

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NewsImportStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class NewsImport extends Model
{
    use HasFactory;

    protected $fillable = [
        'url', 'canonical_url', 'source_domain', 'status', 'failure_reason',
        'raw_title', 'raw_author', 'raw_published_at', 'raw_body',
        'raw_excerpt', 'raw_image_url', 'checksum', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'status'           => NewsImportStatusEnum::class,
            'raw_published_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function article(): HasOne
    {
        return $this->hasOne(NewsArticle::class);
    }

    public function isFailed(): bool
    {
        return $this->status === NewsImportStatusEnum::Failed;
    }

    public function isReady(): bool
    {
        return $this->status === NewsImportStatusEnum::Ready;
    }

    public function markAs(NewsImportStatusEnum $status, ?string $reason = null): void
    {
        $this->update(['status' => $status, 'failure_reason' => $reason]);
    }
}
```

- [ ] **Step 8: Fill `app/Models/NewsArticle.php`**

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NewsArticleStatusEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NewsArticle extends Model
{
    use HasFactory;

    protected $fillable = [
        'news_import_id', 'user_id', 'status', 'source_name', 'source_url',
        'original_title', 'original_language', 'original_published_at',
        'featured_image_url', 'slug_pt_pt', 'slug_pt_br', 'scheduled_at', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'status'                => NewsArticleStatusEnum::class,
            'original_published_at' => 'datetime',
            'scheduled_at'          => 'datetime',
            'published_at'          => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (NewsArticle $article) {
            if (empty($article->slug_pt_pt) && $article->original_title) {
                $article->slug_pt_pt = static::generateUniqueSlug($article->original_title, 'slug_pt_pt');
                $article->slug_pt_br = static::generateUniqueSlug($article->original_title, 'slug_pt_br');
            }
        });
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(NewsImport::class, 'news_import_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function localizations(): HasMany
    {
        return $this->hasMany(NewsArticleLocalization::class);
    }

    public function localization(string $locale): ?NewsArticleLocalization
    {
        return $this->localizations->firstWhere('locale', $locale);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', NewsArticleStatusEnum::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopeScheduledDue(Builder $query): Builder
    {
        return $query->where('status', NewsArticleStatusEnum::Scheduled)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now());
    }

    public function isPublished(): bool
    {
        return $this->status === NewsArticleStatusEnum::Published;
    }

    protected static function generateUniqueSlug(string $title, string $column): string
    {
        $slug = $base = str()->slug($title);
        $counter = 1;
        while (static::where($column, $slug)->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }
}
```

- [ ] **Step 9: Fill `app/Models/NewsArticleLocalization.php`**

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NewsLocaleEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsArticleLocalization extends Model
{
    use HasFactory;

    protected $fillable = [
        'news_article_id', 'locale', 'title', 'summary_short', 'summary_medium',
        'body', 'seo_title', 'seo_description', 'generation_metadata',
    ];

    protected function casts(): array
    {
        return [
            'locale'              => NewsLocaleEnum::class,
            'body'                => 'array',
            'generation_metadata' => 'array',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(NewsArticle::class, 'news_article_id');
    }
}
```

- [ ] **Step 10: Fill factories**

`database/factories/NewsImportFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Enums\NewsImportStatusEnum;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NewsImportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'url'     => fake()->url(),
            'status'  => NewsImportStatusEnum::Pending,
            'user_id' => User::factory(),
        ];
    }

    public function ready(): static
    {
        return $this->state(['status' => NewsImportStatusEnum::Ready]);
    }

    public function failed(): static
    {
        return $this->state([
            'status'         => NewsImportStatusEnum::Failed,
            'failure_reason' => 'Connection timeout',
        ]);
    }

    public function extracted(): static
    {
        return $this->state([
            'status'    => NewsImportStatusEnum::Extracted,
            'raw_title' => fake()->sentence(6),
            'raw_body'  => fake()->paragraphs(3, true),
        ]);
    }
}
```

`database/factories/NewsArticleFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Enums\NewsArticleStatusEnum;
use App\Models\NewsImport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NewsArticleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'news_import_id'     => NewsImport::factory(),
            'user_id'            => User::factory(),
            'status'             => NewsArticleStatusEnum::Review,
            'source_name'        => fake()->randomElement(['IGN', 'Kotaku', 'Polygon']),
            'source_url'         => fake()->url(),
            'original_title'     => fake()->sentence(8),
            'original_language'  => 'en',
            'featured_image_url' => fake()->imageUrl(),
        ];
    }

    public function published(): static
    {
        return $this->state([
            'status'       => NewsArticleStatusEnum::Published,
            'published_at' => now(),
        ]);
    }

    public function scheduled(): static
    {
        return $this->state([
            'status'       => NewsArticleStatusEnum::Scheduled,
            'scheduled_at' => now()->addHours(2),
        ]);
    }
}
```

`database/factories/NewsArticleLocalizationFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Enums\NewsLocaleEnum;
use App\Models\NewsArticle;
use Illuminate\Database\Eloquent\Factories\Factory;

class NewsArticleLocalizationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'news_article_id' => NewsArticle::factory(),
            'locale'          => NewsLocaleEnum::PtPt,
            'title'           => fake()->sentence(8),
            'summary_short'   => fake()->sentence(20),
            'summary_medium'  => fake()->paragraph(3),
            'body'            => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => fake()->paragraph()]]]]],
            'seo_title'       => fake()->sentence(6),
            'seo_description' => fake()->sentence(20),
        ];
    }

    public function ptBr(): static
    {
        return $this->state(['locale' => NewsLocaleEnum::PtBr]);
    }
}
```

- [ ] **Step 11: Write + run model tests**

```bash
php artisan make:test --pest Feature/News/NewsModelsTest --no-interaction
```

`tests/Feature/News/NewsModelsTest.php`:
```php
<?php

use App\Enums\NewsArticleStatusEnum;
use App\Enums\NewsImportStatusEnum;
use App\Enums\NewsLocaleEnum;
use App\Models\NewsArticle;
use App\Models\NewsArticleLocalization;
use App\Models\NewsImport;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('news_import belongs to user and has one article', function () {
    $import  = NewsImport::factory()->ready()->create();
    $article = NewsArticle::factory()->create(['news_import_id' => $import->id]);

    expect($import->article->id)->toBe($article->id);
    expect($import->user)->not->toBeNull();
});

it('news_article has many localizations', function () {
    $article = NewsArticle::factory()->create();
    NewsArticleLocalization::factory()->create(['news_article_id' => $article->id, 'locale' => NewsLocaleEnum::PtPt]);
    NewsArticleLocalization::factory()->ptBr()->create(['news_article_id' => $article->id]);

    expect($article->localizations)->toHaveCount(2);
    expect($article->localization('pt-PT'))->not->toBeNull();
    expect($article->localization('pt-BR'))->not->toBeNull();
});

it('markAs updates status and failure_reason', function () {
    $import = NewsImport::factory()->create();
    $import->markAs(NewsImportStatusEnum::Failed, 'Timeout');

    expect($import->fresh()->status)->toBe(NewsImportStatusEnum::Failed);
    expect($import->fresh()->failure_reason)->toBe('Timeout');
});

it('scopeScheduledDue returns only past-due scheduled articles', function () {
    $due    = NewsArticle::factory()->scheduled()->create(['scheduled_at' => now()->subMinute()]);
    $future = NewsArticle::factory()->scheduled()->create(['scheduled_at' => now()->addHour()]);

    $results = NewsArticle::scheduledDue()->get();

    expect($results->contains($due))->toBeTrue();
    expect($results->contains($future))->toBeFalse();
});

it('auto-generates unique slugs on create', function () {
    $a = NewsArticle::factory()->create(['original_title' => 'Test Game Released', 'slug_pt_pt' => null, 'slug_pt_br' => null]);
    $b = NewsArticle::factory()->create(['original_title' => 'Test Game Released', 'slug_pt_pt' => null, 'slug_pt_br' => null]);

    expect($a->slug_pt_pt)->not->toBe($b->slug_pt_pt);
});
```

```bash
php artisan test --compact tests/Feature/News/NewsModelsTest.php
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 12: Commit**

```
feat(news): add migrations, models, and factories for import pipeline tables
```

---

## Task 3 — NewsGenerationServiceInterface + AnthropicNewsGenerationService

- [ ] **Step 1: Create `app/Contracts/NewsGenerationServiceInterface.php`**

```php
<?php

declare(strict_types=1);

namespace App\Contracts;

interface NewsGenerationServiceInterface
{
    /**
     * Summarise and localise article content into pt-PT and pt-BR.
     *
     * @param  array{title: string, content: string, source: string}  $article
     * @return array<string, array{title: string, summary_short: string, summary_medium: string, body: array, seo_title: string, seo_description: string}>
     */
    public function summarizeAndLocalize(array $article): array;
}
```

- [ ] **Step 2: Bind in `app/Providers/AppServiceProvider.php`**

Add to `register()`:

```php
use App\Contracts\NewsGenerationServiceInterface;
use App\Services\AnthropicNewsGenerationService;
use App\Support\News\MarkdownToTiptapConverter;

$this->app->bind(NewsGenerationServiceInterface::class, function () {
    return new AnthropicNewsGenerationService(
        new MarkdownToTiptapConverter()
    );
});
```

Swapping providers later = change one line here. E.g. swap `AnthropicNewsGenerationService` for `OpenAiNewsGenerationService`.

- [ ] **Step 4: Write failing test**

```bash
php artisan make:test --pest Feature/News/GenerateLocalizedNewsContentTest --no-interaction
```

`tests/Feature/News/GenerateLocalizedNewsContentTest.php`:
```php
<?php

use App\Contracts\NewsGenerationServiceInterface;
use App\Services\AnthropicNewsGenerationService;
use App\Support\News\MarkdownToTiptapConverter;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.anthropic.api_key' => 'test-key',
        'services.anthropic.model'   => 'claude-haiku-4-5-20251001',
        'services.anthropic.version' => '2023-06-01',
    ]);
});

it('calls Anthropic and returns both locales with converted body', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [[
                'type' => 'text',
                'text' => json_encode([
                    'pt-PT' => [
                        'title'           => 'Jogo PT',
                        'summary_short'   => 'Curto PT.',
                        'summary_medium'  => 'Médio PT.',
                        'body_markdown'   => "## Visão\n\nTexto PT.",
                        'seo_title'       => 'SEO PT',
                        'seo_description' => 'Desc PT.',
                    ],
                    'pt-BR' => [
                        'title'           => 'Jogo BR',
                        'summary_short'   => 'Curto BR.',
                        'summary_medium'  => 'Médio BR.',
                        'body_markdown'   => "## Visão\n\nTexto BR.",
                        'seo_title'       => 'SEO BR',
                        'seo_description' => 'Desc BR.',
                    ],
                ]),
            ]],
        ], 200),
    ]);

    $service = new AnthropicNewsGenerationService(new MarkdownToTiptapConverter());
    $result  = $service->summarizeAndLocalize(['title' => 'Game', 'content' => '## Overview\n\nContent.', 'source' => 'IGN']);

    expect($result)->toHaveKeys(['pt-PT', 'pt-BR']);
    expect($result['pt-PT']['title'])->toBe('Jogo PT');
    expect($result['pt-PT']['body'])->toBeArray();
    expect($result['pt-PT']['body']['type'])->toBe('doc');
});

it('throws RuntimeException on Anthropic API failure', function () {
    Http::fake(['api.anthropic.com/*' => Http::response(['error' => ['message' => 'Unauthorized']], 401)]);

    $service = new AnthropicNewsGenerationService(new MarkdownToTiptapConverter());

    expect(fn () => $service->summarizeAndLocalize(['title' => 'T', 'content' => 'C', 'source' => 'S']))
        ->toThrow(RuntimeException::class);
});
```

- [ ] **Step 5: Run — expect FAIL (class missing)**

```bash
php artisan test --compact tests/Feature/News/GenerateLocalizedNewsContentTest.php
```

- [ ] **Step 6: Create `app/Services/AnthropicNewsGenerationService.php`** — implements the interface

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\NewsGenerationServiceInterface;
use App\Support\News\MarkdownToTiptapConverter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AnthropicNewsGenerationService implements NewsGenerationServiceInterface
{
    public function __construct(
        private readonly MarkdownToTiptapConverter $converter
    ) {}

    /**
     * @param  array{title: string, content: string, source: string}  $article
     * @return array<string, array{title: string, summary_short: string, summary_medium: string, body: array, seo_title: string, seo_description: string}>
     */
    public function summarizeAndLocalize(array $article): array
    {
        $response = Http::withHeaders([
            'x-api-key'         => config('services.anthropic.api_key'),
            'anthropic-version' => config('services.anthropic.version'),
            'content-type'      => 'application/json',
        ])->timeout(60)->post('https://api.anthropic.com/v1/messages', [
            'model'      => config('services.anthropic.model'),
            'max_tokens' => 4096,
            'messages'   => [
                ['role' => 'user', 'content' => $this->buildPrompt($article)],
            ],
        ]);

        if ($response->failed()) {
            Log::error('Anthropic API failed', ['status' => $response->status(), 'body' => $response->body()]);
            throw new RuntimeException('AI generation failed. Status: '.$response->status());
        }

        $raw  = $response->json('content.0.text', '');
        $data = json_decode($raw, true);

        if (! is_array($data) || ! isset($data['pt-PT'], $data['pt-BR'])) {
            Log::error('Anthropic unexpected response format', ['raw' => $raw]);
            throw new RuntimeException('AI returned unexpected response format.');
        }

        return [
            'pt-PT' => $this->processLocale($data['pt-PT']),
            'pt-BR' => $this->processLocale($data['pt-BR']),
        ];
    }

    private function processLocale(array $locale): array
    {
        return [
            'title'           => $locale['title'] ?? '',
            'summary_short'   => $locale['summary_short'] ?? '',
            'summary_medium'  => $locale['summary_medium'] ?? '',
            'body'            => $this->converter->convert($locale['body_markdown'] ?? ''),
            'seo_title'       => $locale['seo_title'] ?? '',
            'seo_description' => $locale['seo_description'] ?? '',
        ];
    }

    private function buildPrompt(array $article): string
    {
        $title   = $article['title'];
        $content = $article['content'];
        $source  = $article['source'];

        return <<<PROMPT
        You are a professional gaming news editor. Translate and summarise the following article into both pt-PT (European Portuguese) and pt-BR (Brazilian Portuguese).

        Source: {$source}
        Title: {$title}

        Content:
        {$content}

        Return ONLY a valid JSON object with this exact structure (no markdown, no explanation):
        {
          "pt-PT": {
            "title": "translated title in pt-PT",
            "summary_short": "1-2 sentence summary in pt-PT (max 160 chars)",
            "summary_medium": "3-4 sentence summary in pt-PT (max 400 chars)",
            "body_markdown": "full article body translated to pt-PT in Markdown",
            "seo_title": "SEO-optimised title in pt-PT (max 70 chars)",
            "seo_description": "SEO meta description in pt-PT (max 160 chars)"
          },
          "pt-BR": {
            "title": "translated title in pt-BR",
            "summary_short": "1-2 sentence summary in pt-BR (max 160 chars)",
            "summary_medium": "3-4 sentence summary in pt-BR (max 400 chars)",
            "body_markdown": "full article body translated to pt-BR in Markdown",
            "seo_title": "SEO-optimised title in pt-BR (max 70 chars)",
            "seo_description": "SEO meta description in pt-BR (max 160 chars)"
          }
        }
        PROMPT;
    }
}
```

- [ ] **Step 7: Run — expect PASS**

```bash
php artisan test --compact tests/Feature/News/GenerateLocalizedNewsContentTest.php
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 8: Commit**

```
feat(news): add NewsGenerationServiceInterface and AnthropicNewsGenerationService
```

> **Adding another provider later:** Create `app/Services/OpenAiNewsGenerationService.php` implementing `NewsGenerationServiceInterface`. Change the `AppServiceProvider` binding. Zero other changes required.

---

## Task 4 — Actions

- [ ] **Step 1: Write failing test for ExtractNewsArticle**

```bash
php artisan make:test --pest Feature/News/ExtractNewsArticleTest --no-interaction
```

`tests/Feature/News/ExtractNewsArticleTest.php`:
```php
<?php

use App\Actions\News\ExtractNewsArticle;
use App\Contracts\ContentExtractorInterface;
use App\Enums\NewsImportStatusEnum;
use App\Models\NewsImport;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('updates import with raw content on success', function () {
    $import    = NewsImport::factory()->create(['status' => NewsImportStatusEnum::Pending]);
    $extractor = mock(ContentExtractorInterface::class)
        ->expect(extract: fn () => [
            'title'       => 'Test Article',
            'content'     => "## Overview\n\nSome content.",
            'image'       => 'https://cdn.example.com/img.jpg',
            'summary'     => 'Short summary.',
            'source_name' => 'IGN',
        ]);

    (new ExtractNewsArticle($extractor))->handle($import);

    $import->refresh();
    expect($import->status)->toBe(NewsImportStatusEnum::Extracted);
    expect($import->raw_title)->toBe('Test Article');
    expect($import->raw_image_url)->toBe('https://cdn.example.com/img.jpg');
});

it('marks import as failed when extractor throws', function () {
    $import    = NewsImport::factory()->create();
    $extractor = mock(ContentExtractorInterface::class)
        ->expect(extract: fn () => throw new RuntimeException('Domain blocked'));

    (new ExtractNewsArticle($extractor))->handle($import);

    expect($import->fresh()->status)->toBe(NewsImportStatusEnum::Failed);
    expect($import->fresh()->failure_reason)->toContain('Domain blocked');
});
```

- [ ] **Step 2: Run — expect FAIL**

```bash
php artisan test --compact tests/Feature/News/ExtractNewsArticleTest.php
```

- [ ] **Step 3: Create action stubs**

```bash
php artisan make:class Actions/News/CreateNewsImport --no-interaction
php artisan make:class Actions/News/ExtractNewsArticle --no-interaction
php artisan make:class Actions/News/GenerateLocalizedNewsContent --no-interaction
php artisan make:class Actions/News/PublishNewsArticle --no-interaction
php artisan make:class Actions/News/ScheduleNewsArticle --no-interaction
```

- [ ] **Step 4: Fill `app/Actions/News/CreateNewsImport.php`**

```php
<?php

declare(strict_types=1);

namespace App\Actions\News;

use App\Enums\NewsImportStatusEnum;
use App\Models\NewsImport;

class CreateNewsImport
{
    public function handle(string $url, int $userId): NewsImport
    {
        $host = parse_url($url, PHP_URL_HOST);

        return NewsImport::create([
            'url'           => $url,
            'source_domain' => $host ? preg_replace('/^www\./', '', $host) : null,
            'status'        => NewsImportStatusEnum::Pending,
            'user_id'       => $userId,
        ]);
    }
}
```

- [ ] **Step 5: Fill `app/Actions/News/ExtractNewsArticle.php`**

```php
<?php

declare(strict_types=1);

namespace App\Actions\News;

use App\Contracts\ContentExtractorInterface;
use App\Enums\NewsImportStatusEnum;
use App\Models\NewsImport;
use Illuminate\Support\Facades\Log;

class ExtractNewsArticle
{
    public function __construct(
        private readonly ContentExtractorInterface $extractor
    ) {}

    public function handle(NewsImport $import): void
    {
        $import->markAs(NewsImportStatusEnum::Fetching);

        try {
            $data = $this->extractor->extract($import->url);

            $import->update([
                'status'        => NewsImportStatusEnum::Extracted,
                'raw_title'     => $data['title'],
                'raw_body'      => $data['content'],
                'raw_excerpt'   => $data['summary'],
                'raw_image_url' => $data['image'],
            ]);
        } catch (\Throwable $e) {
            Log::warning('News extraction failed', ['url' => $import->url, 'error' => $e->getMessage()]);
            $import->markAs(NewsImportStatusEnum::Failed, $e->getMessage());
        }
    }
}
```

- [ ] **Step 6: Fill `app/Actions/News/GenerateLocalizedNewsContent.php`**

```php
<?php

declare(strict_types=1);

namespace App\Actions\News;

use App\Contracts\NewsGenerationServiceInterface;
use App\Enums\NewsArticleStatusEnum;
use App\Enums\NewsImportStatusEnum;
use App\Enums\NewsLocaleEnum;
use App\Models\NewsArticle;
use App\Models\NewsImport;
use Illuminate\Support\Facades\Log;

class GenerateLocalizedNewsContent
{
    public function __construct(
        private readonly NewsGenerationServiceInterface $ai
    ) {}

    public function handle(NewsImport $import): NewsArticle
    {
        $import->markAs(NewsImportStatusEnum::Generating);

        try {
            $localized = $this->ai->summarizeAndLocalize([
                'title'   => $import->raw_title ?? '',
                'content' => $import->raw_body ?? '',
                'source'  => $import->source_domain ?? 'Unknown',
            ]);

            $article = NewsArticle::create([
                'news_import_id'     => $import->id,
                'user_id'            => $import->user_id,
                'status'             => NewsArticleStatusEnum::Review,
                'source_name'        => $import->source_domain,
                'source_url'         => $import->url,
                'original_title'     => $import->raw_title,
                'original_language'  => 'en',
                'featured_image_url' => $import->raw_image_url,
            ]);

            foreach (NewsLocaleEnum::cases() as $locale) {
                $data = $localized[$locale->value] ?? null;
                if (! $data) {
                    continue;
                }

                $article->localizations()->create([
                    'locale'              => $locale->value,
                    'title'               => $data['title'],
                    'summary_short'       => $data['summary_short'],
                    'summary_medium'      => $data['summary_medium'],
                    'body'                => $data['body'],
                    'seo_title'           => $data['seo_title'],
                    'seo_description'     => $data['seo_description'],
                    'generation_metadata' => ['model' => config('services.anthropic.model')],
                ]);
            }

            $import->markAs(NewsImportStatusEnum::Ready);

            return $article;
        } catch (\Throwable $e) {
            Log::error('News generation failed', ['import_id' => $import->id, 'error' => $e->getMessage()]);
            $import->markAs(NewsImportStatusEnum::Failed, $e->getMessage());
            throw $e;
        }
    }
}
```

- [ ] **Step 7: Fill `app/Actions/News/PublishNewsArticle.php`**

```php
<?php

declare(strict_types=1);

namespace App\Actions\News;

use App\Enums\NewsArticleStatusEnum;
use App\Models\NewsArticle;

class PublishNewsArticle
{
    public function handle(NewsArticle $article): void
    {
        $article->update([
            'status'       => NewsArticleStatusEnum::Published,
            'published_at' => $article->published_at ?? now(),
            'scheduled_at' => null,
        ]);
    }
}
```

- [ ] **Step 8: Fill `app/Actions/News/ScheduleNewsArticle.php`**

```php
<?php

declare(strict_types=1);

namespace App\Actions\News;

use App\Enums\NewsArticleStatusEnum;
use App\Models\NewsArticle;
use Carbon\Carbon;

class ScheduleNewsArticle
{
    public function handle(NewsArticle $article, Carbon $scheduledAt): void
    {
        $article->update([
            'status'       => NewsArticleStatusEnum::Scheduled,
            'scheduled_at' => $scheduledAt,
        ]);
    }
}
```

- [ ] **Step 9: Run extraction + publish tests**

```bash
php artisan make:test --pest Feature/News/PublishNewsArticleTest --no-interaction
```

`tests/Feature/News/PublishNewsArticleTest.php`:
```php
<?php

use App\Actions\News\PublishNewsArticle;
use App\Enums\NewsArticleStatusEnum;
use App\Models\NewsArticle;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('sets status Published and stamps published_at', function () {
    $article = NewsArticle::factory()->create(['status' => NewsArticleStatusEnum::Review]);

    (new PublishNewsArticle())->handle($article);

    expect($article->fresh()->status)->toBe(NewsArticleStatusEnum::Published);
    expect($article->fresh()->published_at)->not->toBeNull();
});

it('preserves existing published_at', function () {
    $past    = now()->subDay();
    $article = NewsArticle::factory()->create(['status' => NewsArticleStatusEnum::Review, 'published_at' => $past]);

    (new PublishNewsArticle())->handle($article);

    expect($article->fresh()->published_at->toDateString())->toBe($past->toDateString());
});
```

```bash
php artisan test --compact tests/Feature/News/ExtractNewsArticleTest.php tests/Feature/News/PublishNewsArticleTest.php
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 10: Commit**

```
feat(news): add Actions for import pipeline (create, extract, generate, publish, schedule)
```

---

## Task 5 — Queue Jobs

- [ ] **Step 1: Create stubs**

```bash
php artisan make:job News/ImportNewsUrlJob --no-interaction
php artisan make:job News/ExtractNewsArticleJob --no-interaction
php artisan make:job News/GenerateNewsContentJob --no-interaction
php artisan make:job News/PublishScheduledNewsJob --no-interaction
```

- [ ] **Step 2: Fill `app/Jobs/News/ImportNewsUrlJob.php`**

```php
<?php

declare(strict_types=1);

namespace App\Jobs\News;

use App\Actions\News\CreateNewsImport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ImportNewsUrlJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly string $url,
        public readonly int $userId
    ) {}

    public function handle(CreateNewsImport $action): void
    {
        $import = $action->handle($this->url, $this->userId);

        ExtractNewsArticleJob::dispatch($import);
    }
}
```

- [ ] **Step 3: Fill `app/Jobs/News/ExtractNewsArticleJob.php`**

```php
<?php

declare(strict_types=1);

namespace App\Jobs\News;

use App\Actions\News\ExtractNewsArticle;
use App\Enums\NewsImportStatusEnum;
use App\Models\NewsImport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExtractNewsArticleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly NewsImport $import) {}

    public function handle(ExtractNewsArticle $action): void
    {
        $action->handle($this->import);

        if ($this->import->fresh()->status === NewsImportStatusEnum::Extracted) {
            GenerateNewsContentJob::dispatch($this->import);
        }
    }
}
```

- [ ] **Step 4: Fill `app/Jobs/News/GenerateNewsContentJob.php`**

```php
<?php

declare(strict_types=1);

namespace App\Jobs\News;

use App\Actions\News\GenerateLocalizedNewsContent;
use App\Models\NewsImport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateNewsContentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 120;

    public function __construct(public readonly NewsImport $import) {}

    public function handle(GenerateLocalizedNewsContent $action): void
    {
        $action->handle($this->import);
    }
}
```

- [ ] **Step 5: Fill `app/Jobs/News/PublishScheduledNewsJob.php`**

```php
<?php

declare(strict_types=1);

namespace App\Jobs\News;

use App\Actions\News\PublishNewsArticle;
use App\Models\NewsArticle;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PublishScheduledNewsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(PublishNewsArticle $action): void
    {
        NewsArticle::scheduledDue()
            ->get()
            ->each(fn (NewsArticle $article) => $action->handle($article));
    }
}
```

- [ ] **Step 6: Register cron in `routes/console.php`**

```php
use App\Jobs\News\PublishScheduledNewsJob;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new PublishScheduledNewsJob())
    ->everyFiveMinutes()
    ->name('news-publish-scheduled')
    ->withoutOverlapping()
    ->onOneServer();
```

- [ ] **Step 7: Write + run job tests**

```bash
php artisan make:test --pest Feature/News/NewsJobsTest --no-interaction
```

`tests/Feature/News/NewsJobsTest.php`:
```php
<?php

use App\Jobs\News\ExtractNewsArticleJob;
use App\Jobs\News\GenerateNewsContentJob;
use App\Jobs\News\ImportNewsUrlJob;
use App\Models\NewsImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('ImportNewsUrlJob creates import and dispatches ExtractNewsArticleJob', function () {
    Queue::fake([ExtractNewsArticleJob::class]);
    $user = User::factory()->create(['is_admin' => true]);

    dispatch(new ImportNewsUrlJob('https://ign.com/articles/test', $user->id));

    Queue::assertPushed(ExtractNewsArticleJob::class);
    $this->assertDatabaseHas('news_imports', ['url' => 'https://ign.com/articles/test']);
});

it('ExtractNewsArticleJob dispatches GenerateNewsContentJob when extracted', function () {
    Queue::fake([GenerateNewsContentJob::class]);
    $import = NewsImport::factory()->create();

    $mock = mock(\App\Actions\News\ExtractNewsArticle::class)
        ->expect(handle: function (NewsImport $imp) {
            $imp->update(['status' => \App\Enums\NewsImportStatusEnum::Extracted]);
        });
    app()->instance(\App\Actions\News\ExtractNewsArticle::class, $mock);

    dispatch(new ExtractNewsArticleJob($import));

    Queue::assertPushed(GenerateNewsContentJob::class);
});
```

```bash
php artisan test --compact tests/Feature/News/NewsJobsTest.php
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 8: Commit**

```
feat(news): add queue jobs for import pipeline and PublishScheduledNewsJob cron
```

---

## Task 6 — Form Requests

- [ ] **Step 1: Create stubs**

```bash
php artisan make:request Admin/News/StoreNewsImportRequest --no-interaction
php artisan make:request Admin/News/UpdateNewsArticleRequest --no-interaction
```

- [ ] **Step 2: Fill `StoreNewsImportRequest.php`**

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\News;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreNewsImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'url' => ['required', 'url', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $url  = $this->input('url', '');
            $host = parse_url($url, PHP_URL_HOST);

            if (! $host) {
                $v->errors()->add('url', 'Could not parse the URL host.');
                return;
            }

            $ip = gethostbyname($host);

            if (filter_var($ip, FILTER_VALIDATE_IP) &&
                ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                $v->errors()->add('url', 'The URL resolves to a private or reserved IP address.');
            }
        });
    }
}
```

- [ ] **Step 3: Fill `UpdateNewsArticleRequest.php`**

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\News;

use App\Enums\NewsArticleStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateNewsArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'featured_image_url'              => ['nullable', 'url', 'max:2000'],
            'scheduled_at'                    => ['nullable', 'date', 'after:now'],
            'localizations'                   => ['required', 'array', 'min:1'],
            'localizations.*.locale'          => ['required', 'string'],
            'localizations.*.title'           => ['required', 'string', 'max:255'],
            'localizations.*.summary_short'   => ['nullable', 'string', 'max:160'],
            'localizations.*.summary_medium'  => ['nullable', 'string', 'max:400'],
            'localizations.*.body'            => ['nullable', 'array'],
            'localizations.*.seo_title'       => ['nullable', 'string', 'max:70'],
            'localizations.*.seo_description' => ['nullable', 'string', 'max:160'],
        ];
    }
}
```

- [ ] **Step 4: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
```

```
feat(news): add form requests with SSRF protection for import pipeline
```

---

## Task 7 — Admin Controllers & Routes

- [ ] **Step 1: Create stubs**

```bash
php artisan make:controller Admin/News/NewsImportController --no-interaction
php artisan make:controller Admin/News/NewsArticleController --no-interaction
```

- [ ] **Step 2: Fill `app/Http/Controllers/Admin/News/NewsImportController.php`**

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\News;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\News\StoreNewsImportRequest;
use App\Jobs\News\ImportNewsUrlJob;
use App\Models\NewsImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class NewsImportController extends Controller
{
    public function index(): View
    {
        $imports = NewsImport::with('user', 'article')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('admin.news-imports.index', compact('imports'));
    }

    public function create(): View
    {
        $recentImports = NewsImport::with('user')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return view('admin.news-imports.create', compact('recentImports'));
    }

    public function store(StoreNewsImportRequest $request): RedirectResponse
    {
        ImportNewsUrlJob::dispatch($request->validated('url'), auth()->id());

        return redirect()->route('admin.news-imports.index')
            ->with('success', 'Import queued. It will be processed shortly.');
    }

    public function show(NewsImport $newsImport): View
    {
        $newsImport->load('user', 'article.localizations');

        return view('admin.news-imports.show', ['import' => $newsImport]);
    }
}
```

- [ ] **Step 3: Fill `app/Http/Controllers/Admin/News/NewsArticleController.php`**

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\News;

use App\Actions\News\PublishNewsArticle;
use App\Actions\News\ScheduleNewsArticle;
use App\Enums\NewsArticleStatusEnum;
use App\Enums\NewsLocaleEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\News\UpdateNewsArticleRequest;
use App\Models\NewsArticle;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NewsArticleController extends Controller
{
    public function index(Request $request): View
    {
        $query = NewsArticle::with(['import', 'localizations', 'author'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('source')) {
            $query->where('source_name', $request->input('source'));
        }

        $articles = $query->paginate(20)->withQueryString();
        $statuses = NewsArticleStatusEnum::cases();

        return view('admin.news-articles.index', compact('articles', 'statuses'));
    }

    public function edit(NewsArticle $newsArticle): View
    {
        $newsArticle->load('localizations', 'import');
        $locales = NewsLocaleEnum::cases();

        return view('admin.news-articles.edit', [
            'article' => $newsArticle,
            'locales' => $locales,
        ]);
    }

    public function update(UpdateNewsArticleRequest $request, NewsArticle $newsArticle): RedirectResponse
    {
        $data = $request->validated();

        $newsArticle->update([
            'featured_image_url' => $data['featured_image_url'] ?? $newsArticle->featured_image_url,
        ]);

        foreach ($data['localizations'] as $locData) {
            $newsArticle->localizations()->updateOrCreate(
                ['locale' => $locData['locale']],
                [
                    'title'           => $locData['title'],
                    'summary_short'   => $locData['summary_short'] ?? null,
                    'summary_medium'  => $locData['summary_medium'] ?? null,
                    'body'            => $locData['body'] ?? null,
                    'seo_title'       => $locData['seo_title'] ?? null,
                    'seo_description' => $locData['seo_description'] ?? null,
                ]
            );
        }

        return redirect()->route('admin.news-articles.edit', $newsArticle)
            ->with('success', 'Article saved.');
    }

    public function publish(NewsArticle $newsArticle, PublishNewsArticle $action): RedirectResponse
    {
        $action->handle($newsArticle);

        return redirect()->route('admin.news-articles.edit', $newsArticle)
            ->with('success', 'Article published.');
    }

    public function schedule(Request $request, NewsArticle $newsArticle, ScheduleNewsArticle $action): RedirectResponse
    {
        $request->validate(['scheduled_at' => ['required', 'date', 'after:now']]);

        $action->handle($newsArticle, Carbon::parse($request->input('scheduled_at')));

        return redirect()->route('admin.news-articles.edit', $newsArticle)
            ->with('success', 'Article scheduled.');
    }

    public function destroy(NewsArticle $newsArticle): RedirectResponse
    {
        $newsArticle->delete();

        return redirect()->route('admin.news-articles.index')
            ->with('success', 'Article deleted.');
    }
}
```

- [ ] **Step 4: Add routes to `routes/web.php`** — inside existing admin middleware group, after the existing `news.` group:

```php
use App\Http\Controllers\Admin\News\NewsArticleController;
use App\Http\Controllers\Admin\News\NewsImportController;

// News import pipeline
Route::middleware([EnsureNewsFeatureEnabled::class])
    ->prefix('news-imports')
    ->name('news-imports.')
    ->group(function () {
        Route::get('/', [NewsImportController::class, 'index'])->name('index');
        Route::get('/create', [NewsImportController::class, 'create'])->name('create');
        Route::post('/', [NewsImportController::class, 'store'])->name('store');
        Route::get('/{newsImport}', [NewsImportController::class, 'show'])->name('show');
    });

// News articles (pipeline output)
Route::middleware([EnsureNewsFeatureEnabled::class])
    ->prefix('news-articles')
    ->name('news-articles.')
    ->group(function () {
        Route::get('/', [NewsArticleController::class, 'index'])->name('index');
        Route::get('/{newsArticle}/edit', [NewsArticleController::class, 'edit'])->name('edit');
        Route::patch('/{newsArticle}', [NewsArticleController::class, 'update'])->name('update');
        Route::post('/{newsArticle}/publish', [NewsArticleController::class, 'publish'])->name('publish');
        Route::post('/{newsArticle}/schedule', [NewsArticleController::class, 'schedule'])->name('schedule');
        Route::delete('/{newsArticle}', [NewsArticleController::class, 'destroy'])->name('destroy');
    });
```

- [ ] **Step 5: Write + run controller tests**

```bash
php artisan make:test --pest Feature/Admin/NewsImportControllerTest --no-interaction
php artisan make:test --pest Feature/Admin/NewsArticleControllerTest --no-interaction
```

`tests/Feature/Admin/NewsImportControllerTest.php`:
```php
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

it('admin can queue a valid URL import', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->post(route('admin.news-imports.store'), ['url' => 'https://ign.com/articles/test'])
        ->assertRedirect(route('admin.news-imports.index'));

    Queue::assertPushed(ImportNewsUrlJob::class, fn ($job) => $job->url === 'https://ign.com/articles/test');
});

it('rejects invalid URL', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->post(route('admin.news-imports.store'), ['url' => 'not-a-url'])
        ->assertSessionHasErrors('url');
});

it('shows import list to admin', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    NewsImport::factory()->count(3)->create(['user_id' => $admin->id]);

    $this->actingAs($admin)->get(route('admin.news-imports.index'))->assertOk();
});
```

`tests/Feature/Admin/NewsArticleControllerTest.php`:
```php
<?php

use App\Enums\NewsArticleStatusEnum;
use App\Models\NewsArticle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['features.news' => true]);
});

it('admin can view articles list', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    NewsArticle::factory()->count(2)->create();

    $this->actingAs($admin)->get(route('admin.news-articles.index'))->assertOk();
});

it('admin can update article localizations', function () {
    $admin   = User::factory()->create(['is_admin' => true]);
    $article = NewsArticle::factory()->create();

    $payload = [
        'localizations' => [
            ['locale' => 'pt-PT', 'title' => 'Título PT', 'summary_short' => 'Resumo.', 'summary_medium' => 'Médio.'],
            ['locale' => 'pt-BR', 'title' => 'Título BR', 'summary_short' => 'Resumo.', 'summary_medium' => 'Médio.'],
        ],
    ];

    $this->actingAs($admin)
        ->patch(route('admin.news-articles.update', $article), $payload)
        ->assertRedirect(route('admin.news-articles.edit', $article));

    $this->assertDatabaseHas('news_article_localizations', [
        'news_article_id' => $article->id,
        'locale'          => 'pt-PT',
        'title'           => 'Título PT',
    ]);
});

it('admin can publish an article', function () {
    $admin   = User::factory()->create(['is_admin' => true]);
    $article = NewsArticle::factory()->create(['status' => NewsArticleStatusEnum::Review]);

    $this->actingAs($admin)
        ->post(route('admin.news-articles.publish', $article))
        ->assertRedirect(route('admin.news-articles.edit', $article));

    expect($article->fresh()->status)->toBe(NewsArticleStatusEnum::Published);
});

it('admin can delete an article', function () {
    $admin   = User::factory()->create(['is_admin' => true]);
    $article = NewsArticle::factory()->create();

    $this->actingAs($admin)
        ->delete(route('admin.news-articles.destroy', $article))
        ->assertRedirect(route('admin.news-articles.index'));

    $this->assertModelMissing($article);
});
```

```bash
php artisan test --compact tests/Feature/Admin/NewsImportControllerTest.php tests/Feature/Admin/NewsArticleControllerTest.php
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 6: Commit**

```
feat(news): add admin controllers and routes for import pipeline and article editor
```

---

## Task 8 — Admin Views

Check `resources/views/admin/news/index.blade.php` for layout name and section names before writing these views.

- [ ] **Step 1: Create `resources/views/admin/news-imports/create.blade.php`**

Key elements (match existing admin layout):
- Form: `POST route('admin.news-imports.store')` with `@csrf`
- Input: `name="url"` type="url", show `$errors->first('url')`
- Submit button using existing `<x-primary-button>` component
- Recent imports table below form: loop `$recentImports`, show status badge (`$import->status->colorClass()`, `$import->status->label()`), URL truncated, link to `route('admin.news-imports.show', $import)`

- [ ] **Step 2: Create `resources/views/admin/news-imports/index.blade.php`**

Table: Status badge, URL (break-all, max 60 chars truncated), Source Domain, User name, Created at, link to show.
Pagination: `{{ $imports->links() }}`.

- [ ] **Step 3: Create `resources/views/admin/news-imports/show.blade.php`**

Show: status badge, URL, failure_reason (if failed), source domain, user, created_at.
If `$import->isReady() && $import->article`: link to `route('admin.news-articles.edit', $import->article)`.

- [ ] **Step 4: Create `resources/views/admin/news-articles/index.blade.php`**

Filter bar (GET): status `<select>` from `$statuses`, source text input, submit.
Table: Status badge, Source, Original Title, pt-PT title (`$article->localization('pt-PT')?->title`), pt-BR title, Created, Published at, Edit link.
Pagination.

- [ ] **Step 5: Create `resources/views/admin/news-articles/edit.blade.php`**

Two-column layout:
- **Left** (1/3): source URL, original title, source domain, featured_image_url input, raw excerpt from `$article->import->raw_excerpt`
- **Right** (2/3): tab nav `foreach ($locales as $locale)` with tab button per locale, tab pane with fields: title, summary_short (textarea), summary_medium (textarea), seo_title, seo_description, body (Tiptap editor div)

Bottom action bar:
- Save: `PATCH route('admin.news-articles.update', $article)` button
- Publish: `POST route('admin.news-articles.publish', $article)` form with submit
- Schedule: datetime input + `POST route('admin.news-articles.schedule', $article)` form

- [ ] **Step 6: Verify views load**

```bash
php artisan test --compact
```

- [ ] **Step 7: Commit**

```
feat(news): add admin Blade views for import pipeline and article editor
```

---

## Task 9 — Public Localized Routes

- [ ] **Step 1: Create public controller stub**

```bash
php artisan make:controller NewsArticleController --no-interaction
```

- [ ] **Step 2: Fill `app/Http/Controllers/NewsArticleController.php`**

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\NewsLocaleEnum;
use App\Models\NewsArticle;
use Illuminate\View\View;

class NewsArticleController extends Controller
{
    public function index(string $localePrefix): View
    {
        $locale   = $this->resolveLocale($localePrefix);
        $articles = NewsArticle::published()
            ->with(['localizations' => fn ($q) => $q->where('locale', $locale->value)])
            ->orderByDesc('published_at')
            ->paginate(20);

        return view('news-articles.index', compact('articles', 'locale'));
    }

    public function show(string $localePrefix, string $slug): View
    {
        $locale = $this->resolveLocale($localePrefix);
        $column = match ($locale) {
            NewsLocaleEnum::PtPt => 'slug_pt_pt',
            NewsLocaleEnum::PtBr => 'slug_pt_br',
        };

        $article      = NewsArticle::published()->where($column, $slug)->with('localizations')->firstOrFail();
        $localization = $article->localization($locale->value) ?? abort(404);

        return view('news-articles.show', compact('article', 'localization', 'locale'));
    }

    private function resolveLocale(string $prefix): NewsLocaleEnum
    {
        return match ($prefix) {
            'pt-pt' => NewsLocaleEnum::PtPt,
            'pt-br' => NewsLocaleEnum::PtBr,
            default => abort(404),
        };
    }
}
```

- [ ] **Step 3: Add public routes to `routes/web.php`** — outside admin group:

```php
use App\Http\Controllers\NewsArticleController;

Route::middleware([EnsureNewsFeatureEnabled::class])
    ->prefix('{localePrefix}/noticias')
    ->where(['localePrefix' => 'pt-pt|pt-br'])
    ->name('news-articles.public.')
    ->group(function () {
        Route::get('/', [NewsArticleController::class, 'index'])->name('index');
        Route::get('/{slug}', [NewsArticleController::class, 'show'])->name('show');
    });
```

- [ ] **Step 4: Create minimal views**

`resources/views/news-articles/index.blade.php` — list articles, show `$localization->title` and `$localization->summary_short`.

`resources/views/news-articles/show.blade.php` — show `$localization->title`, include `@include('news._tiptap-content', ['content' => $localization->body])` for body.

- [ ] **Step 5: Write + run public route tests**

```bash
php artisan make:test --pest Feature/News/NewsArticlePublicRoutesTest --no-interaction
```

`tests/Feature/News/NewsArticlePublicRoutesTest.php`:
```php
<?php

use App\Enums\NewsLocaleEnum;
use App\Models\NewsArticle;
use App\Models\NewsArticleLocalization;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => config(['features.news' => true]));

it('shows published articles at pt-pt index', function () {
    $article = NewsArticle::factory()->published()->create(['slug_pt_pt' => 'jogo-anunciado']);
    NewsArticleLocalization::factory()->create([
        'news_article_id' => $article->id,
        'locale'          => NewsLocaleEnum::PtPt,
        'title'           => 'Jogo Anunciado',
    ]);

    $this->get('/pt-pt/noticias')->assertOk()->assertSee('Jogo Anunciado');
});

it('shows article at localized slug', function () {
    $article = NewsArticle::factory()->published()->create(['slug_pt_pt' => 'jogo-slug-test']);
    NewsArticleLocalization::factory()->create([
        'news_article_id' => $article->id,
        'locale'          => NewsLocaleEnum::PtPt,
        'title'           => 'Jogo Slug Test',
    ]);

    $this->get('/pt-pt/noticias/jogo-slug-test')->assertOk()->assertSee('Jogo Slug Test');
});

it('returns 404 for draft article', function () {
    NewsArticle::factory()->create([
        'status'     => \App\Enums\NewsArticleStatusEnum::Review,
        'slug_pt_pt' => 'draft-article',
    ]);

    $this->get('/pt-pt/noticias/draft-article')->assertNotFound();
});
```

```bash
php artisan test --compact tests/Feature/News/NewsArticlePublicRoutesTest.php
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 6: Commit**

```
feat(news): add public localized news routes (pt-pt/noticias, pt-br/noticias)
```

---

## Task 10 — Integration Test + Full Suite

- [ ] **Step 1: Write integration test**

```bash
php artisan make:test --pest Feature/News/NewsImportPipelineIntegrationTest --no-interaction
```

`tests/Feature/News/NewsImportPipelineIntegrationTest.php`:
```php
<?php

use App\Actions\News\ExtractNewsArticle;
use App\Actions\News\GenerateLocalizedNewsContent;
use App\Contracts\ContentExtractorInterface;
use App\Contracts\NewsGenerationServiceInterface;
use App\Enums\NewsArticleStatusEnum;
use App\Enums\NewsImportStatusEnum;
use App\Models\NewsImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('runs full pipeline: extract → generate → article in review state', function () {
    config([
        'services.anthropic.api_key' => 'test-key',
        'services.anthropic.model'   => 'claude-haiku-4-5-20251001',
        'services.anthropic.version' => '2023-06-01',
    ]);

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [[
                'type' => 'text',
                'text' => json_encode([
                    'pt-PT' => ['title' => 'Jogo PT', 'summary_short' => 'Curto.', 'summary_medium' => 'Médio.', 'body_markdown' => "## Título\n\nTexto.", 'seo_title' => 'SEO PT', 'seo_description' => 'Desc.'],
                    'pt-BR' => ['title' => 'Jogo BR', 'summary_short' => 'Curto.', 'summary_medium' => 'Médio.', 'body_markdown' => "## Título\n\nTexto.", 'seo_title' => 'SEO BR', 'seo_description' => 'Desc.'],
                ]),
            ]],
        ], 200),
    ]);

    $user   = User::factory()->create(['is_admin' => true]);
    $import = NewsImport::factory()->create(['user_id' => $user->id]);

    $extractor = mock(ContentExtractorInterface::class)
        ->expect(extract: fn () => [
            'title' => 'New Game 2026', 'content' => "## Overview\n\nBig news.", 'image' => 'https://cdn.example.com/img.jpg', 'summary' => 'Big news.', 'source_name' => 'IGN',
        ]);

    (new ExtractNewsArticle($extractor))->handle($import);
    expect($import->fresh()->status)->toBe(NewsImportStatusEnum::Extracted);

    $article = (new GenerateLocalizedNewsContent(app(\App\Contracts\NewsGenerationServiceInterface::class)))->handle($import->fresh());

    expect($import->fresh()->status)->toBe(NewsImportStatusEnum::Ready);
    expect($article->status)->toBe(NewsArticleStatusEnum::Review);
    expect($article->localizations)->toHaveCount(2);

    $ptPt = $article->localizations->firstWhere('locale', 'pt-PT');
    expect($ptPt->title)->toBe('Jogo PT');
    expect($ptPt->body['type'])->toBe('doc');
});
```

- [ ] **Step 2: Run integration test**

```bash
php artisan test --compact tests/Feature/News/NewsImportPipelineIntegrationTest.php
```

Expected: PASS.

- [ ] **Step 3: Run full suite**

```bash
php artisan test --compact
```

All tests must pass.

- [ ] **Step 4: Final commit**

```
feat(news): add integration test, verify full pipeline end-to-end
```

---

## Verification Checklist

| Check | How |
|-------|-----|
| All existing tests pass | `php artisan test --compact` |
| Fresh migration works | `php artisan migrate:fresh --no-interaction` |
| Import form accessible | Visit `/admin/news-imports/create` as admin |
| Import queued on submit | POST URL, see redirect + success flash |
| Articles list shows review records | `/admin/news-articles` |
| Edit page has pt-PT / pt-BR tabs | `/admin/news-articles/{id}/edit` |
| Publish changes status | Click publish, `$article->fresh()->status === Published` |
| Public route serves localized article | `/pt-pt/noticias/{slug}` returns 200 |
| Pint clean | `vendor/bin/pint --test --format agent` |

---

## Key Implementation Notes

1. **Never chain `make:migration` calls** — timestamp collision. Run one, wait, run next.
2. `JinaReaderService` is already bound to `ContentExtractorInterface` in `AppServiceProvider` — no new binding needed.
3. `AnthropicNewsGenerationService` is concrete — inject it directly. Laravel resolves constructor deps via container when jobs are dispatched.
4. **Existing `AdminNewsController` and `/admin/news` routes stay untouched** — new pipeline uses `/admin/news-imports` and `/admin/news-articles`.
5. SSRF check in `StoreNewsImportRequest` calls `gethostbyname()` — on test env with fake URLs, this returns the host string (not a valid IP), so `filter_var($ip, FILTER_VALIDATE_IP)` returns false and the check is skipped cleanly.
6. Feature flag `FEATURE_NEWS` (existing) gates both old and new admin routes. `FEATURE_NEWS_IMPORT_PIPELINE` is added to `config/features.php` for granular control but not wired to middleware in Phase 1 — checked manually where needed.