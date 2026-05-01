# Monthly "Next Month's Choices" broadcast

Automated mid-/end-of-month post of next month's curated releases to
Telegram, sharing the same data source as the homepage's curated
list. Mirrors the weekly broadcast (see
[`weekly-choices-broadcast.md`](weekly-choices-broadcast.md)) with
two distinct fires per month and a different window.

- **When:**
  - **23rd of each month, 09:00 UTC** — `PREVIEW` post
  - **28th of each month, 09:00 UTC** — final post
- **What:** Games whose pivot `release_date` falls in the *upcoming*
  calendar month (`startOfMonth->addMonth()` through that month's
  `endOfMonth`).
- **Where from:** The active yearly system `GameList` (same source as
  the weekly broadcast).
- **Channels:** Telegram only. **X is intentionally not wired** —
  pattern is in place to add a `MonthlyXChannel` later.
- **Limit:** 40 games per window (vs 18 for weekly). Keeps the
  rendered message under Telegram's 4096-char `sendMessage` cap with
  typical line lengths.

## PREVIEW vs FINAL

Both fires query the **same upcoming-month window** and produce the
same list of games. The difference is the header:

- Final: `*🎮 Games Outbreak — Next Month's Choices*`
- Preview: `*🎮 Games Outbreak — PREVIEW — Next Month's Choices*`

The flag travels via:

- `MonthlyChoicesPayload::$isPreview`
- `BroadcastMonthlyChoicesJob(isPreview: true)`
- `monthly-choices:broadcast --preview`

## Architecture at a glance

```
app/Services/MonthlyChoicesCollector.php       forCurrentMonth() / forUpcomingMonth(?, isPreview)
app/Services/MonthlyChoicesPayload.php         readonly DTO — games + window + ctaUrl + isPreview

app/Services/Broadcasts/
├── MonthlyChoicesBroadcaster.php              orchestrator; per-channel error isolation
├── Channels/MonthlyBroadcastChannel.php       interface
├── Channels/MonthlyTelegramChannel.php        Telegram impl (reuses TelegramClient)
├── Formatters/MonthlyTelegramMessageFormatter.php  MarkdownV2; injects PREVIEW marker
└── Exceptions/BroadcastFailedException.php    (shared with weekly)

app/Jobs/BroadcastMonthlyChoicesJob.php        ShouldQueue, tries=3, backoff=[60,300,900]
app/Console/Commands/BroadcastMonthlyChoicesCommand.php  monthly-choices:broadcast
routes/console.php                             23rd 09:00 + 28th 09:00 UTC entries
```

The generic substrate (`TelegramClient`, `BroadcastFailedException`,
`EscapesMarkdownV2` trait, `services.telegram.*` config) is reused
verbatim — same bot/chat as the weekly broadcast.

## Environment variables

No new variables. Reuses the existing Telegram config from the
weekly setup:

```
TELEGRAM_BROADCAST_ENABLED=false
TELEGRAM_BOT_TOKEN=
TELEGRAM_CHAT_ID=
```

If those are already set up for the weekly broadcast, the monthly
broadcast posts to the same chat. Run `php artisan config:clear`
after any env change.

## CLI

```
# Dry-run (no HTTP)
php artisan monthly-choices:broadcast --dry-run
php artisan monthly-choices:broadcast --dry-run --preview
php artisan monthly-choices:broadcast --dry-run --channel=telegram

# Live (Telegram is the default channel)
php artisan monthly-choices:broadcast
php artisan monthly-choices:broadcast --preview
```

Defaults differ from the weekly command:

- `--channel=telegram` is the default (vs weekly's `all`) since X is
  not registered for monthly.
- `--preview` is a flag, not an option — present means PREVIEW mode.

## How to test

### 1. Dry-run

Renders and prints the output for each registered channel; makes
**no** HTTP calls. Works without any Telegram credentials.

```
php artisan monthly-choices:broadcast --dry-run
```

Expected output:
`Upcoming window: YYYY-MM-01 → YYYY-MM-DD · N games`,
followed by the rendered Telegram block. Adding `--preview` prepends
the PREVIEW marker to the header and appends ` · PREVIEW` to the
window summary.

### 2. End-to-end Telegram (staging)

After filling `TELEGRAM_*` and flipping
`TELEGRAM_BROADCAST_ENABLED=true`:

```
php artisan config:clear
php artisan monthly-choices:broadcast --preview
php artisan monthly-choices:broadcast
```

Verify both messages appear in the channel and every game title is a
working link to the game detail page.

### 3. Scheduler sanity

```
php artisan schedule:list
```

Expect:

```
0 9 23 * *  broadcast-monthly-choices-preview
0 9 28 * *  broadcast-monthly-choices
```

Use `php artisan schedule:test` and pick either entry to dispatch
without waiting for the day.

### 4. Failure / retry rehearsal

Same shape as the weekly job: 3 tries with `60s / 5min / 15min`
backoff, `monthly-choices.broadcast.failed` logged on terminal
failure.

### 5. Automated tests

```
php artisan test --compact --filter=Monthly
```

Covers:

- `MonthlyChoicesCollectorTest` — current vs upcoming month, empty
  list, 40-game limit, year-end rollover (Dec → Jan), preview flag
  passthrough.
- `MonthlyTelegramMessageFormatterTest` — header in both modes
  (with/without PREVIEW), monthly subtitle (`_Month Year_`),
  MarkdownV2 escaping, empty payload.
- `BroadcastMonthlyChoicesJobTest` — posts to Telegram, PREVIEW
  marker flows through, never hits X, skips silently on empty
  month, throws when all channels fail, `--channel` scoping.
- `BroadcastMonthlyChoicesCommandTest` — dry-run prints & sends
  nothing, `--preview` tags output, live sends once, unknown
  `--channel` exits with code 2, `--channel=telegram` is the
  default.

## Behavior notes

- **Empty month** → skip silently;
  `Log::info('monthly-choices.skipped', {reason: 'empty-window', is_preview: ...})`.
  No post, no retry.
- **Both fires on same data** → if the curated list has not changed
  between the 23rd and 28th, the FINAL message is content-identical
  to the PREVIEW except for the header.
- **All channels fail** → `BroadcastFailedException` bubbles up; job
  retries with backoff; final failure logs
  `monthly-choices.broadcast.failed`.
- **Disabled channel** → no-op, no HTTP call, no error.
- **X channel** → not registered. Adding it later: implement
  `MonthlyXChannel implements MonthlyBroadcastChannel`, then add it
  to the `broadcasts.monthly_channels` tag in `AppServiceProvider`.

## Related

- Weekly equivalent: [`weekly-choices-broadcast.md`](weekly-choices-broadcast.md)
- Schedules registered in `routes/console.php` under
  `// === BROADCAST SCHEDULES ===`.
