# Games Outbreak Project

## Setup


```bash
# Run migrations and seeders
php artisan migrate --seed

# Create the admin user id 1, required for the system lists
php artisan user:create-admin --email=admin@example.com --password=secure_password --force

# Create monthly game lists for the current year
php artisan games:lists:create-monthly --year=2026

# Populate db with games
php artisan igdb:upcoming:update --start-date=2026-01-06 --days=10 
```

### DB
```shell

# dump prod db
mysqldump -h xxx.db.laravel.cloud -P 3306 -u <user> -p --single-transaction main > dump-go-main.sql

# import DB
mysql -u root -p games_outbreak < /usr/dumps/dump-go-main.sql

```


### Create Admin User

After initial setup, create an admin user to access admin features:

**Interactive Mode (Recommended)**:
```bash
php artisan user:create-admin
```

The command will prompt for:
- Email address
- Password (hidden input)
- Password confirmation (hidden input)

**Non-Interactive Mode**:
```bash
php artisan user:create-admin --email=admin@example.com --password=secure_password --force
```

**Security Notes**:
- Use strong passwords (minimum 8 characters enforced)
- Never commit admin credentials to git
- Run command directly on server via SSH for production
- Password is automatically hashed using Laravel's bcrypt

### News pipeline (Jina + AI provider)

The News admin (`/admin/news-imports`) turns a pasted article URL into localised news articles (EN / pt-PT / pt-BR)
via an async pipeline. Requires:

1. **Jina Reader** — extracts article title, body, summary and image from the pasted URL. Sign up at jina.ai for a
   free key and add:
   ```
   JINA_API_KEY=your_key_here
   ```
2. **AI provider** for localisation — pick one and set:
   ```
   NEWS_AI_PROVIDER=anthropic          # or: openai
   ANTHROPIC_API_KEY=your_key_here     # (if anthropic)
   ANTHROPIC_MODEL=claude-haiku-4-5-20251001
   # OR
   OPENAI_API_KEY=your_key_here        # (if openai)
   OPENAI_MODEL=gpt-4o-mini
   ```
3. **Feature flags** — the news feature defaults to admin-only preview mode:
   ```
   FEATURE_NEWS=admin                  # true = public, admin = admin-only, false = disabled
   FEATURE_NEWS_URL_IMPORT=true        # show Import URL button in admin
   FEATURE_NEWS_IMPORT_PIPELINE=false  # enable the full queued pipeline (false = store imports only)
   ```
4. `php artisan config:clear`, then run `php artisan queue:work` so jobs process.

### YouTube Data API (Videos import)

The Videos admin (`/admin/videos`) uses YouTube Data API v3 to fetch title, channel, duration, thumbnails and
published date from a pasted URL.

1. Create a Google Cloud project and enable **YouTube Data API v3**.
2. Generate an API key (no OAuth needed — public data only).
3. Add it to `.env`:
   ```
   YOUTUBE_API_KEY=your_key_here
   ```
4. Clear config: `php artisan config:clear`.
5. Make sure the queue worker is running so imports actually process:
   ```
   php artisan queue:work
   ```

Without the key, imports fail with `YOUTUBE_API_KEY is not configured.` visible in the admin detail page. Tests fake
the HTTP client and never hit Google.



## Goal

This project is a web application for managing game lists and tracking game statuses.

## Features

- Track your game collection and statuses (Playing, Beaten, Completed, etc.)
- Organize games into custom lists
- **Backlog**: a dedicated list for games you plan to play
- **Wishlist**: a dedicated list for games you want to buy
- Quickly add/remove games to/from **Backlog** and **Wishlist** with one-click icons on each game card
- **News imports** — admins paste an article URL; the pipeline extracts and localises it (EN / pt-PT / pt-BR)
- **Videos** — admins paste a YouTube URL; a queued job fetches metadata via YouTube Data API v3 and stores a
  curated video record. One toggleable "featured" video plus the latest imports render as a homepage section and
  on a public `/videos` page with an in-page lightbox
- Admin panel for managing users, games, and lists

