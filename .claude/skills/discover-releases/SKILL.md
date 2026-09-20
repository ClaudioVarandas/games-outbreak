---
name: discover-releases
description: >
  Discover game releases for a date window (month, week, or explicit range) by
  sweeping the discovery source registry — release calendars, press sites,
  YouTube channels, X accounts — curating by editorial presence, verifying
  dates, resolving IGDB ids, and staging results through the import API for
  admin review. Use this skill whenever the user asks to discover/research the
  releases of a month or window, says things like "discover August releases",
  "what's coming out next month, add it to the site", or invokes
  /discover-releases.
---

# Discover Releases

Automates the monthly release-research routine: multi-source media sweep →
editorial curation → verified staging. Nothing goes live — the admin promotes
from the staging page.

## Inputs

1. **Window** — `YYYY-MM`, `YYYY-MM-DD..YYYY-MM-DD`, or natural phrasing
   ("next week", "first half of October") which you normalize to a from..to
   range before starting. Ask if missing.
2. **Target list slug** — the yearly system list's slug is just the year
   (e.g. `2026`, named "Game Releases 2026"). Infer from the window's year
   when obvious, confirm otherwise. `list-items` returns "Target list not
   found" for a wrong slug and writes nothing.
3. Env (from the project `.env`): `IMPORT_API_BASE_URL`, `IMPORT_API_TOKEN`.
   Both must be set; stop and tell the user if missing.

```bash
BASE_URL=$(grep '^IMPORT_API_BASE_URL=' .env | cut -d= -f2-)
TOKEN=$(grep '^IMPORT_API_TOKEN=' .env | cut -d= -f2-)
```

## Workflow

### 1. Load the source registry

```bash
curl -s "$BASE_URL/api/v1/sources?purpose=releases" -H "Authorization: Bearer $TOKEN"
```

The registry drives every sweep below — no open-web trawling. Keep each
source's `id`: you need it for staging attribution (`source_ids`) and the
run report. Group by `kind`: `calendar`, `press`, `youtube`, `x`.

### 2. Calendar sweep (the backbone, ~90% of coverage)

Fetch each `calendar` source's `url` (WebFetch; Jina Reader
`https://r.jina.ai/<url>` for bot-walled sites like Gematsu/Push Square/
Nintendo Life/Pure Xbox). These pages are date-organized: extract every game
inside the window — name, claimed date, platforms.

### 3. Press sweep

One targeted search per `press` source:
`site:<locator> game releases <window phrase>` (adapt phrasing to the window:
"August 2026 releases", "games releasing this week"). Read the best roundup
hit per outlet; extract the same fields.

### 4. YouTube sweep (metadata only, no watching)

Per `youtube` source, walk recent uploads in the window (handle is the
`locator`). Prefer the YouTube Data API pattern used by
`YoutubeDataService::recentChannelVideos()` via a targeted
`site:youtube.com/<handle>` search or the channel's videos page — read titles
and descriptions for roundups/trailers/date announcements.

### 5. X sweep (best effort)

Per `x` source, one web search for window-relevant release announcements from
that handle. Skip silently when nothing surfaces.

### 6. Corroboration table (editorial presence rule)

Build: game | sources that mention it (registry ids + kind) | date each claims.

- **2+ independent sources → candidate.**
- **1 source → candidate only if clearly notable** (known franchise, major
  publisher) — your editorial judgment replaces the user's manual filtering.
- Conflicting dates → prefer the most recent evidence; keep the conflict for
  the staging `note`.

### 7. IGDB cross-check (safety net, not a source)

```bash
XDEBUG_MODE=off CACHE_STORE=array php artisan games:release-window <window>
```

Ranked by `hypes`. Flag high-hype titles the media sweep missed — add them as
candidates only if you judge them notable. Sanity-check candidate dates
against IGDB's. A targeted web search is allowed only to verify a specific
candidate's date.

### 8. Site dedupe

`POST $BASE_URL/api/v1/import/check` with all candidates (batches ≤100).
Drop rows with `on_target_list: true` (report "already on list") and
`on_staging_list: true` (report "pending review"); keep returned `igdb_id`s.

### 9. Resolve IGDB ids

Per remaining candidate:

```bash
XDEBUG_MODE=off php artisan games:igdb-search "<name>" --limit=5 --year=<window year>
```

No plausible IGDB record → skip + report (site invariant: never invent ids,
never create games outside IGDB).

### 10. Stage (batches of ≤10)

`POST $BASE_URL/api/v1/import/list-items` — per item:

- `igdb_id`, `release_date` (verified) or `is_tba` + `release_year`,
  `platforms` (IGDB ids)
- `confidence`: `high` = 2+ sources agree on the date; `medium` = single
  source; `low` = conflicting evidence
- `sources`: evidence kinds actually used — `press`, `youtube`, `x`, `igdb`,
  `steam`, `web`
- `source_ids`: registry ids of the sources that surfaced this game (powers
  yield attribution — promote/reject feeds their score)
- `note`: name the outlets, e.g. `"IGN + Gematsu roundups; GameSpot says Oct 12"`

### 11. Run report

```bash
curl -s -X POST "$BASE_URL/api/v1/sources/run-report" \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"window":"<window>","sources":[{"id":1,"items_found":12,"fetch_ok":true}, ...]}'
```

One row per registry source swept: `items_found` = games it surfaced,
`fetch_ok: false` when the fetch/search failed.

### 12. Final summary (always print)

Table grouped: staged (by date) → already on target list → pending review →
no-IGDB-record → judgment-dropped (with one-line reason).

Then **suggested source additions**: if fallback searches surfaced a notable
game no registry source covered, propose the missing outlet via
`POST $BASE_URL/api/v1/sources/propose` with `evidence` naming what it had —
it lands pending on `/admin/discovery-sources`.

End with: **"Review & promote at <review_url>"** (from the list-items
response) and call out medium/low-confidence rows.

## Rules

- Keep total staged ≤ ~40 per window; this is curation, not a dump.
- Targeted searches only — one per source per sweep; no exhaustive crawling.
- Never bypass the API to write to the database directly.
- Everything stays quarantined on the staging list until the admin promotes.
