# News Ingestion & Editorial Pipeline — Implementation Notes

## Overview

Queued URL-import pipeline that extracts article content via JinaReader, localises it to pt-PT and pt-BR via a configurable AI provider (Anthropic or OpenAI), and presents it for admin review before publishing.

---

## Architecture

### Data Flow

```
Admin pastes URL
  → StoreNewsImportRequest (validates URL + SSRF check)
  → ImportNewsUrlJob (creates NewsImport record)
    → ExtractNewsArticleJob (calls JinaReaderService)
      → GenerateNewsContentJob (calls active AI provider via NewsGenerationServiceInterface)
        → NewsArticle + 2x NewsArticleLocalization (pt-PT, pt-BR)

Admin reviews → edits → Publish Now | Schedule
  → Published: available at /pt-pt/noticias/{slug} and /pt-br/noticias/{slug}
```

### Database Tables

| Table | Purpose |
|-------|---------|
| `news_imports` | Tracks each URL import attempt and its pipeline status |
| `news_articles` | One editorial entity per import; holds slugs, featured image, publish dates |
| `news_article_localizations` | One row per locale (pt-PT, pt-BR) per article; holds all translated content |

### Queue Chain

Three jobs run sequentially. Each job dispatches the next only on success:

```
ImportNewsUrlJob (tries: 3)
  └─ creates NewsImport, dispatches ExtractNewsArticleJob

ExtractNewsArticleJob (tries: 3)
  └─ calls JinaReaderService, updates import raw_* fields
  └─ if status = Extracted → dispatches GenerateNewsContentJob

GenerateNewsContentJob (tries: 2, timeout: 120s)
  └─ calls active AI provider (resolved via NewsGenerationServiceInterface)
  └─ creates NewsArticle + localizations
  └─ marks import as Ready
```

On any failure the import is marked `Failed` with `failure_reason` stored.

---

## Key Design Decisions

### AI Provider Abstraction

`NewsGenerationServiceInterface` mirrors the existing `ContentExtractorInterface` → `JinaReaderService` pattern. Two providers are implemented: `AnthropicNewsGenerationService` and `OpenAiNewsGenerationService`. The active one is resolved in `AppServiceProvider` based on `config('services.news_ai_provider')`:

```php
// AppServiceProvider::register()
$this->app->bind(NewsGenerationServiceInterface::class, function () {
    $converter = new MarkdownToTiptapConverter;

    return match (config('services.news_ai_provider')) {
        'openai' => new OpenAiNewsGenerationService($converter),
        default  => new AnthropicNewsGenerationService($converter),
    };
});
```

Switching provider = one `.env` change (`NEWS_AI_PROVIDER=openai`). Adding a third provider = new class + one `match` case.

### No New Packages

Both AI providers are called via Laravel's built-in `Http` facade. No Composer dependency added.

### SSRF Protection

`StoreNewsImportRequest` resolves the submitted URL's hostname to an IP via `gethostbyname()` and rejects private/reserved ranges using `FILTER_VALIDATE_IP` with `FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE`.

### Slug Auto-generation

`NewsArticle::booted()` generates unique `slug_pt_pt` and `slug_pt_br` from `original_title` on creation when slugs are not explicitly set. Collision-safe via counter suffix (`-1`, `-2`, etc.).

### Feature Flag

All routes (admin and public) are gated by `EnsureNewsFeatureEnabled` middleware, which reads `config('features.news')`. Values:
- `false` — 404 for everyone
- `'admin'` — admin-only preview
- `true` — public + admin

`FEATURE_NEWS_IMPORT_PIPELINE` in `config/features.php` is available as a sub-feature toggle for future use.

### Scheduled Publishing

`PublishScheduledNewsJob` runs every 5 minutes via the scheduler (`routes/console.php`). It queries `NewsArticle::scheduledDue()` and calls `PublishNewsArticle::handle()` on each result. Uses `withoutOverlapping()` and `onOneServer()`.

---

## File Map

### New files