## Game Lists

Each user can have:

- **Multiple `regular` lists** (custom named lists)
- **One `backlog` list** (automatically created if missing)
- **One `wishlist` list** (automatically created if missing)

### List Types

| Type      | Description                          | Unique per user |
|-----------|--------------------------------------|-----------------|
| regular   | User-created custom lists            | No              |
| backlog   | Games the user plans to play         | Yes             |
| wishlist  | Games the user wants to buy          | Yes             |

## Backlog

- The backlog is a **special list** with `type = 'backlog'`.
- It is created automatically when the user visits **My Games**.
- Users can add/remove games to/from their backlog.
- Displayed under the **Backlog** tab on `/my-games`.

## Wishlist

- The wishlist is a **special list** with `type = 'wishlist'`.
- It is created automatically when the user visits **My Games**.
- Users can add/remove games to/from their wishlist.
- Displayed under the **Wishlist** tab on `/my-games`.
- Useful for tracking games you want to buy or try in the future.

## Monthly Game Lists

Monthly game lists are **system lists** that showcase games releasing in a specific month. These lists are automatically created and managed by administrators.

### Characteristics

- **Type**: System lists (`is_system = true`)
- **Visibility**: Public (`is_public = true`)
- **Active Status**: Controlled by `is_active` flag and date ranges (`start_at`, `end_at`)
- **Slug**: Auto-generated from month name (e.g., `january-2026`)
- **Access**: Viewable by all users via `/list/{slug}` route

### Creation

Create monthly lists for a specific year using the Artisan command:

```bash
php artisan games:lists:create-monthly --year=2026
```

This creates 12 lists (one for each month) with:
- Start date: First day of the month
- End date: Last day of the month
- Auto-generated unique slugs
- Public and active flags set

### Display

- **Homepage**: The active monthly list (within current date range) is displayed as "Featured Games"
- **Monthly Releases Page**: Full list view at `/monthly-releases`
- **Public Slug View**: Accessible at `/list/{slug}` for any active/public list

### Management

- Lists are created by admin user (user_id = 1)
- Games are added manually by admin or via seeders
- Lists can be activated/deactivated via `is_active` flag
- Date ranges control when lists appear on the homepage

## Seasonal Banners

The homepage features seasonal event banners displayed at the top of the page, above the Featured Games section.

### Image Specifications

**Location**: Place banner images in `public/images/` directory

**Recommended Sizes**:
- **Aspect Ratio**: 16:9 (rectangular)
- **Two Banners Side-by-Side**:
  - Minimum: 1200px × 675px
  - Optimal: 1600px × 900px
  - Maximum: 1920px × 1080px (Full HD)
- **Single Banner (Full Width)**:
  - Optimal: 1920px × 1080px (Full HD)

**File Format**: JPG or WebP (optimized for web, < 500KB per image recommended)

**Usage**: Update banner data in `resources/views/homepage/index.blade.php`:

```php
<x-seasonal-banners :banners="[
    [
        'image' => '/images/seasonal-event-1.jpg',
        'link' => route('monthly-releases'),
        'title' => 'January Releases',
        'description' => 'Discover the best games releasing this month',
        'alt' => 'January Releases Banner'
    ],
    [
        'image' => '/images/seasonal-event-2.jpg',
        'link' => route('upcoming'),
        'title' => 'Upcoming Games',
        'description' => 'See what\'s coming soon',
        'alt' => 'Upcoming Games Banner'
    ]
]" />
```

**Layout Behavior**:
- **2 Banners**: Displayed side-by-side on desktop, stacked on mobile
- **1 Banner**: Spans full width on all screen sizes
- **Responsive**: Automatically adapts to screen size

**Note**: For retina/high-DPI displays, use 2x resolution (e.g., 1920px × 1080px for standard, 3840px × 2160px for retina).



## Release Dates & Statuses

Games can have **multiple release dates per platform**, each with a different status (e.g., Early Access, Full Release, Advanced Access).

### Features

