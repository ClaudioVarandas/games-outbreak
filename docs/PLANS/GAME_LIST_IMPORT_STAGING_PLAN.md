# Game List Import — Staging List + Promote (increment 2)

## Context

Increment 1 (implemented): `/import-game-list` skill researches pasted release lists and POSTs to `POST /api/v1/import/list-items`, which attaches games **directly to the live yearly list**. User now wants a quarantine in prod: imports land on a hidden **staging list**; admin reviews in the existing admin UI, then **promotes** approved games; promote routes **per-game by year** (a 2027 date goes to the 2027 list), reusing the existing event→yearly sync machinery. Reject = existing remove button.

Key constraint discovered: staging must NOT be a `yearly`-type list — `ReleasesController` (`/releases/{year}`) picks yearly system lists by `whereYear('start_at')` with **no** `is_active`/`is_public` filter, so a same-year yearly staging list would collide/leak. Solution: new `import` list type, which no public query touches (`/lists/{type}/{slug}` guards `is_public`+`is_active` for non-admins).

## Existing building blocks (reuse)

- `EventYearlySyncService::plan()/apply()` (`app/Services/EventYearlySyncService.php`) — already source-agnostic (takes any `GameList`; fallback year = `getEventTime() ?? start_at ?? now()`). `apply()` routes each game to the yearly list for its date-year / TBA `release_year`, auto-creates missing year lists, dedupes via `GameListSyncService::fillMissing()` (fills only empty fields), transactional, returns `{created_years, inserted, filled, skipped, errors, per_year}`. **No changes needed to it.**
- `GameListSyncService::insertGame()` — auto-derives `platform_group`; staging pivots not having one is fine.
- `GameListImportService::attachGame()` (built in increment 1) — works with any list type.
- Admin edit page Alpine `fetch()` pattern with `data-*-url` + `__GAME_ID__` placeholder (`resources/views/components/admin/system-lists/game-grid.blade.php`, `removeGame` / `syncEventFromIgdb` in `resources/views/admin/system-lists/edit.blade.php`).
- Admin routes group in `routes/web.php` (~197–255), route names `admin.system-lists.games.*`.

## Changes

### 1. `ListTypeEnum` — new case (`app/Enums/ListTypeEnum.php`)

`case IMPORT = 'import';` added to every match arm: `label()` → `'Import Staging'`, `colorClass()` → amber variant, `isUniquePerUser()` → false, `isSystemListType()` → true, `fromValue()`, `toSlug()`, `fromSlug()`. Manual creation stays blocked (`storeSystemList` whitelists yearly/seasoned/events explicitly). During implementation, grep other `match ($this` / exhaustive matches on `ListTypeEnum` (e.g. views, GameListFactory states) for arms that need the new case. Update `tests/Unit/ListTypeEnumTest.php`.

### 2. Migration + model

- New migration: `import_target_list_id` on `game_lists` — nullable, `foreignId ... constrained('game_lists')->nullOnDelete()`.
- `GameList`: add to `$fillable`; relations `importTargetList(): BelongsTo` and `importStagingList(): HasOne` (inverse, FK `import_target_list_id`).

### 3. API — imports land on staging (`app/Http/Controllers/Api/GameListImportController.php`)

`listItems()`:
- Resolve + validate the **target** list exactly as today (yearly/seasoned system list).
- `firstOrCreate` the staging list: slug `{target-slug}-import`, `list_type => ListTypeEnum::IMPORT`, `is_system => true`, `is_public => false`, `is_active => false`, `user_id`/`start_at` copied from target, `import_target_list_id => target->id`, name `"Import: {target name}"`.
- Attach items to the **staging** list via `GameListImportService::attachGame()` (unchanged).
- Response gains `staging_list_slug` and `review_url` (admin edit URL for the staging list).

`check()`: per existing game also report `on_staging_list` (member of the target's staging list) so the skill short-circuits games already pending review.

### 4. Promote backend

- `GameListImportService::promoteFromStaging(GameList $staging, array $gameIds): array` — guards `$staging->list_type === IMPORT`; delegates to `EventYearlySyncService::apply($staging, $gameIds)`; then detaches every processed game **without an error** from the staging list (inserted, filled, and skipped-duplicates all leave staging); returns the apply() result + `detached` count.
- `AdminListController::promoteGames(Request $request, EventYearlySyncService|GameListImportService ..., string $type, string $slug): JsonResponse` — import lists only (422 otherwise); body `game_ids[]` or `all => true` (all = every game on the list); returns JSON summary.
- Route: `POST .../games/promote` → name `admin.system-lists.games.promote`, in the existing admin group.

### 5. Admin UI

- `AdminListController::systemLists()`: add `$importLists = GameList::where('is_system', true)->where('list_type', ListTypeEnum::IMPORT)->withCount('games')->with('importTargetList')->orderByDesc('created_at')->get();` → new "Import Staging" section in `resources/views/admin/system-lists/index.blade.php` (rendered only when non-empty; card shows game count + target list name, links to edit page).
- `resources/views/admin/system-lists/edit.blade.php`: when list type is IMPORT — header "Promote all" button (confirm dialog → fetch promote route with `all: true` → alert summary → reload), banner naming the target list.
- `resources/views/components/admin/system-lists/game-grid.blade.php`: per-game "Promote" button for IMPORT lists following the existing `__GAME_ID__` data-url + Alpine fetch pattern (like Remove). Remove button acts as reject (no change). The existing edit modal keeps working for fixing pivot data before promoting.

### 6. Skill + docs

- `.claude/skills/import-game-list/SKILL.md`: summary section now reports "staged for review" + the `review_url`; describe promote step; `check` handling of `on_staging_list`.
- Update `docs/SPEC/GAME_LIST_IMPORT_SPEC.md` (staging flow section) and copy this plan to `docs/PLANS/GAME_LIST_IMPORT_STAGING_PLAN.md`.

### 7. Tests (Pest)

- `tests/Unit/ListTypeEnumTest.php` — new case coverage.
- `tests/Feature/Api/GameListImportApiTest.php` — update: items attach to auto-created staging list (target untouched); staging list created with correct flags/link/slug; re-import → `already_on_list` against staging; `check` reports `on_staging_list`.
- New `tests/Feature/Admin/PromoteImportListTest.php` (or sibling naming): dated 2026 game → target 2026 list; TBA `release_year` 2027 game → auto-created 2027 list; game already on target with empty fields → `fillMissing` + detached; promoted games detached from staging; error rows stay; non-import list → 422; guests/non-admin blocked (route middleware).
- Public safety: guest request to `/lists/import/{staging-slug}` is denied (GameListController guard).

### Out of scope

Auto-deleting emptied staging lists, promote preview UI (plan() exists if wanted later), notifications.

## Order of work

1. Enum case + enum test.
2. Migration + GameList fillable/relations.
3. API staging redirect (controller + tests).
4. `promoteFromStaging` service method + admin controller action + route (+ tests).
5. Admin index section + edit/game-grid buttons.
6. Skill + spec/plan docs.
7. `vendor/bin/pint --dirty --format agent`; `XDEBUG_MODE=off php artisan test --compact` on touched test files.

## Verification

- Full targeted test run (API import tests, promote tests, enum test, admin list tests from increment 1 still green).
- Local e2e: point `IMPORT_API_BASE_URL` at local, import a 3-game slice → confirm staging list appears in admin index, games NOT on public `/releases/{year}`, promote all → games land in correct year month-buckets, staging emptied.
