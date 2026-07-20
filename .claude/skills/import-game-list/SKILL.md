---
name: import-game-list
description: >
  Import a raw pasted list of game releases (month headers + game names) into a
  yearly/seasoned system list on the games-outbreak site. Researches each game
  (IGDB match via local artisan command, release date verified against a second
  source, platforms), then POSTs verified rows to the token-authenticated
  /api/v1/import endpoints. Use this skill whenever the user pastes a list of
  game names/release roundups and wants them added to the site, or says things
  like "import this list", "add these games to the 2026 list", or invokes
  /import-game-list.
---

# Import Game List

Turn a raw pasted release list into verified entries on a yearly/seasoned system list.

## Inputs

1. **Target list slug** (e.g. `releases-2026`) — ask if not given.
2. **The raw list** — pasted text with month headers and comma-separated game names.
3. Env (read from the project `.env`): `IMPORT_API_BASE_URL`, `IMPORT_API_TOKEN`.
   Both must be set; stop and tell the user if missing.

```bash
BASE_URL=$(grep '^IMPORT_API_BASE_URL=' .env | cut -d= -f2-)
TOKEN=$(grep '^IMPORT_API_TOKEN=' .env | cut -d= -f2-)
```

## Workflow

### 1. Parse the list

- Split on newlines, then commas. Trim each entry.
- Month headers (`January` … `December`) set the *month hint* for entries that follow.
  Themed headers map too: `Halloween` → October, `Intro` → ignore (not a game).
- Strip parenthetical aliases into alt-names: `Grand Theft Auto 6 (GTA 6)` →
  primary `Grand Theft Auto 6`, alt `GTA 6`.
- The target list's year (from its slug/name) is the *expected year*.
- Build a working table: `raw_name | alt_names | month_hint`.

### 2. Check what already exists (short-circuit)

POST all names to the check endpoint (batches of ≤100):

```bash
curl -s -X POST "$BASE_URL/api/v1/import/check" \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"list_slug":"<target-slug>","items":[{"name":"Phantom Blade Zero"}, ...]}'
```

Rows with `on_target_list: true` are done — move them straight to the summary
("already on list"). Rows with `on_staging_list: true` are already imported and
awaiting admin review — report them as "pending review", do not re-research.
Rows with `exists: true` but on neither list keep their `igdb_id` (skip IGDB
search for them, still verify the date).

### 3. Match each remaining game on IGDB

```bash
XDEBUG_MODE=off php artisan games:igdb-search "<name>" --limit=5 --year=<expected-year>
```

Pick the best candidate by: name similarity (primary or alt name), expected year
proximity, `game_type` (prefer "Main Game" unless the list entry implies DLC/
expansion/remaster). If nothing plausible:
- Retry once with an alt-name or a web search for the official title
  (announcements often use working titles).
- Still nothing → **skip**, report as "not importable yet (no IGDB record)".
  Never invent an igdb_id; never create games another way.

### 4. Verify the release date (IGDB + 1 source rule)

For each matched game:

- **IGDB date** comes from the candidate JSON (`first_release_date`, `release_dates`).
- **Second source**, in order of preference:
  1. Steam store page when `steam_app_id` present:
     `curl -s "https://store.steampowered.com/api/appdetails?appids=<id>&filters=release_date"`
  2. One targeted web search (`"<game name>" release date`) — publisher post,
     trusted outlet, or trailer description.
- Decide:
  - IGDB + second source agree on the month → `confidence: high`.
  - Only IGDB has a date → use it, `confidence: medium`, `note: "single-source date"`.
  - Sources conflict → prefer the most recent evidence, `confidence: low`, note the conflict.
  - No date anywhere (or only a bare year/quarter) → `is_tba: true` with
    `release_year` when known; date stays null.
- Sanity-check against the month hint from the pasted list; a mismatch is worth a
  note, not an automatic override (the pasted list may be stale).

### 5. Platforms

Use the IGDB candidate's platform igdb_ids. Do not guess beyond IGDB; the
backend falls back to the game's active platforms when the array is empty.

### 6. POST the import (batches of ≤10)

```bash
curl -s -X POST "$BASE_URL/api/v1/import/list-items" \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d @payload.json
```

Payload shape:

```json
{
  "list_slug": "releases-2026",
  "items": [
    {
      "igdb_id": 123,
      "release_date": "2026-10-15",
      "is_tba": false,
      "platforms": [6, 167, 169],
      "confidence": "high",
      "sources": ["igdb", "steam"],
      "note": null
    },
    {
      "igdb_id": 456,
      "is_tba": true,
      "release_year": 2026,
      "confidence": "medium",
      "sources": ["igdb"],
      "note": "no date on any source"
    }
  ]
}
```

Statuses returned per item: `attached`, `already_on_list`, `game_not_found`, `failed`.
The response also carries `staging_list_slug` and `review_url`.

**Nothing goes live from this POST.** Items land on a hidden staging list
(`{target-slug}-import`) that only admins can see. Games reach the real yearly
lists when the admin promotes them (per game or all) from the staging list's
edit page; promote routes each game to the yearly list matching its release
year. Remove on that page = reject.

### 7. Final summary (always print)

One markdown table with every row from the original paste:

| Game | Result | Month | Confidence | Follow-up |
|------|--------|-------|------------|-----------|

Group order: staged (by month) → TBA → pending review (from earlier runs) →
already on target list → skipped (no IGDB) → failed. End with an explicit
action line: **"Review & promote at <review_url from the response>"** and call
out medium/low-confidence rows to double-check before promoting.

## Rules

- Batch IGDB searches sequentially (the artisan command already rate-limits).
- Keep web searches targeted: only for unmatched names and missing/conflicting dates.
- Never bypass the API to write to the database directly.
- Imports are quarantined on the staging list; nothing is public until the
  admin promotes it. Confidence flags + notes are the review trail.