- **Detailed Release Information**: See all release dates for each platform, not just the earliest
- **Status Badges**: Color-coded status indicators show the type of release:
    - 🟢 **Full Release** - The official 1.0 release
    - 🟣 **Advanced Access** - Early access for pre-orders or special editions
    - 🔵 **Early Access** - Public testing/beta release
    - 🟡 **Alpha** / 🟠 **Beta** - Development builds
    - 🔴 **Cancelled** - Cancelled releases
    - 📱 **Digital Comp.** - Backward compatible digital releases
    - ⚡ **Next-Gen Patch** - Performance optimization updates
- **Platform Colors**: Each platform group has its own color (PlayStation = Blue, Xbox = Green, Nintendo = Red, PC = Gray)
- **Expandable View**: Click a platform to see all its release dates when there are multiple

### Sync Release Statuses

Release date statuses are fetched from IGDB and stored locally for better performance:

`php artisan igdb:sync-release-date-statuses`

This command:
- Fetches all release date status types from IGDB
- Stores them in the `release_date_statuses` table with abbreviations
- Caches the data for fast lookups
- Should be run once during setup (statuses rarely change)

### Display

On the game details page, release dates are grouped by platform and show:
- Platform name with colored border
- Earliest release date prominently displayed
- Badge showing count of additional releases (if any)
- Expandable list showing all releases with dates and status badges

**Example:**

```shell
PC 15/10/2025 [+2] 
• 15/10/2025 [Adv. Access] 
• 30/10/2025 [Full Release] 
• 15/11/2025 [Next-Gen Patch]
```


## News System

Multi-locale news (EN / pt-PT / pt-BR) with a URL-import pipeline that extracts article content and generates a
localised article per supported locale. Feature-flag gated via `config/features.php`.

### Supported locales

`NewsLocaleEnum` (`app/Enums/NewsLocaleEnum.php`) is the single source of truth:

| Case   | BCP-47 value | URL prefix | Path segment |
|--------|--------------|------------|--------------|
| `En`   | `en`         | `en`       | `news`       |
| `PtPt` | `pt-PT`      | `pt-pt`    | `noticias`   |
| `PtBr` | `pt-BR`      | `pt-br`    | `noticias`   |

Always use enum cases or their methods — never raw locale strings.

### URL structure

```
/en/news                 EN index
/en/news/{slug}          EN article
/pt-pt/noticias          PT-PT index
/pt-pt/noticias/{slug}
/pt-br/noticias          PT-BR index
/pt-br/noticias/{slug}
/news                    redirect to the best locale
```

- EN routes use a fixed `en/news` prefix (no route param).
- PT routes use `{localePrefix}/noticias` where `localePrefix` is constrained to `pt-pt|pt-br`.
- `/news` redirects using: `session('news_locale')` → `Accept-Language` header → `app.locale` config.

### `SetNewsLocale` middleware

Applied to **both** public news route groups. On each news page request it:

1. Resolves the current `NewsLocaleEnum` from the URL.
2. Calls `app()->setLocale($newsLocale->value)` — sets Laravel runtime locale for `__()` translations.
3. Persists `session(['news_locale' => $newsLocale->slugPrefix()])` — used for the `/news` redirect and the header
   switcher.
4. Shares `$currentNewsLocale` with all views rendered for that request.

Do **not** apply this middleware globally or to non-news routes.

### Header locale switcher

The switcher is visible on **all pages** (not just news) and uses a 3-tier fallback (since `$currentNewsLocale` is
only shared on news routes):

```php
$headerNewsLocale = $currentNewsLocale                          // middleware (news pages)
    ?? NewsLocaleEnum::fromPrefix(session('news_locale'))       // session (previous visit)
    ?? NewsLocaleEnum::fromAppLocale();                         // config default
```

Clicking a locale navigates to `$l->indexUrl()`, which triggers the middleware and updates the session.

### Article slugs

Each `NewsArticle` has separate slug columns per locale: `slug_en`, `slug_pt_pt`, `slug_pt_br`. A locale's article
URL is only valid when that slug column is non-null — check `$article->{$l->slugColumn()}` before linking.

