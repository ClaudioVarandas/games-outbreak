# Weekly "This Week's Choices" broadcast

Automated Sunday-night post of next week's curated releases to
Telegram and X, sharing the same data source as the homepage "This
Week's Choices" section.

- **When:** Sunday 21:00 Europe/Lisbon (DST-aware)
- **What:** Games whose pivot `release_date` falls in the *upcoming*
  Mon–Sun window (Mon = day after the send, Sun = 7 days out)
- **Where from:** The active yearly system `GameList`
- **Shared source:** `App\Services\WeeklyChoicesCollector` —
  consumed by both `HomepageController::index` (current week) and
  the broadcast pipeline (upcoming week). No query duplication.

## Architecture at a glance

```
app/Services/WeeklyChoicesCollector.php     forCurrentWeek() / forUpcomingWeek()
app/Services/WeeklyChoicesPayload.php       readonly DTO — games + window + ctaUrl

app/Services/Broadcasts/
├── WeeklyChoicesBroadcaster.php            orchestrator; per-channel error isolation
├── Channels/BroadcastChannel.php           interface
├── Channels/TelegramChannel.php
├── Channels/XChannel.php
├── Clients/TelegramClient.php
├── Clients/XClient.php                     OAuth 1.0a signed requests
├── Formatters/TelegramMessageFormatter.php MarkdownV2 with escape
├── Formatters/XTweetFormatter.php          280-char budget + "+ N more"
└── Exceptions/BroadcastFailedException.php

app/Jobs/BroadcastWeeklyChoicesJob.php      ShouldQueue, tries=3, backoff=[60,300,900]
app/Console/Commands/BroadcastWeeklyChoicesCommand.php  weekly-choices:broadcast
routes/console.php                          Sun 21:00 Europe/Lisbon schedule entry
```

## Environment variables

Add to `.env` (examples already listed in `.env.example`):

```
# Master toggles — leave false until credentials are in and verified
TELEGRAM_BROADCAST_ENABLED=false
X_BROADCAST_ENABLED=false

# Telegram
TELEGRAM_BOT_TOKEN=
TELEGRAM_CHAT_ID=

# X (Twitter) — OAuth 1.0a user context
X_API_KEY=
X_API_SECRET=
X_ACCESS_TOKEN=
X_ACCESS_TOKEN_SECRET=
```

After changing env values run `php artisan config:clear`.

## Telegram setup

1. **Create a bot** — open a DM with `@BotFather` on Telegram,
   `/newbot`, follow prompts. Copy the **bot token** it issues.
2. **Create a channel or group** — public or private. Add the bot
   as an **administrator** (required to post).
3. **Find the chat id**:
   - Send any message to the channel.
   - Open `https://api.telegram.org/bot<TOKEN>/getUpdates` in a
     browser.
   - Copy `message.chat.id` — channel ids are large negatives
     (e.g. `-1001234567890`). For public channels `@channelname`
     also works.
4. Fill the env values and flip `TELEGRAM_BROADCAST_ENABLED=true`.

Formatter uses MarkdownV2; all user-supplied strings (titles, dates,
platforms) are escaped. Link previews are enabled so the CTA shows
a rich card.

## X (Twitter) setup

### Which account posts

Whichever X account you're logged in as when you generate the
Access Token. Recommendation — create a dedicated account
(e.g. `@GamesOutbreak`) instead of using a personal one. Easier to
revoke and audit later. Code and config don't change — only the
env values differ.

### Access tier

The `POST /2/tweets` endpoint is available on the **Free** developer
tier (500 posts/month as of 2025). One weekly post (~4–5/month) is
well within quota. No paid tier required.

### Variables

| Var | What it is | Where to get it |
|-----|------------|-----------------|
| `X_API_KEY` | App "Consumer Key" | developer.x.com → Project/App → Keys and tokens |
| `X_API_SECRET` | App "Consumer Secret" | same screen |
| `X_ACCESS_TOKEN` | User-context token (identifies the posting account) | same screen → "Access Token and Secret" → Generate |
| `X_ACCESS_TOKEN_SECRET` | Paired secret | same screen |
| `X_BROADCAST_ENABLED` | Master toggle | — |

