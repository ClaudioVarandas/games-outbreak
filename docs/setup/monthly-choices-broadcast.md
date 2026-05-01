# Monthly "Next Month's Choices" broadcast

Automated mid-/end-of-month post of curated monthly releases to
Telegram, sharing the same data source as the homepage's curated
list. Mirrors the weekly broadcast (see
[`weekly-choices-broadcast.md`](weekly-choices-broadcast.md)) with
two distinct fires per month, a switchable window (upcoming or
current), and automatic chunking of long lists.

- **When:**
  - **23rd of each month, 09:00 UTC** — `PREVIEW` post
  - **28th of each month, 09:00 UTC** — final post
- **What:** Games whose pivot `release_date` falls in the **upcoming**
  calendar month by default (`startOfMonth->addMonth()` through that
  month's `endOfMonth`). Pass `--current` to target the **current**
  calendar month instead — same window math but `startOfMonth` of
  "now".
- **Where from:** The active yearly system `GameList` (same source as
  the weekly broadcast).
- **Channels:** Telegram only. **X is intentionally not wired** —
  pattern is in place to add a `MonthlyXChannel` later.
- **List size:** No hard per-message cap. A 200-game **safety** cap
  is applied at the collector to avoid pathological queries; in
  practice the formatter splits the rendered list into multiple
  Telegram `sendMessage` calls when it would exceed 3800 chars (see
  *Chunking* below).

## Header copy is window-derived

The formatter compares the payload's `windowStart` against `now`
(both stamped on `MonthlyChoicesPayload`) and picks the header
automatically:

| Window relative to now      | Header base                  |
|-----------------------------|------------------------------|
| Same calendar month         | `This Month's Choices`       |
| Next calendar month         | `Next Month's Choices`       |
| Any other month             | `<F Y> Choices` (e.g. `September 2026 Choices`) |

`--preview` then prepends ` — PREVIEW — ` to whichever base applies:

```
*🎮 Games Outbreak — Next Month's Choices*
*🎮 Games Outbreak — PREVIEW — Next Month's Choices*
*🎮 Games Outbreak — This Month's Choices*
*🎮 Games Outbreak — September 2026 Choices*
*🎮 Games Outbreak — PREVIEW — September 2026 Choices*
```

The CLI flags select which window to query:

- *(none)* — upcoming month (default; what the schedule fires)
- `--current` — current calendar month
- `--month=YYYY-MM` — explicit month (e.g. `2026-09`)
- `--current` and `--month` are mutually exclusive
- `--preview` is orthogonal to all three

Because the header is window-derived, `--month=2026-04` while it is
April 2026 produces "This Month's Choices" automatically; same value
on March 2026 produces "Next Month's Choices"; on January 2026 or
later than May produces "April 2026 Choices".

The flags travel via:

- `MonthlyChoicesPayload::$isPreview`, `$now`
- `BroadcastMonthlyChoicesJob(isPreview: true, monthOverride: '2026-09')`
- `monthly-choices:broadcast --preview --month=2026-09`

The schedule still fires upcoming-month broadcasts; `--current` and
`--month` are intended for ad-hoc CLI runs (e.g., posting "this
month's slate" on day 1, or backfilling a missed fire).

## Chunking

The Telegram `sendMessage` text limit is 4096 chars. When the rendered
list would exceed `MonthlyTelegramMessageFormatter::MAX_CHARS_PER_MESSAGE`
(3800 chars, leaving headroom), the formatter splits at line
boundaries into multiple messages. Each chunk is sent as its own
`sendMessage` call. Continuation chunks include a `· Part X/N`
suffix on the subtitle line; only the **last** chunk carries the
`[See the full list →]` CTA footer.

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
php artisan monthly-choices:broadcast --dry-run --current
php artisan monthly-choices:broadcast --dry-run --month=2026-09
php artisan monthly-choices:broadcast --dry-run --channel=telegram

# Live (Telegram is the default channel)
php artisan monthly-choices:broadcast
php artisan monthly-choices:broadcast --preview
php artisan monthly-choices:broadcast --current
php artisan monthly-choices:broadcast --current --preview
php artisan monthly-choices:broadcast --month=2026-09
php artisan monthly-choices:broadcast --month=2026-09 --preview
```

Defaults differ from the weekly command:

- `--channel=telegram` is the default (vs weekly's `all`) since X is
  not wired for monthly.
- `--preview` and `--current` are flags, not options — pass each on
  its own to enable.

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
  list, 200-game safety cap, year-end rollover (Dec → Jan), preview
  flag passthrough, `isCurrent` flag passthrough.
- `MonthlyTelegramMessageFormatterTest` — header in all four flag
  combinations (PREVIEW/FINAL × current/upcoming), monthly subtitle
  (`_Month Year_`), MarkdownV2 escaping, empty payload, single-message
  rendering, multi-chunk rendering with parts label and CTA only on
  the last chunk.
- `BroadcastMonthlyChoicesJobTest` — posts to Telegram, PREVIEW
  marker flows through, `isCurrent` targets the current month, never
  hits X, skips silently on empty month, throws when all channels
  fail, `--channel` scoping, chunked messages all respect the
  Telegram 4096-char hard limit.
- `BroadcastMonthlyChoicesCommandTest` — dry-run prints & sends
  nothing, `--preview` tags output, `--current` switches the window,
  live sends once, unknown `--channel` exits with code 2,
  `--channel=telegram` is the default, `--channel=x` rejected while
  X is unwired.

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