### Import pipeline

Admin pastes an article URL on `/admin/news-imports/create` →
`StoreNewsImportRequest` (auth + URL + private-IP guards) →
`ImportNewsUrlJob` (queued) → chain:

1. `CreateNewsImport` action → `NewsImport` row in Pending with the source domain extracted.
2. `ExtractNewsArticleJob` → `ExtractNewsArticle` action calls `ContentExtractorInterface::extract($url)`. Default
   implementation is `JinaReaderService` (uses `JINA_API_KEY`). Populates `raw_title`, `raw_body`, `raw_excerpt`,
   `raw_image_url`. On failure marks Failed with reason.
3. `GenerateNewsContentJob` → `GenerateLocalizedNewsContent` action calls the configured AI provider
   (`NEWS_AI_PROVIDER`: `anthropic` → `AnthropicNewsGenerationService`, `openai` → `OpenAiNewsGenerationService`) to
   produce per-locale title / summary / body. Writes `news_article_localizations` rows keyed by
   `(news_article_id, locale)`.
4. Admin reviews the generated article on `/admin/news-articles`, edits via Tiptap editor, then publishes or
   schedules it.
5. `PublishScheduledNewsJob` (scheduled) — flips any due `scheduled_at` articles to `published`.

### Feature flags (`config/features.php`)

- `FEATURE_NEWS` — master toggle: `true` (public), `admin` (admin-only preview, default), `false` (disabled / 404).
- `FEATURE_NEWS_URL_IMPORT` — shows the *Import URL* button in the admin (default `true`).
- `FEATURE_NEWS_IMPORT_PIPELINE` — enables the queued extract + AI-generate pipeline (default `false`). When off,
  `NewsImport` rows are stored but never extracted.

`EnsureNewsFeatureEnabled::isVisibleTo($user)` gates visibility on both sides (404 for anonymous users when the
feature is in `admin` mode).

### External services

- **Jina Reader** — content extractor. Key: `JINA_API_KEY`. Bound in `AppServiceProvider` as
  `ContentExtractorInterface → JinaReaderService`.
- **Anthropic or OpenAI** — localised content generator. Driver selected by `config('services.news_ai_provider')`.
  Keys: `ANTHROPIC_API_KEY` / `OPENAI_API_KEY`. Models configurable via `ANTHROPIC_MODEL` / `OPENAI_MODEL`.

### Telegram broadcast on publish

Each `NewsArticle` carries `should_broadcast` (default `true`) and `broadcasted_at`. When the admin clicks **Publish
Now** on `/admin/news-articles/{article}/edit`, the Publish form carries the `should_broadcast` checkbox (default ON)
to `NewsArticleController::publish`, which persists the flag and calls `PublishNewsArticle`.

`PublishNewsArticle` dispatches `BroadcastNewsArticleJob` when `should_broadcast = true` and `broadcasted_at = null`.
The job:

1. Picks the first available locale via `NewsArticleTelegramFormatter::resolveLocale($article)` in the order
   **pt-PT → pt-BR → EN** (a locale needs both a non-null slug and a `NewsArticleLocalization` with a title).
2. Formats a MarkdownV2 caption (`📰 *title*` + summary + "Ler mais →" link to the localized article URL).
3. If `featured_image_url` is set → `TelegramClient::sendPhoto()` with the caption. Otherwise →
   `TelegramClient::sendMessage()` with link preview enabled.
4. On success, sets `broadcasted_at = now()` — idempotent; a second dispatch is a no-op unless `force = true`.

All gates re-check inside the job (`services.telegram.enabled`, `should_broadcast`, `broadcasted_at`, published status),
so the DB state wins against races.

### Tests

- Feature: `tests/Feature/News/SetNewsLocaleMiddlewareTest.php` — middleware, session persistence, `/news` redirect,
  header switcher labels
- Feature: `tests/Feature/News/NewsImportPipelineIntegrationTest.php` — full pipeline end-to-end with fakes
- Feature: `tests/Feature/News/ExtractNewsArticleTest.php`, `GenerateLocalizedNewsContentTest.php`,
  `PublishNewsArticleTest.php`, `NewsJobsTest.php`, `NewsModelsTest.php`
