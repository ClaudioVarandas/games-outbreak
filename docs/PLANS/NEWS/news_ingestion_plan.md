# News Ingestion & Editorial Management Module
## Laravel 12 Architecture Plan

### Project Context
Existing system: Laravel 12 application  
Goal: Add a News Management module that allows admins to paste article URLs, automatically generate summaries, translate/localize into pt-PT and pt-BR, review, and publish.

---

# 1. Solution Overview

This module will be integrated directly into the existing Laravel 12 monolith.

### Main workflow

1. Admin pastes source URL
2. System imports article
3. Extracts article content
4. Generates summary
5. Generates pt-PT localization
6. Generates pt-BR localization
7. Admin reviews content
8. Publish immediately or schedule

---

# 2. Architecture

## Keep Existing Laravel App

No separate backend.

Implement as internal Laravel module:
- Models
- Controllers
- Jobs
- Actions
- Admin Views
- Routes

---

# 3. Module Structure

```txt
app/
├── Actions/News/
├── Jobs/News/
├── Models/News/
├── Http/
│   ├── Controllers/Admin/News/
│   └── Controllers/News/
├── Http/Requests/Admin/News/
├── Policies/
└── Support/News/

resources/views/admin/news/
resources/views/news/

database/migrations/

routes/
├── admin.php
└── web.php
```

---

# 4. Database Design

## 4.1 news_imports

Tracks each imported URL.

| Field | Type |
|--------|------|
| id | bigint |
| url | text |
| canonical_url | text |
| source_domain | string |
| status | enum |
| failure_reason | text nullable |
| raw_title | text |
| raw_author | string nullable |
| raw_published_at | datetime nullable |
| raw_body | longText |
| raw_excerpt | text nullable |
| raw_image_url | text nullable |
| checksum | string nullable |
| created_by | foreignId |
| timestamps | yes |

### Status values:
- pending
- fetching
- extracted
- generating
- ready
- failed

## 4.2 news_articles

Published editorial entity.

| Field | Type |
|--------|------|
| id | bigint |
| news_import_id | foreignId |
| status | enum |
| source_name | string |
| source_url | text |
| original_title | text |
| original_language | string |
| original_published_at | datetime nullable |
| featured_image_url | text nullable |
| slug_pt_pt | string |
| slug_pt_br | string |
| scheduled_at | datetime nullable |
| published_at | datetime nullable |
| timestamps | yes |

### Status values:
- draft
- review
- approved
- scheduled
- published
- archived

## 4.3 news_article_localizations

Localized article versions.

| Field | Type |
|--------|------|
| id | bigint |
| news_article_id | foreignId |
| locale | string |
| title | text |
| summary_short | text |
| summary_medium | text |
| body | longText nullable |
| seo_title | text nullable |
| seo_description | text nullable |
| status | enum |
| generation_metadata | json |
| timestamps | yes |

### Locale values:
- pt-PT
- pt-BR

---

# 5. Eloquent Relationships

```php
// NewsImport
public function article()
{
    return $this->hasOne(NewsArticle::class);
}

// NewsArticle
public function import()
{
    return $this->belongsTo(NewsImport::class);
}

public function localizations()
{
    return $this->hasMany(NewsArticleLocalization::class);
}

// NewsArticleLocalization
public function article()
{
    return $this->belongsTo(NewsArticle::class);
}
```

---

# 6. Queue Job Pipeline

Use Laravel Queues.

## Jobs

### ImportNewsUrlJob
- validate URL
- normalize URL
- create import record

### ExtractNewsArticleJob
- fetch article page
- parse content
- extract metadata
- save raw content

### GenerateNewsContentJob
- summarize article
- generate pt-PT
- generate pt-BR
- create localizations

### PublishScheduledNewsJob
- publish scheduled content

---

# 7. Laravel Scheduler

```php
Schedule::job(new PublishScheduledNewsJob)->everyMinute();
```

---

# 8. Service Layer

## Actions
- CreateNewsImport
- ExtractNewsArticle
- GenerateLocalizedNewsContent
- PublishNewsArticle
- ScheduleNewsArticle
- RetryNewsImport

---

# 9. Admin Interface Design

## Dashboard Page

Rework the existing pages (list/edit)

Route: `/admin/news`  

Features:
- List all articles
- Filter by status
- Filter by source
- Search title/source

Columns:
- Status
- Source
- Original title
- pt-PT title
- pt-BR title
- Imported date
- Publish date
- Actions

## Import Page
Route: `/admin/news/import`

UI:
- Single URL input
- Bulk URL textarea
- Import button
- Recent imports list

## Review/Edit Page
Route: `/admin/news/{id}/edit`

Left panel:
- Source URL
- Original title
- Source domain
- Publish date
- Extracted image
- Raw excerpt preview

Right panel:
Tabs:
- pt-PT
- pt-BR

Each tab:
- Title
- Short summary
- Medium summary
- Body
- SEO title
- SEO description

Bottom actions:
- Save Draft
- Regenerate Locale
- Publish
- Schedule

## Failed Imports
Route: `/admin/news/failed`

Shows:
- Failed URL
- Failure reason
- Retry button

---

# 10. Frontend Stack Recommendation

Already exists, i believe not much changes are required. 

/news
/news/{slug}

---

# 11. AI Integration Layer

```php
interface NewsGenerationService
{
    public function summarizeAndLocalize(array $article): array;
}
```

Example output:

```json
{
  "pt_pt": {
    "title": "",
    "summary_short": "",
    "summary_medium": ""
  },
  "pt_br": {
    "title": "",
    "summary_short": "",
    "summary_medium": ""
  }
}
```

---

# 12. Security

## Admin Access
Use Policies / Gates

Roles:
- news-editor
- news-publisher
- admin

## URL Fetch Protection
Prevent SSRF:
- block localhost
- block private IP ranges
- enforce HTTPS/HTTP only
- timeout requests

---

# 13. Public Routes

```php
/pt-pt/noticias/{slug}
/pt-br/noticias/{slug}
```

Only published articles visible.

---

# 14. MVP Delivery Plan

## Phase 1
- migrations
- models
- import page
- queue jobs
- extraction
- AI generation
- review page
- publish flow

## Phase 2
- bulk import
- scheduling
- retries
- source management

## Phase 3
- RSS auto-import
- trusted source automation
- duplicate detection
- analytics dashboard

---

# 15. Recommended Immediate Next Step

1. Create migrations  
2. Create models + relationships  
3. Create import admin page  
4. Implement queue jobs  
5. Integrate AI generation  
6. Build review/publish UI  

---

# Final Recommendation

Build this as a Laravel 12 monolith module with:
- internal News domain
- queued ingestion pipeline
- AI-assisted localization
- review-first admin workflow

This is the fastest, safest, and most maintainable architecture.
