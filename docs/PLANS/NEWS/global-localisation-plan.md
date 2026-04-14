# Plan: Global UI Localisation (EN / PT-PT / PT-BR)

## Context

The news system already has locale-prefixed URLs, session persistence, and a header switcher. This plan expands localisation to all non-admin frontend pages without changing the URL structure for non-news routes.

**Decisions:**
- **News URLs unchanged** — `/en/news`, `/pt-pt/noticias`, `/pt-br/noticias` keep their locale prefixes.
- **Session-based locale for everything else** — `/releases`, `/events`, etc. stay the same URL; locale stored in `session('locale')`.
- **UI strings only** — game titles/descriptions from IGDB stay in English.
- **Vue i18n via Blade props** — translated strings passed as props; no `vue-i18n` dependency.
- **Scope** — all public frontend pages: homepage, releases, events, games/show, search, upcoming, lists, user pages, auth, header, footer.

---

## Predicted Problems

### 1. Session key rename (`news_locale` → `locale`)
`news_locale` appears in 3 app files and 7 test assertions. Must be renamed globally.

**Affected:**
- `app/Http/Middleware/SetNewsLocale.php` (writes)
- `app/Http/Controllers/HomepageController.php:99` (reads)
- `routes/web.php:87` (reads in `/news` redirect)
- `resources/views/components/header.blade.php:67-69` (reads)
- `tests/Feature/News/SetNewsLocaleMiddlewareTest.php` (5 assertions)
- `tests/Feature/HomepagePageTest.php` (1 assertion)

### 2. BCP-47 casing vs slug casing
`slugPrefix()` returns `'pt-br'` (lowercase), but `NewsLocaleEnum::PtBr->value` is `'pt-BR'`. `SetAppLocale` must use `NewsLocaleEnum::fromPrefix($slug)->value` before calling `app()->setLocale()`, so Laravel finds `lang/pt-BR.json` not `lang/pt-br.json`.

### 3. `SetAppLocale` + `SetNewsLocale` execution order
Global middleware runs before route middleware. `SetAppLocale` sets locale from session; on news routes `SetNewsLocale` then overrides with the route-specific locale and updates the session. Correct — no conflict.

### 4. Header switcher currently navigates to `$l->indexUrl()`
Replace with a dedicated `/locale/{prefix}` endpoint that sets session and `redirect()->back()`. User stays on current page; locale is updated.

### 5. Header switcher gated behind `@if($newsVisible)`
After decoupling from news URLs, the switcher should be visible on all pages regardless of news feature flag.

### 6. Vue interpolated string
`GlobalSearch.vue`: `'No games found for "{{ query }}"'` can't be a simple prop. Pass `noResults` prefix string and render `{{ noResults }} "{{ query }}"` in the template.

### 7. No lang files exist yet
Auth pages already use `__()` — they'll start translating the moment lang files exist. Must include auth strings in the initial lang files.

### 8. Missing translations fall back silently
`__('Some string')` returns the key if no translation found — good for incremental rollout but undetectable.

---

## Implementation Steps

### Step 1 — Rename `news_locale` → `locale` session key

Update all reads/writes in the files listed above. Run affected tests to confirm green.

---

### Step 2 — `SetAppLocale` global middleware

**New file:** `app/Http/Middleware/SetAppLocale.php`

```php
class SetAppLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $slug = session('locale');

        $locale = $slug
            ? NewsLocaleEnum::fromPrefix($slug)->value
            : NewsLocaleEnum::fromBrowserLocale($request->header('Accept-Language'))->value;

        app()->setLocale($locale);

        return $next($request);
    }
}
```

Register in `bootstrap/app.php`:
```php
$middleware->web(append: [\App\Http\Middleware\SetAppLocale::class]);
```

---

### Step 3 — `LocaleSwitchController` + `/locale/{prefix}` route

**New file:** `app/Http/Controllers/LocaleSwitchController.php`

```php
public function __invoke(Request $request, string $prefix): RedirectResponse
{
    NewsLocaleEnum::fromPrefix($prefix); // validates; 404 on unknown
    session(['locale' => $prefix]);
    return redirect()->back(fallback: route('homepage'));
}
```

**Route:**
```php
Route::get('/locale/{prefix}', LocaleSwitchController::class)
    ->where('prefix', 'en|pt-pt|pt-br')
    ->name('locale.switch');
```

---

### Step 4 — Update header locale switcher

**File:** `resources/views/components/header.blade.php`

1. Move locale dropdown outside `@if($newsVisible)`.
2. Change dropdown links from `$l->indexUrl()` to `route('locale.switch', $l->slugPrefix())`.
3. Fallback logic reads `session('locale')` (renamed from `news_locale`).

---

### Step 5 — Lang files