- Feature: `tests/Feature/News/NewsArticlePublicRoutesTest.php`, `NewsArticleSeoTest.php` — public routes +
  canonical / hreflang / OG / JSON-LD
- Feature: `tests/Feature/Admin/NewsImportControllerTest.php`, `NewsArticleControllerTest.php`
- Unit: `tests/Unit/NewsLocaleEnumTest.php` — `fromBrowserLocale()` parsing

## Videos System

Curated YouTube videos surfaced in a homepage section and on a public `/videos` index. Videos are
**language-neutral** — one record serves all locales; no per-locale slugs or localizations table.

### Domain model

`Video` model / `videos` table — see `app/Models/Video.php` and the migration.

- `youtube_id` (nullable, unique) — extracted from the pasted URL via regex
- `title`, `channel_name`, `channel_id`, `duration_seconds`, `thumbnail_url`, `description`, `published_at` — fetched
  from YouTube Data API v3
- `is_featured` (bool) — admin-toggled; `VideoImportController::toggleFeatured()` enforces that only one video is
  featured at a time inside a DB transaction
- `is_active` (bool) — staging toggle; hides the row from public listings without deleting
- `status` (`VideoImportStatusEnum`: Pending, Fetching, Ready, Failed) + `failure_reason`
- `raw_api_response` (JSON) — full Data API payload for debugging/re-extract
- `user_id` — the admin who triggered the import
- `video_category_id` (nullable FK → `video_categories`, `nullOnDelete`) — optional grouping (see Categories below)

Key scopes on `Video`:

- `ready()` — status = Ready
- `active()` — is_active = true
- `publicVisible()` — ready + active, used on homepage and `/videos`

Helpers: `embedUrl(bool $autoplay)`, `watchUrl()`, `thumbnailMaxRes()`, `thumbnailHq()`,
`durationFormatted()` (returns `M:SS` or `H:MM:SS`), `markAs(VideoImportStatusEnum, ?string $reason)`.

### Import pipeline

Admin pastes a YouTube URL on `/admin/videos/create` →
`StoreVideoImportRequest` (auth via `isAdmin()`, URL + regex + private-IP guards) →
`ImportYoutubeVideoJob` (queued, 3 tries, backoff `[10, 30, 90]`) → inside `handle()`:

1. `YoutubeDataService::extractYoutubeId($url)` — regex against
   `/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/shorts\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/`.
   No match → `CreateVideo` action writes a Failed row with a reason. The row is visible in the admin list so the
   failure is not silent.
2. Dedupe — if a `Video` with the same `youtube_id` exists, skip.
3. `CreateVideo` action → creates the row in Pending.
4. `FetchYoutubeVideoMetadata` action → marks Fetching, calls `YoutubeDataService::fetchVideo()`, populates fields,
   marks Ready. On `Throwable`: logs + marks Failed with the exception message.

`YoutubeDataService::fetchVideo()` calls `https://www.googleapis.com/youtube/v3/videos` with
`part=snippet,contentDetails` + the API key, throws `RuntimeException` on HTTP failure or empty `items[]`. ISO 8601
durations (`PT4M46S`, `PT1H2M3S`) are parsed by `parseIsoDuration()`.

### Config

`config/services.php` → `'youtube' => ['api_key' => env('YOUTUBE_API_KEY')]`. The key is required; the service throws
a `RuntimeException` when it is missing. Set `YOUTUBE_API_KEY` in `.env` before running the queue worker against real
imports (tests always fake `Http`).

### URLs / routes

- `/videos` — public, no locale prefix (`videos.index`)
- `/admin/videos`, `/admin/videos/create`, `/admin/videos/{video}` — admin index / create / show
- `PATCH /admin/videos/{video}/toggle-featured` — enforces single-featured in one transaction
- `PATCH /admin/videos/{video}/toggle-active` — flips `is_active`
- `PATCH /admin/videos/{video}/update-category` — assigns or clears the video category
- `DELETE /admin/videos/{video}`
- `/admin/video-categories` — categories CRUD (index / store / update / destroy)

