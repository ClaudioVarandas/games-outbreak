# Social Posts Hub — X (Twitter) posting tool

## Context

Today the site pushes content to external channels **fire-and-forget on publish**: publishing a
news article auto-broadcasts to Telegram, toggling a video active auto-broadcasts, and curated
weekly/monthly game picks auto-broadcast to Telegram **and** X. There is no human in the loop, no
editing, and only binary `should_broadcast` + `broadcasted_at` flags — no post text stored, no live
post URL, no per-network history.

The user wants a **human-in-the-loop, editable, optionally AI-drafted composer** for posting to
social networks, starting with **X only**, designed to add networks later. It must let the admin
review/edit the text before sending, schedule posts, and keep a history. Auto-posting (no review)
is explicitly a *later* phase once the tool is proven.

### Decisions locked with the user

| # | Decision |
|---|----------|
| 1 | **Additive now**, auto-post-without-review possible later. |
| 2 | **Hybrid UI**: dedicated `/admin/social-posts` hub (compose + history) **plus** a "Post this" shortcut on news/video admin pages that deep-links into the composer pre-filled. |
| 3 | **Full lifecycle**: drafts + scheduling + history. |
| 4 | **AI required when enabled** via env flag `SOCIAL_POSTS_AI_ENABLED`; when off → manual composition. Text always editable. |
| 5 | **EN only** for now; design to extend languages later. |
| 6 | **Images via link cards** (no media upload code): news → article URL → `summary_large_image` card; video → YouTube watch URL → player card. (OG/twitter tags verified present on `news-articles/show.blade.php` and YouTube links unfurl natively.) |
| 7 | **Sources**: news articles, videos, **and** game picks (weekly/monthly). **X-only minimal schema** (single `social_posts` table, no per-network target rows; a `network` column future-proofs without a migration). |
| 8 | **Reuse existing X credentials** (`config('services.x')`). |
| 9 | **Game picks**: the hub owns the **X** path (parked draft for review); **Telegram stays auto/untouched**. A scheduled job **pre-builds a draft** in the hub at the existing broadcast cadence. |

## Architecture

Reuse the existing `app/Services/Broadcasts/` X stack (`XClient` OAuth 1.0a, creds from
`config('services.x')`). The hub is a new persistence + UI + AI layer on top of `XClient`.

### Data model — `social_posts` (single table)

| Column | Notes |
|--------|-------|
| `id` | |
| `user_id` (FK, nullable) | creator; null for scheduler-built drafts |
| `source_type` | `SocialPostSourceTypeEnum`: NewsArticle, Video, WeeklyPicks, MonthlyPicks, Manual |
| `source_id` (nullable) | NewsArticle/Video id; null for picks/manual |
| `source_meta` (json, nullable) | picks period (e.g. `{month: "2026-07"}` / `{week_start}`) |
| `network` | `SocialNetworkEnum` (only `X` now) — future-proofing column |
| `status` | `SocialPostStatusEnum`: Draft, Scheduled, Posting, Posted, Failed, Canceled |
| `body` (text) | editable composed tweet text |
| `link_url` (nullable) | article URL / YouTube watch URL included in the post |
| `scheduled_at` (nullable) | |
| `posted_at` (nullable) | |
| `external_id` (nullable) | X tweet id |
| `external_url` (nullable) | `https://x.com/i/web/status/{id}` |
| `error` (text, nullable) | last failure message |
| `ai_generated` (bool) | |
| `ai_meta` (json, nullable) | provider, model |
| timestamps | |

Three enums in `app/Enums/` following existing convention (`label()`, and `colorClass()` on
status like `NewsArticleStatusEnum`/`VideoImportStatusEnum`). Keep all source-type/network/status
lists in the enums — never inline in Blade (per project convention).

`SocialPost` model: enum/json/datetime casts via `casts()`, `user()` relation, and a
`sourceModel(): NewsArticle|Video|null` helper (explicit columns, not a morphTo, because picks are
not model-backed).

### AI drafting — shared LLM client (mandated refactor)

`AnthropicNewsGenerationService` and `OpenAiNewsGenerationService` duplicate the
raw "POST messages → extract text → strip ```json fences" call. Per the project's reuse rule,
extract the shared low-level call and refactor existing callers **in this PR**:

