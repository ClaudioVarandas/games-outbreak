# Rework 2.0 — Deploy Checklist

Branch: `feature/news-import-pipeline`

## 1. Migrations

```bash
php artisan migrate
```

4 new tables:

| Migration | Description |
|---|---|
| `create_news_imports_table` | Raw import queue — URL, extracted content, status |
| `create_news_articles_table` | Published article record with per-locale slug columns |
| `create_news_article_localizations_table` | Translated content per article per locale |
| `add_slug_en_to_news_articles_table` | Additive column — safe on live data |

---

## 2. Environment Variables

Add to production `.env`:

```dotenv
# News feature toggle: true (public), admin (admin-only preview), false (disabled)
FEATURE_NEWS=admin

# Sub-feature toggles
FEATURE_NEWS_URL_IMPORT=true
FEATURE_NEWS_IMPORT_PIPELINE=false

# AI provider: anthropic or openai
NEWS_AI_PROVIDER=anthropic
ANTHROPIC_API_KEY=
ANTHROPIC_MODEL=claude-haiku-4-5-20251001
OPENAI_API_KEY=
OPENAI_MODEL=gpt-4o-mini
```

| Key | Required | Notes |
|---|---|---|
| `FEATURE_NEWS` | yes | Start with `admin` to verify in production before going public |
| `FEATURE_NEWS_URL_IMPORT` | yes | |
| `FEATURE_NEWS_IMPORT_PIPELINE` | yes | Keep `false` until pipeline is validated in production |
| `NEWS_AI_PROVIDER` | yes | `anthropic` or `openai` |
| `ANTHROPIC_API_KEY` | if using Anthropic | |
| `ANTHROPIC_MODEL` | no | Defaults to `claude-haiku-4-5-20251001` |
| `OPENAI_API_KEY` | if using OpenAI | |
| `OPENAI_MODEL` | no | Defaults to `gpt-4o-mini` |

---

## 3. Assets

```bash
npm run build
```

Changed: global search styling, header mobile layout, list page neon design, news article page.

---

## 4. Queue Worker

The import pipeline dispatches 3 chained jobs (`ImportNewsUrlJob` → `ExtractNewsArticleJob` → `GenerateNewsContentJob`). Verify a worker is running:

```bash
php artisan queue:work --queue=default
```

Or confirm Supervisor/Forge is managing a persistent worker (`QUEUE_CONNECTION=database`).

---

## 5. Scheduler

`PublishScheduledNewsJob` runs every 5 minutes and is already registered in `routes/console.php`. No action needed if `php artisan schedule:run` is already active in production (it is — existing IGDB jobs depend on it).

---

## 6. Cache

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## Notes

- No breaking changes to existing functionality. All changes are additive.
- The news system is fully gated behind `FEATURE_NEWS`. Setting it to `admin` limits access to admin users only while you verify the pipeline in production.
- Flip `FEATURE_NEWS=true` to make news public when ready.
- Flip `FEATURE_NEWS_IMPORT_PIPELINE=true` to enable the queued import pipeline when validated.