Admin routes sit inside the existing `auth + EnsureAdminUser + prevent-caching` group. The Videos + Video Categories
links in the admin dropdown (`resources/views/components/header.blade.php`, desktop + mobile) are **not** gated by the
news feature flag.

### Categories

`VideoCategory` (`video_categories` table) groups videos for display. Admin-managed via `/admin/video-categories` with a
modal-based CRUD (mirrors the Genre admin pattern). Columns:

- `name`, `slug` (unique), `is_active`
- `color` (hex string, e.g. `#ff8a2a`) — drives the badge color via a CSS variable
- `icon` (heroicon slug, e.g. `film`, optional)

Seeded rows from `VideoCategorySeeder` (idempotent via `firstOrCreate`): **trailers**, **gameplay**, **reviews**, **tech**.
Deleting a category is safe — the FK is `nullOnDelete`, so videos keep existing but lose their category tag.

Each video has an optional `video_category_id`. Admin assigns it via a dropdown on `/admin/videos/{video}` which posts
to the `update-category` endpoint (single-field PATCH, same shape as `toggleFeatured`). When a video has a category, a
`<x-videos.category-badge>` component renders a neon pill on:

- The homepage hero tile (top-right of the thumbnail)
- The homepage list rows (inline above the title)
- The public `/videos` cards (top-right of the thumbnail)
- The admin videos index (Category column) + admin show page (header row)

The pill (`.neon-category-pill` in `resources/css/homepage.css`) uses `color-mix(in srgb, var(--c) ...)` with a
solid-color fallback for Safari ≤15. The color comes from the category's `color` column via an inline `style="--c: ..."`.

### Homepage section

`<x-homepage.latest-videos :featured="$featuredVideo" :videos="$latestVideos">` sits between This Week's Choices and
Events in `resources/views/homepage/index.blade.php`.

`HomepageController::getLatestVideos()` pulls the top 6 public-visible videos by `published_at desc`, picks the
featured one (or falls back to the newest), and returns the rest (up to 5) as the list. The section renders nothing
when the pool is empty — no empty-state placeholder on the homepage.

Both sub-components render a news-style meta line above the title: orange channel name · cyan
`$video->published_at->diffForHumans()` (e.g. *Rockstar · 3 days ago*).

Sub-components:

- `resources/views/components/videos/hero-tile.blade.php` — big hero with featured badge, play overlay, duration,
  category badge (top-right), channel + days-ago meta
- `resources/views/components/videos/list-row.blade.php` — 118px thumbnail + inline category badge + channel + days-ago
  meta + 2-line title

Both apply the `theme-neon` palette (`--neon-cyan`, `--neon-orange`, `--neon-purple`) and Inter font — **no bespoke
`go-*` tokens or Space Grotesk / JetBrains Mono fonts**.

### Lightbox

Shared modal root `<div id="go-video-lightbox">` lives in `resources/views/layouts/app.blade.php` (before
`@stack('scripts')`). `resources/js/video-lightbox.js` delegates clicks on `[data-video-id]`, builds
`https://www.youtube.com/embed/{id}?autoplay=1&rel=0&modestbranding=1`, locks body scroll while open, closes on
backdrop click / ESC / Close button. Imported from `resources/js/app.js`.

### Public `/videos` page

`resources/views/videos/index.blade.php` mirrors the News index structure: `neon-section-frame`, `neon-card` rows,
pagination at 20/page, breadcrumb JSON-LD, canonical + OG tags. Single locale → no hreflang. Empty state uses
`neon-panel` + `x-heroicon-o-video-camera`.

### Telegram broadcast on first activation

Each `Video` carries `should_broadcast` (default `true`) and `broadcasted_at`. The import form (`/admin/videos/create`)
has a "Broadcast to Telegram when Ready" checkbox (default ON) that threads through `ImportYoutubeVideoJob →
CreateVideo` onto the row. The admin show page offers a separate PATCH toggle
(`admin.videos.toggle-should-broadcast`) to flip the flag later.

