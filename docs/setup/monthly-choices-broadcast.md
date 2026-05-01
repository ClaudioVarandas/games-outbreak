# Monthly Choices broadcast

Automated mid-/end-of-month post of curated monthly releases to
Telegram, sharing the same data source as the homepage's curated
list. Mirrors the weekly broadcast (see
[`weekly-choices-broadcast.md`](weekly-choices-broadcast.md)) with
two scheduled fires per month, a switchable window (upcoming,
current, or arbitrary `YYYY-MM`), automatic chunking of long lists,
and a header that derives from the window relative to "now".

- **When (scheduled):**
  - **23rd of each month, 09:00 UTC** — `PREVIEW` post
  - **28th of each month, 09:00 UTC** — final post
- **What:** Games whose pivot `release_date` falls in a **calendar
  month window**:
  - default → upcoming month (`now->startOfMonth()->addMonth()` through
    that month's `endOfMonth`) — what the schedule fires
  - `--current` → current month (`now->startOfMonth()` through
    `endOfMonth`)
  - `--month=YYYY-MM` → that explicit month
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
app/Services/MonthlyChoicesCollector.php
    forCurrentMonth($now, $isPreview)
    forUpcomingMonth($now, $isPreview)
    forMonth(CarbonImmutable $monthStart, $now, $isPreview)
    (200-game safety cap; eager-loads platforms; orderByRaw release_date asc)

app/Services/MonthlyChoicesPayload.php
    readonly DTO — windowStart + windowEnd + games + ctaUrl + now + isPreview

app/Services/Broadcasts/
├── MonthlyChoicesBroadcaster.php
│   orchestrator + parseMonthOverride('YYYY-MM'); per-channel error isolation
├── Channels/MonthlyBroadcastChannel.php       interface
├── Channels/MonthlyTelegramChannel.php        Telegram impl (reuses TelegramClient)
├── Formatters/MonthlyTelegramMessageFormatter.php
│   MarkdownV2; window-vs-now header; chunks at MAX_CHARS_PER_MESSAGE (3800)
└── Exceptions/BroadcastFailedException.php    (shared with weekly)

app/Jobs/BroadcastMonthlyChoicesJob.php
    ShouldQueue, tries=3, backoff=[60,300,900]
    ($onlyChannel, $isPreview, $isCurrent, $monthOverride)

app/Console/Commands/BroadcastMonthlyChoicesCommand.php
    monthly-choices:broadcast --dry-run --channel= --preview --current --month=

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
- `--channel=` and `--month=` take values (e.g. `--month=2026-09`);
  `--preview` and `--current` are flags.
- `--current` and `--month` are mutually exclusive; combining them
  exits with code 2.

## How to test

### 1. Dry-run

Renders and prints the output for each registered channel; makes
**no** HTTP calls. Works without any Telegram credentials.

```
php artisan monthly-choices:broadcast --dry-run
php artisan monthly-choices:broadcast --dry-run --current
php artisan monthly-choices:broadcast --dry-run --month=2026-09
```

Expected first line:

- default → `Upcoming window: YYYY-MM-01 → YYYY-MM-DD · N games`
- `--current` → `Current window: YYYY-MM-01 → YYYY-MM-DD · N games`
- `--month=YYYY-MM` → `Override window: YYYY-MM-01 → YYYY-MM-DD · N games`

Each is followed by the rendered Telegram block. Adding `--preview`
prepends the PREVIEW marker to the header and appends ` · PREVIEW`
to the window summary line.

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

- `MonthlyChoicesCollectorTest` — current / upcoming / arbitrary month
  (`forMonth`), empty list, 200-game safety cap, year-end rollover
  (Dec → Jan), preview flag passthrough, `now` reference stamped on
  payload.
- `MonthlyTelegramMessageFormatterTest` — header derivation across
  the three window kinds (current / next / arbitrary) × PREVIEW
  on/off, monthly subtitle (`_Month Year_`), MarkdownV2 escaping,
  empty payload, single-message rendering, multi-chunk rendering
  with `· Part X/N` label and CTA only on the last chunk.
- `BroadcastMonthlyChoicesJobTest` — posts to Telegram, PREVIEW
  marker flows through, `isCurrent` targets the current month,
  `monthOverride='YYYY-MM'` targets the explicit month with the
  derived header, never hits X, skips silently on empty month,
  throws when all channels fail, `--channel` scoping, chunked
  messages all respect the Telegram 4096-char hard limit.
- `BroadcastMonthlyChoicesCommandTest` — dry-run prints & sends
  nothing, `--preview` tags output, `--current` switches the window,
  `--month=YYYY-MM` switches the window and tags dry-run output as
  `Override window`, malformed `--month` (e.g. `2026-13`, `banana`)
  exits with code 2, `--month` + `--current` together rejected,
  unknown `--channel` exits with code 2, `--channel=telegram` is the
  default, `--channel=x` rejected while X is unwired.

## Behavior notes

- **Empty month** → skip silently;
  `Log::info('monthly-choices.skipped', {reason: 'empty-window', is_preview, is_current, month_override, ...})`.
  No post, no retry.
- **Both scheduled fires on same data** → if the curated list has
  not changed between the 23rd and 28th, the FINAL message is
  content-identical to the PREVIEW except for the header.
- **All channels fail** → `BroadcastFailedException` bubbles up; job
  retries with backoff; final failure logs
  `monthly-choices.broadcast.failed` with `is_preview`, `is_current`,
  `month_override` for triage.
- **Disabled channel** → no-op, no HTTP call, no error.
- **X channel** → not registered. Adding it later: implement
  `MonthlyXChannel implements MonthlyBroadcastChannel`, then add it
  to the `broadcasts.monthly_channels` tag in `AppServiceProvider`.
- **Backfilling a missed fire** → run
  `php artisan monthly-choices:broadcast --month=YYYY-MM` (add
  `--preview` to mirror the 23rd run). Idempotency is **not**
  enforced; running twice posts twice.

## Related

- Weekly equivalent: [`weekly-choices-broadcast.md`](weekly-choices-broadcast.md)
- Schedules registered in `routes/console.php` under
  `// === BROADCAST SCHEDULES ===`.
