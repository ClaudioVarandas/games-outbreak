---
name: add-source
description: >
  Research raw links (news sites, release calendars, YouTube channels, X
  accounts) and propose them as discovery sources on the games-outbreak site
  via the token-authenticated /api/v1/sources endpoints. Sources land as
  "pending" for admin confirmation — this skill never activates anything.
  Use this skill whenever the user throws links at you to add as sources,
  says "add this source / these sources", asks to enrich sources flagged
  "needs enrichment", or invokes /add-source.
---

# Add Discovery Source

Turn raw links into classified, evidence-backed source proposals in the
discovery source registry.

## Inputs

1. **One or more URLs** (or a request to enrich existing `needs_enrichment` rows).
2. Env (read from the project `.env`): `IMPORT_API_BASE_URL`, `IMPORT_API_TOKEN`.
   Both must be set; stop and tell the user if missing.

```bash
BASE_URL=$(grep '^IMPORT_API_BASE_URL=' .env | cut -d= -f2-)
TOKEN=$(grep '^IMPORT_API_TOKEN=' .env | cut -d= -f2-)
```

## Workflow

### 1. Check the registry first

```bash
curl -s "$BASE_URL/api/v1/sources" -H "Authorization: Bearer $TOKEN"
```

The list returns active sources with their `locator`. If a given link obviously
matches an existing locator, report "already registered" and skip it — the
propose endpoint dedupes anyway, but skipping saves the research.

### 2. Research each link

Fetch the page (WebFetch / Jina Reader `https://r.jina.ai/<url>` for bot-walled
sites) and decide:

- **kind** — one of:
  - `youtube` — a channel (`youtube.com/@handle`, `/channel/...`). Resolve to
    the canonical `@handle` (visible on the channel page).
  - `x` — an X/Twitter profile.
  - `calendar` — a page that IS a date-organised release list (upcoming
    games browse pages, release-date tags/schedules).
  - `press` — a news outlet / blog; the domain is the source, not one article.
    If the link is a single article, the source is the outlet's domain.
- **purpose** — `releases`, `news`, or `both`: does the source publish release
  dates/calendars, news coverage, or both?
- **name** — the outlet/channel's real display name.
- **confidence** — `high` when the test extraction clearly yields games/news
  with dates; `medium` when plausible but thin; `low` when unsure.
- **evidence** — one or two sentences naming what you actually found, e.g.
  `"Date-organised upcoming list; extracted 14 titles with dates for August"`.

A test extraction is mandatory for `calendar`/`press`: read the fetched page
and confirm it actually yields game names + dates (calendar) or dated articles
(news). If the page yields nothing useful, tell the user and do not propose it.

### 3. Propose (batches of ≤20)

```bash
curl -s -X POST "$BASE_URL/api/v1/sources/propose" \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d @payload.json
```

Payload shape:

```json
{
  "sources": [
    {
      "url": "https://www.youtube.com/@SomeChannel",
      "kind": "youtube",
      "purpose": "releases",
      "name": "Some Channel",
      "confidence": "high",
      "evidence": "Weekly upcoming-releases roundups; latest video lists 12 dated games."
    }
  ]
}
```

Per-item statuses: `created` (now pending), `duplicate` (locator already on
file — includes rejected sources: a rejected locator staying on file is
intentional, do not try to work around it), `invalid`.

### 4. Enriching flagged rows

When asked to enrich `needs_enrichment` sources: the admin page lists them.
Research the stored `url` exactly as in step 2, then tell the user the
corrected kind/purpose/name so they can edit the row on
`/admin/discovery-sources` — there is no agent endpoint for editing existing
rows on purpose (confirmation stays with the admin).

### 5. Final summary (always print)

| Link | Status | Kind | Purpose | Confidence | Evidence |
|------|--------|------|---------|------------|----------|

End with: **"Confirm the pending sources at <review_url from the response>"**.

## Rules

- Never propose a source you could not fetch or whose content you did not
  verify — evidence must describe what was actually seen.
- One targeted fetch per link; no crawling.
- Never bypass the API to write to the database directly.
- Proposals are always `pending`; only the admin confirms/activates.