| Path | Responsibility |
|------|----------------|
| `app/Support/News/MarkdownToTiptapConverter.php` | Converts Markdown string to Tiptap JSON doc |
| `app/Contracts/NewsGenerationServiceInterface.php` | AI provider contract |
| `app/Enums/NewsImportStatusEnum.php` | Pending/Fetching/Extracted/Generating/Ready/Failed |
| `app/Enums/NewsArticleStatusEnum.php` | Draft/Review/Approved/Scheduled/Published/Archived |
| `app/Enums/NewsLocaleEnum.php` | PtPt / PtBr |
| `app/Models/NewsImport.php` | Import tracking model |
| `app/Models/NewsArticle.php` | Editorial article model |
| `app/Models/NewsArticleLocalization.php` | Per-locale content model |
| `database/factories/NewsImportFactory.php` | States: `ready()`, `failed()`, `extracted()` |
| `database/factories/NewsArticleFactory.php` | States: `published()`, `scheduled()` |
| `database/factories/NewsArticleLocalizationFactory.php` | State: `ptBr()` |
| `app/Services/AnthropicNewsGenerationService.php` | Calls Claude API, converts body_markdown via converter |
| `app/Services/OpenAiNewsGenerationService.php` | Calls OpenAI Chat Completions API, same interface and prompt |
| `app/Actions/News/CreateNewsImport.php` | Creates NewsImport record from URL |
| `app/Actions/News/ExtractNewsArticle.php` | Runs JinaReader, updates import |
| `app/Actions/News/GenerateLocalizedNewsContent.php` | Runs AI, creates article + localizations |
| `app/Actions/News/PublishNewsArticle.php` | Sets status=Published, stamps published_at |
| `app/Actions/News/ScheduleNewsArticle.php` | Sets status=Scheduled, stamps scheduled_at |
| `app/Jobs/News/ImportNewsUrlJob.php` | Job: step 1 |
| `app/Jobs/News/ExtractNewsArticleJob.php` | Job: step 2 |
| `app/Jobs/News/GenerateNewsContentJob.php` | Job: step 3 |
| `app/Jobs/News/PublishScheduledNewsJob.php` | Cron job: auto-publishes past-due scheduled articles |
| `app/Http/Requests/Admin/News/StoreNewsImportRequest.php` | URL validation + SSRF |
| `app/Http/Requests/Admin/News/UpdateNewsArticleRequest.php` | Localization edit validation |
| `app/Http/Controllers/Admin/News/NewsImportController.php` | Admin: import CRUD |
| `app/Http/Controllers/Admin/News/NewsArticleController.php` | Admin: article CRUD + publish/schedule |
| `app/Http/Controllers/NewsArticleController.php` | Public: localized article index + show |
| `resources/views/admin/news-imports/index.blade.php` | Import list |
| `resources/views/admin/news-imports/create.blade.php` | URL input form + recent imports |
| `resources/views/admin/news-imports/show.blade.php` | Import detail + link to article |
| `resources/views/admin/news-articles/index.blade.php` | Article list with status/source filters |
| `resources/views/admin/news-articles/edit.blade.php` | Locale tab editor + publish/schedule actions |
| `resources/views/news-articles/index.blade.php` | Public localized article listing |
| `resources/views/news-articles/show.blade.php` | Public localized article detail |

### Modified files

| Path | What changed |
|------|--------------|
| `app/Http/Controllers/AdminNewsController.php` | `importFromUrl()` now injects `MarkdownToTiptapConverter`; private `markdownToTiptap()` removed |
| `app/Providers/AppServiceProvider.php` | Dynamic `NewsGenerationServiceInterface` binding based on `NEWS_AI_PROVIDER` |
| `config/services.php` | Added `anthropic`, `openai`, and `news_ai_provider` keys |
| `config/features.php` | Added `news_import_pipeline` flag |
| `.env.example` | Added `ANTHROPIC_API_KEY`, `ANTHROPIC_MODEL`, `OPENAI_API_KEY`, `OPENAI_MODEL`, `NEWS_AI_PROVIDER`, `FEATURE_NEWS_IMPORT_PIPELINE` |
| `routes/web.php` | Added admin and public news pipeline route groups |
| `routes/console.php` | Registered `PublishScheduledNewsJob` cron |

---

## Routes

### Admin (require auth + `is_admin`)

| Method | URI | Name | Action |
|--------|-----|------|--------|
| GET | `/admin/news-imports` | `admin.news-imports.index` | List imports |
| GET | `/admin/news-imports/create` | `admin.news-imports.create` | URL import form |
| POST | `/admin/news-imports` | `admin.news-imports.store` | Queue import |
| GET | `/admin/news-imports/{id}` | `admin.news-imports.show` | Import detail |
| GET | `/admin/news-articles` | `admin.news-articles.index` | List articles |
| GET | `/admin/news-articles/{id}/edit` | `admin.news-articles.edit` | Edit article |
| PATCH | `/admin/news-articles/{id}` | `admin.news-articles.update` | Save article |
| POST | `/admin/news-articles/{id}/publish` | `admin.news-articles.publish` | Publish now |
| POST | `/admin/news-articles/{id}/schedule` | `admin.news-articles.schedule` | Schedule |
| DELETE | `/admin/news-articles/{id}` | `admin.news-articles.destroy` | Delete |

### Public (gated by `EnsureNewsFeatureEnabled`)

| Method | URI | Name |
|--------|-----|------|
| GET | `/pt-pt/noticias` | `news-articles.index` |
| GET | `/pt-pt/noticias/{slug}` | `news-articles.show` |
| GET | `/pt-br/noticias` | `news-articles.index` |
| GET | `/pt-br/noticias/{slug}` | `news-articles.show` |

---

## Environment Variables

```dotenv
# Feature gates
FEATURE_NEWS=true                            # or 'admin' for preview-only
FEATURE_NEWS_IMPORT_PIPELINE=true

# AI provider selection
NEWS_AI_PROVIDER=anthropic                   # anthropic | openai

# Anthropic (used when NEWS_AI_PROVIDER=anthropic)
ANTHROPIC_API_KEY=sk-ant-...
ANTHROPIC_MODEL=claude-haiku-4-5-20251001   # optional override

# OpenAI (used when NEWS_AI_PROVIDER=openai)
OPENAI_API_KEY=sk-...
OPENAI_MODEL=gpt-4o-mini                    # optional override
```

---

## Known Limitations (Phase 1)

- No duplicate detection — importing the same URL twice creates two imports.
- No retry UI — failed imports must be re-queued manually.
- No bulk import — one URL at a time.
- Body editor is text-only (no Tiptap rich editor wired up in the admin edit view).
- Public views are minimal — no pagination styling, no SEO meta tags beyond title/description.

These are all explicitly Phase 2 / Phase 3 scope per the original plan.