**Create:**
- `lang/en.json` — empty `{}` (English strings are their own keys)
- `lang/pt-PT.json`
- `lang/pt-BR.json`

**String inventory:**

Auth (already using `__()`):
```
"Log in", "Register", "Email", "Password", "Remember me",
"Forgot your password?", "Reset Password", "Confirm Password",
"Verify Email Address", "Profile", "Dashboard", "Log Out",
"Save", "Saved.", "Delete Account", "Cancel", "Confirm"
```

Header/nav:
```
"News", "Curated Lists", "Events", "My Games"
```

Homepage:
```
"Featured News", "Read Feature", "View All News",
"This Week's Choices", "See monthly", "No curated releases this week.",
"Latest Added Games", "Upcoming Releases", "No games releasing this week.",
"Game", "Platforms", "Release Date", "Added", "TBA", "No games added yet."
```

Core pages:
```
"Upcoming Games", "Events", "Most Wanted Games", "Seasoned Lists",
"Search games...", "Searching...", "Show more", "No games found for",
"No games available.", "About", "Steam Reviews", "Trailers",
"Screenshots", "Release Dates", "No summary available.",
"Highlights", "Indies", "All", "Jump to month...", "See all",
"See timeline", "Grid", "List", "Back to :year", "News"
```

---

### Step 6 — Replace hardcoded strings in views

**Phase A — Global chrome:**
- `resources/views/components/header.blade.php`
- `resources/views/components/footer.blade.php`
- `resources/views/components/releases-nav.blade.php`

**Phase B — Homepage components:**
- `resources/views/components/homepage/hero.blade.php`
- `resources/views/components/homepage/this-week-choices.blade.php`
- `resources/views/components/homepage/latest-added-table.blade.php`
- `resources/views/components/homepage/upcoming-releases.blade.php`

**Phase C — Core pages:**
- `resources/views/releases/yearly.blade.php`
- `resources/views/releases/index.blade.php`
- `resources/views/events/index.blade.php`
- `resources/views/games/show.blade.php`
- `resources/views/search/results.blade.php`
- `resources/views/upcoming/index.blade.php`

**Phase D — User pages + auth:**
- Auth views (lang files activate existing `__()` calls automatically)
- `resources/views/user-games/`, `resources/views/user-lists/`
- `resources/views/profile/`

---

### Step 7 — Vue component props (GlobalSearch)

Add props to `resources/js/components/GlobalSearch.vue`:
```js
const props = defineProps({
    placeholder: { type: String, default: 'Search games...' },
    searching:   { type: String, default: 'Searching...' },
    showMore:    { type: String, default: 'Show more' },
    noResults:   { type: String, default: 'No games found for' },
    tba:         { type: String, default: 'TBA' },
})
```

Blade mount points pass translated strings:
```blade
<global-search
    :placeholder="__('Search games...')"
    :searching="__('Searching...')"
    :show-more="__('Show more')"
    :no-results="__('No games found for')"
    :tba="__('TBA')"
/>
```

---

## Critical Files

| Action | File |
|--------|------|
| Modify | `app/Http/Middleware/SetNewsLocale.php` |
| Modify | `app/Http/Controllers/HomepageController.php` |
| Modify | `routes/web.php` |
| Modify | `resources/views/components/header.blade.php` |
| Modify | `tests/Feature/News/SetNewsLocaleMiddlewareTest.php` |
| Modify | `tests/Feature/HomepagePageTest.php` |
| Create | `app/Http/Middleware/SetAppLocale.php` |
| Modify | `bootstrap/app.php` |
| Create | `app/Http/Controllers/LocaleSwitchController.php` |
| Create | `lang/en.json` |
| Create | `lang/pt-PT.json` |
| Create | `lang/pt-BR.json` |
| Modify | ~20 Blade view files (phases A–D) |
| Modify | `resources/js/components/GlobalSearch.vue` |

---

## Tests

**Step 1:** Re-run `SetNewsLocaleMiddlewareTest` + `HomepagePageTest` after session key rename.

**Step 2 — `tests/Feature/SetAppLocaleMiddlewareTest.php`:**
- Session `locale=pt-pt` → `app()->getLocale()` is `'pt-PT'` on any page
- Session `locale=pt-br` → `'pt-BR'`
- No session, `Accept-Language: pt-PT` → `'pt-PT'`
- No session, no header → `'en'`

**Step 3 — `tests/Feature/LocaleSwitchTest.php`:**
- `GET /locale/pt-pt` → session `locale=pt-pt` + redirect
- `GET /locale/en` → session `locale=en`
- `GET /locale/invalid` → 404

**Full suite after each phase:**
```bash
php artisan test --compact
vendor/bin/pint --dirty --format agent
```