`MaybeBroadcastVideo` action dispatches `BroadcastVideoJob` when **all** of the following are true:

- `status = Ready`
- `is_active = true`
- `should_broadcast = true`
- `broadcasted_at = null`

It is called from two places:

- `FetchYoutubeVideoMetadata` — right after a successful transition to `Ready` (covers the default happy path since
  `is_active` defaults to `true`).
- `VideoImportController::toggleActive` — after flipping `is_active` from `false` to `true` (covers videos that were
  staged hidden).

The job formats `🎬 *title* + channel · duration + [Ver no YouTube →]`, sends the thumbnail via `sendPhoto` (falls back
to `sendMessage` if no thumbnail), then stamps `broadcasted_at`. Second dispatches are no-ops unless `force = true`.

### CLI — `broadcast:resend`

`php artisan broadcast:resend <type> <id> [--channel=telegram]` re-sends a previously-broadcast (or never-broadcast) news
article or video. Intended as a **fallback / testing tool** — it passes `force: true` to the job so the `broadcasted_at`
gate is bypassed; `should_broadcast` and the global enabled flag are still respected.

```
php artisan broadcast:resend news 42            # re-broadcast news article id 42
php artisan broadcast:resend video 7            # re-broadcast video id 7
php artisan broadcast:resend news 42 --channel=x   # aborts: X channel is not implemented yet
```

Only `telegram` is implemented today; `x` is accepted as an argument so the CLI shape stays stable when X is added,
but it exits with a "not implemented" error.

### Tests

- Unit: `tests/Unit/VideoImportStatusEnumTest.php` — labels / color class / `isFinal()`
- Feature: `tests/Feature/Videos/YoutubeDataServiceTest.php` — ID extraction, ISO 8601 parsing, Http::fake for success
  / empty / HTTP failure. Lives under `Feature/` (not `Unit/`) because Pest's container is only wired there.
- Feature: `tests/Feature/Videos/FetchYoutubeVideoMetadataTest.php` — Ready / Failed transitions
- Feature: `tests/Feature/Videos/ImportYoutubeVideoJobTest.php` — full job with `Http::fake`, dedupe, non-YouTube URL
- Feature: `tests/Feature/Admin/VideoImportControllerTest.php` — auth/forbidden/happy paths, toggle-featured
  single-row invariant, toggle-active, destroy
- Feature: `tests/Feature/VideosIndexPageTest.php` — public index visibility (Ready + active only), SEO tags,
  pagination
- Feature: `tests/Feature/HomepageLatestVideosTest.php` — section renders when videos exist, hidden when empty,
  positioned between This Week's Choices and Events; category badge + days-ago meta rendering
- Unit: `tests/Unit/VideoCategoryTest.php` — fillable + cast
- Feature: `tests/Feature/Admin/VideoCategoryControllerTest.php` — CRUD, validation, slug uniqueness, `nullOnDelete`
  behaviour
- Feature: `tests/Feature/Admin/VideoCategoryAssignmentTest.php` — `update-category` PATCH assigns / clears / rejects
  bogus ids
- Feature: `tests/Feature/Broadcasts/BroadcastNewsArticleJobTest.php` — sendPhoto/sendMessage branches, locale fallback
  (pt-PT → pt-BR → EN), idempotency gates, `force` path
- Feature: `tests/Feature/Broadcasts/BroadcastVideoJobTest.php` — ditto for videos
- Feature: `tests/Feature/Broadcasts/NewsPublishBroadcastTest.php` — Publish action dispatches via `Bus::fake` when
  `should_broadcast` is on, skips otherwise
- Feature: `tests/Feature/Broadcasts/VideoActivationBroadcastTest.php` — `MaybeBroadcastVideo` gates, toggle-active
  dispatch, toggle-should-broadcast PATCH
- Feature: `tests/Feature/Commands/ResendBroadcastCommandTest.php` — happy paths, unknown type/id/channel, x aborts