### Steps

1. Sign in to `developer.x.com` **as the account you want to post
   from**.
2. Create a Project + App (or reuse one).
3. **App permissions → Read and Write** (default is Read-only; won't
   post). Type of App: **Web App** or **Automated App**.
4. On "Keys and tokens":
   - Copy API Key / API Secret → `X_API_KEY` / `X_API_SECRET`.
   - **Regenerate Access Token and Secret** (required any time
     permissions change) → `X_ACCESS_TOKEN` / `X_ACCESS_TOKEN_SECRET`.
5. Set `X_BROADCAST_ENABLED=true`.
6. `php artisan config:clear`.

Signing: the `XClient` builds an OAuth 1.0a signature inline
(HMAC-SHA1) — no third-party package required.

## How to test

### 1. Dry-run (no credentials needed)

Renders and prints the output for each channel; makes **no** HTTP
calls. Works without any env set.

```
php artisan weekly-choices:broadcast --dry-run
php artisan weekly-choices:broadcast --dry-run --channel=telegram
php artisan weekly-choices:broadcast --dry-run --channel=x
```

Expected output: `Upcoming window: YYYY-MM-DD → YYYY-MM-DD · N
games`, followed by one rendered block per channel marked `(enabled)`
or `(disabled)` depending on config. Use this to eyeball MarkdownV2
escaping and the 280-char X budget.

### 2. End-to-end Telegram (staging)

After filling `TELEGRAM_*` and flipping
`TELEGRAM_BROADCAST_ENABLED=true`:

```
php artisan config:clear
php artisan weekly-choices:broadcast --channel=telegram
```

Posts immediately. Verify the message appears in the channel and
every game title is a working link to the game detail page.

### 3. End-to-end X (when credentials are ready)

```
php artisan weekly-choices:broadcast --channel=x --dry-run   # sanity
php artisan weekly-choices:broadcast --channel=x             # live
```

### 4. Scheduler sanity

- `php artisan schedule:list` — confirm
  `0 20 * * 0  broadcast-weekly-choices` (cron shown in UTC; the
  scheduler evaluates against `Europe/Lisbon`, so it shifts to
  `21 UTC` in winter).
- `php artisan schedule:test` — Laravel 12 prompt; pick
  `broadcast-weekly-choices` to dispatch without waiting for Sunday.

### 5. Failure / retry rehearsal

Point `TELEGRAM_BOT_TOKEN` to a bogus value and run the command.
Expected: job throws, lands in `failed_jobs` after 3 attempts with
`60s / 5min / 15min` backoff, `weekly-choices.broadcast.failed`
logged. Fix the token and re-run to verify recovery.

### 6. Automated tests

```
php artisan test --compact tests/Feature/Broadcasts
```

Covers:
- `WeeklyChoicesCollectorTest` — current vs upcoming window,
  empty list, 18-game limit, year-end rollover.
- `TelegramMessageFormatterTest` — header/CTA/deep links, MarkdownV2
  escaping, empty payload.
- `XTweetFormatterTest` — 280-char budget, `+ N more` tail,
  all-fit case.
- `BroadcastWeeklyChoicesJobTest` — posts to Telegram, skips X when
  disabled, skip-silent on empty week, throws when all channels
  fail, `--channel` scoping.
- `BroadcastWeeklyChoicesCommandTest` — dry-run prints & sends
  nothing, live sends once, unknown `--channel` exits with code 2.

## Behavior notes

- **Empty week** → skip silently; `Log::info('weekly-choices.skipped', {reason: 'empty-window', ...})`. No post, no retry.
- **Partial failure** (one channel ok, one fails) → does **not**
  retry (would double-post the successful channel). Logs
  `weekly-choices.broadcast.partial` at error level for operator
  attention.
- **All channels fail** → `BroadcastFailedException` bubbles up; job
  retries with backoff; final failure logs
  `weekly-choices.broadcast.failed`.
- **Disabled channel** → no-op, no HTTP call, no error.