- `app/Services/Llm/LlmChatClient.php` (interface): `complete(string $prompt, int $maxTokens): string` → raw text.
- `AnthropicChatClient` (x-api-key, `/v1/messages`, `content.0.text`, `stop_reason`) and
  `OpenAiChatClient` (Bearer, `/v1/chat/completions`, `choices.0.message.content`, `finish_reason`).
- **Refactor** both news services to depend on `LlmChatClient` for the HTTP+text step; they keep
  their own JSON parsing/validation.
- Bind `LlmChatClient` in `AppServiceProvider` by `config('services.news_ai_provider')` (reuse the
  existing provider switch — one switch for news + social).

New `app/Services/SocialPosts/SocialPostTextGenerator.php`: builds an EN tweet prompt from a source
context (title, link, type, picks payload), calls `LlmChatClient`, trims to ≤280. Guarded by
`config('services.social_posts.ai_enabled')` — when off, callers skip generation and the body
starts empty (or, for weekly picks, pre-filled by the existing `XTweetFormatter`).

### Actions / Jobs

- `app/Actions/SocialPosts/CreateSocialPostDraft.php` — from a source ref → `SocialPost` (Draft):
  resolves `link_url`, `source_meta`, and initial `body` (AI when enabled, else formatter/empty).
- `app/Actions/SocialPosts/SendSocialPost.php` — validates creds present (mirrors `XChannel`),
  posts via `XClient::postTweet(config('services.x'), $body)`, sets `external_id/url`,
  `posted_at`, `status=Posted`; on throw → `status=Failed` + `error`. Shared by send-now and the job.
- `app/Jobs/SocialPosts/SendSocialPostJob.php` (ShouldQueue, `tries=3`, backoff `[60,300,900]`,
  matches existing broadcast jobs) — guards status, calls `SendSocialPost`.
- `app/Jobs/SocialPosts/BuildGamePickDraftJob.php` — uses `WeeklyChoicesCollector` /
  `MonthlyChoicesCollector` to build the payload, generates X text (AI or `XTweetFormatter`), parks
  a `SocialPost` (Draft) with `source_type=WeeklyPicks|MonthlyPicks`.

### Reroute weekly X → hub (decision #9)

- `AppServiceProvider:45` — change `tag([TelegramChannel::class, XChannel::class], 'broadcasts.channels')`
  to `tag([TelegramChannel::class], 'broadcasts.channels')`. Weekly auto-broadcast becomes
  **Telegram-only**; monthly is already Telegram-only. X for picks now flows through the hub draft.
  (`XChannel`/`XTweetFormatter` are kept and reused by the draft builder.)
- `X_BROADCAST_ENABLED` is no longer consulted for auto-broadcast; hub send only checks the 4 X
  creds are present. Note this in `.env.example`.

### Scheduling (`routes/console.php`)

- New `social-posts:send-due` command, `->everyMinute()->withoutOverlapping()->onOneServer()` —
  dispatches `SendSocialPostJob` for posts with `status=Scheduled` and `scheduled_at <= now`.
  Reads live state so edits/cancels are honored.
- New schedule entries dispatching `BuildGamePickDraftJob` at the existing cadence (weekly Sun, the
  23rd/28th monthly) so a reviewable X draft is parked alongside the auto-Telegram broadcast.

### Admin UI (hybrid)

Routes under the existing `/admin` group (`auth` + `EnsureAdminUser` + `prevent-caching`), in
`routes/web.php`:

- `Route::resource('social-posts', SocialPostController::class)` (index/create/store/edit/update/destroy)
- `POST social-posts/{socialPost}/send`, `/regenerate`, `/cancel`
- `POST social-posts/from-source` — the "Post this" shortcut: `CreateSocialPostDraft` → redirect to its edit page.

`app/Http/Controllers/Admin/SocialPosts/SocialPostController.php` (slim; actions injected).
Form Requests in `app/Http/Requests/Admin/SocialPosts/` (Store/Update): `authorize()` → `isAdmin()`,
`body` required ≤280, `scheduled_at` nullable `after:now`.

Views in `resources/views/admin/social-posts/` (Tailwind + dark, matching news/videos):
- `index.blade.php` — table: source, network (X), status badge (enum `colorClass()`), snippet,
  scheduled/posted, live link, row actions.
