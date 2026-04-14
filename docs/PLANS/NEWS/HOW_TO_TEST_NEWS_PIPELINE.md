# How to Test: News Ingestion & Editorial Pipeline

## Prerequisites

- `.env` has `FEATURE_NEWS=true` (or `admin`) and an API key for the active AI provider
- `NEWS_AI_PROVIDER` set to `anthropic` or `openai` (default: `anthropic`)
- Queue worker running: `php artisan queue:work`
- Migrations run: `php artisan migrate`
- You are logged in as an admin user (`is_admin = true`)

---

## 1. Automated Tests

Run the full pipeline test suite:

```bash
# All pipeline tests
php artisan test --compact tests/Feature/News/

# Admin controller tests
php artisan test --compact tests/Feature/Admin/NewsImportControllerTest.php tests/Feature/Admin/NewsArticleControllerTest.php

# Full suite (should show 0 failures)
php artisan test --compact
```

Key test files and what they cover:

| File | Coverage |
|------|----------|
| `NewsModelsTest.php` | Models, relationships, scopes, auto-slug |
| `GenerateLocalizedNewsContentTest.php` | Anthropic + OpenAI API calls, provider switching, locale conversion, error handling |
| `ExtractNewsArticleTest.php` | JinaReader extraction, failure path |
| `PublishNewsArticleTest.php` | Publish action, published_at preservation |
| `NewsJobsTest.php` | Job chain: Import → Extract → Generate |
| `NewsImportControllerTest.php` | Admin import form, SSRF, auth/authz |
| `NewsArticleControllerTest.php` | Admin article CRUD, publish, localization update |
| `NewsArticlePublicRoutesTest.php` | Public pt-pt/pt-br routes, 404 for draft |
| `NewsImportPipelineIntegrationTest.php` | Full end-to-end pipeline with faked HTTP |

---

## 2. Manual Browser Test

### Step 1 — Enable the feature

In `.env`, choose a provider and set the corresponding key:

```dotenv
FEATURE_NEWS=true
FEATURE_NEWS_IMPORT_PIPELINE=true

# Choose provider: anthropic | openai
NEWS_AI_PROVIDER=anthropic
ANTHROPIC_API_KEY=sk-ant-...

# Or use OpenAI:
# NEWS_AI_PROVIDER=openai
# OPENAI_API_KEY=sk-...
```

Restart: `php artisan config:clear`

### Step 2 — Start the queue worker

```bash
php artisan queue:work --tries=3
```

### Step 3 — Import a URL

1. Visit `/admin/news-imports/create`
2. Paste a news article URL (e.g. from IGN, Kotaku, Eurogamer)
3. Click **Queue Import**
4. You'll be redirected to `/admin/news-imports` — the import appears with status **Pending**

### Step 4 — Watch the pipeline run

Refresh `/admin/news-imports` and watch status progress:
```
Pending → Fetching → Extracted → Generating → Ready
```

The queue worker output shows each job step. Full pipeline takes ~15-30 seconds depending on AI provider latency.

### Step 5 — Review the generated article

1. Click **View** on the import → shows raw extracted content
2. Click **View Generated Article →** to open the edit page
3. The edit page (`/admin/news-articles/{id}/edit`) shows:
   - Source info (original URL, title, source domain)
   - Two locale tabs: **Português (Portugal)** and **Português (Brasil)**
   - Each tab has: title, summary short, summary medium, SEO title, SEO description

### Step 6 — Edit and save

1. Edit any localization fields in either tab
2. Click **Save** — saves all localizations via `PATCH /admin/news-articles/{id}`
3. Session flash confirms "Article saved."

### Step 7 — Publish

Click **Publish Now** → article status changes to **Published** and `published_at` is stamped.

Alternatively:
- Pick a future datetime and click **Schedule** → status becomes **Scheduled**
- The `PublishScheduledNewsJob` cron fires every 5 minutes and auto-publishes past-due articles

### Step 8 — View on public site

After publishing, the article is available at:
```
/pt-pt/noticias/{slug}
/pt-br/noticias/{slug}
```

Listing pages:
```
/pt-pt/noticias
/pt-br/noticias
```

---

## 3. Failure Paths to Test

| Scenario | How to trigger | Expected result |
|----------|---------------|-----------------|
| Invalid URL | Submit `not-a-url` in import form | Validation error on `url` field |
| Private IP URL | Submit `http://127.0.0.1/` | SSRF block: "resolves to a private or reserved IP" |
| API key missing | Unset the key for the active provider, queue a job | Import status → **Failed**, `failure_reason` shows error |
| Wrong provider value | Set `NEWS_AI_PROVIDER=unknown` | Falls back to Anthropic (default branch of `match`) |
| Unpublished article accessed | GET `/pt-pt/noticias/draft-slug` | 404 |
| Wrong locale prefix | GET `/fr-fr/noticias` | 404 (route constraint blocks it) |

---

## 4. Database Inspection

```bash
# Check imports
php artisan tinker --execute="App\Models\NewsImport::latest()->first()"

# Check articles with localizations
php artisan tinker --execute="App\Models\NewsArticle::with('localizations')->latest()->first()"

# Check scheduled articles due for publishing
php artisan tinker --execute="App\Models\NewsArticle::scheduledDue()->get()"
```

---

## 5. Manually Fire the Scheduler

```bash
# Publish all past-due scheduled articles now
php artisan schedule:run

# Or dispatch the job directly
php artisan tinker --execute="dispatch(new App\Jobs\News\PublishScheduledNewsJob)"
```

---

## 6. Switching the AI Provider

Set `NEWS_AI_PROVIDER` in `.env` and clear config cache:

```bash
# Use Anthropic (Claude)
NEWS_AI_PROVIDER=anthropic
ANTHROPIC_API_KEY=sk-ant-...
ANTHROPIC_MODEL=claude-haiku-4-5-20251001   # optional override

# Use OpenAI (GPT)
NEWS_AI_PROVIDER=openai
OPENAI_API_KEY=sk-...
OPENAI_MODEL=gpt-4o-mini                    # optional override

php artisan config:clear
```

No code changes required. The container resolves the correct service automatically.

### Adding a third provider

1. Create `app/Services/YourProviderNewsGenerationService.php` implementing `NewsGenerationServiceInterface`
2. Add a case to the `match` in `AppServiceProvider::register()`:

```php
return match (config('services.news_ai_provider')) {
    'openai'    => new OpenAiNewsGenerationService($converter),
    'yourprovider' => new YourProviderNewsGenerationService($converter),
    default     => new AnthropicNewsGenerationService($converter),
};
```

3. Add credentials to `config/services.php` and `.env.example`