- `create.blade.php` / `edit.blade.php` — source picker, editable textarea with a **280 char
  counter** (Alpine), **Regenerate** button (Alpine `fetch` → `/regenerate`, shown only when AI
  enabled), link/card preview, and **Save draft / Schedule / Send now** buttons.

Nav: add **Social Posts** to the admin dropdown in `resources/views/components/header.blade.php`
(both mobile ~ll.103-126 and desktop ~ll.246-273 blocks), near Videos / CLI Reference.

"Post this" shortcut buttons: news-articles index/edit + videos index/show → POST to
`social-posts.from-source` with `source_type`/`source_id`.

### Config (`config/services.php`)

```php
'social_posts' => [
    'ai_enabled' => env('SOCIAL_POSTS_AI_ENABLED', false),
],
```
Add `SOCIAL_POSTS_AI_ENABLED` to `.env.example` with a note that the hub reuses the `X_*` creds.

## Implementation phases

1. **Foundation** — 3 enums, migration, `SocialPost` model + factory, config flag.
2. **X send path** — `SendSocialPost` action + `SendSocialPostJob` (reuse `XClient`). Manual compose
   end-to-end, no AI.
3. **Hub UI** — routes, `SocialPostController`, Form Requests, index/create/edit Blade, nav link.
4. **AI drafting** — extract `LlmChatClient` + refactor both news services; `SocialPostTextGenerator`;
   `CreateSocialPostDraft`; wire regenerate + auto-draft-on-source-select; env-flag gating.
5. **Shortcuts** — "Post this" from news/video pages → draft → redirect to edit.
6. **Scheduling + game picks** — `social-posts:send-due` command + schedule; untag `XChannel`;
   `BuildGamePickDraftJob` + schedule entries.

## Critical files

- Reuse: `app/Services/Broadcasts/Clients/XClient.php`, `app/Services/Broadcasts/Formatters/XTweetFormatter.php`, `app/Services/{Weekly,Monthly}ChoicesCollector.php`, `config/services.php` (`x`).
- Refactor: `app/Services/AnthropicNewsGenerationService.php`, `app/Services/OpenAiNewsGenerationService.php`, `app/Providers/AppServiceProvider.php` (ll. 36-45), `routes/console.php`, `routes/web.php`, `resources/views/components/header.blade.php`.
- New: `app/Enums/SocialNetworkEnum.php`, `SocialPostStatusEnum.php`, `SocialPostSourceTypeEnum.php`; `app/Models/SocialPost.php`; `app/Services/Llm/*`; `app/Services/SocialPosts/SocialPostTextGenerator.php`; `app/Actions/SocialPosts/*`; `app/Jobs/SocialPosts/*`; `app/Http/Controllers/Admin/SocialPosts/SocialPostController.php`; `app/Http/Requests/Admin/SocialPosts/*`; `resources/views/admin/social-posts/*`; migration; factory.

## Testing & verification

Pest (required for new features). Run with `XDEBUG_MODE=off php artisan test --compact --filter=...`.

- **Unit**: `SocialPostTextGenerator` (mock `LlmChatClient`); enum `label()`/`colorClass()`;
  `LlmChatClient` impls fence-stripping (`Http::fake`).
- **Feature**:
  - Hub CRUD admin-only (403 for non-admin).
  - Create draft from source (news + video): correct `link_url`, `source_type`.
  - AI flag on → body pre-filled (faked LLM); off → empty body, no LLM call.
  - Send-now → `Http::fake` X endpoint asserted, `external_id/url` + `status=Posted` stored.
  - `social-posts:send-due` dispatches only due Scheduled posts; canceled/edited honored.
  - `BuildGamePickDraftJob` parks a Draft from a faked payload.
  - **Regression**: weekly auto-broadcast now posts to Telegram only (X not called); existing news
    services still pass after the `LlmChatClient` refactor.
- **Lint**: `vendor/bin/pint --dirty --format agent`.
- **Manual**: `nvm use 24 && npm run build` if assets touched; visit `/admin/social-posts`, create a
  draft from a video, toggle `SOCIAL_POSTS_AI_ENABLED`, schedule + run the scheduler.
