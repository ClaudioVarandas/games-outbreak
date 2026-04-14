# Localized Content Strategy for Laravel 12 Monolith

## Scope

This document focuses only on **frontend behavior and routes** in a Laravel 12 monolith.

Backend storage and database localization stay unchanged for now.

---

## Goal

Present localized content in a predictable, SEO-friendly, and user-friendly way without changing backend structures.

The strategy should:

- keep locale visible in the URL
- respect explicit user choice
- use browser language only as a hint
- normalize locale variants correctly:
  - `en-US -> en`
  - `pt-PT -> pt-pt`
  - `pt-BR -> pt-br`
- avoid aggressive redirects
- preserve distinct Portuguese regional variants

---

## Supported Locales

Your app supports exactly:

```php
$supportedLocales = ['en', 'pt-pt', 'pt-br'];
$defaultLocale = 'en';
```

These must be treated as separate frontend locales.

Important:
- `pt-pt` and `pt-br` are different supported locales
- never convert `pt-br` into `br`
- never collapse both into plain `pt` if region-specific content matters

---

## Route Strategy

Use locale-prefixed routes:

- `/en/...`
- `/pt-pt/...`
- `/pt-br/...`

Example:

```php
Route::prefix('{locale}')
    ->whereIn('locale', ['en', 'pt-pt', 'pt-br'])
    ->group(function () {
        Route::get('/', HomeController::class)->name('home');
        Route::get('/news', NewsIndexController::class)->name('news.index');
        Route::get('/news/{slug}', NewsShowController::class)->name('news.show');
    });

Route::get('/', LocaleEntryController::class);
```

The neutral `/` route handles first-visit locale resolution.

---

## Locale Resolution Priority

Always resolve locale in this order:

1. explicit user selection
2. saved preference (session/cookie/profile)
3. locale already present in URL
4. browser preferred languages
5. default locale

Rule:
If URL already contains a valid locale, do not override it.

Example:
- user visits `/pt-br/news/article-x`
- browser says `en-US`
- app must stay on `pt-br`

---

## Browser Locale Detection

Use browser locale only on first neutral entry (like `/`).

Examples:

- `en-US` -> `/en`
- `en-GB` -> `/en`
- `pt-PT` -> `/pt-pt`
- `pt-BR` -> `/pt-br`

Generic Portuguese:
- `pt` must map to one chosen default regional variant

Recommended:

```php
'pt' => 'pt-pt'
```

(or `pt-br` if that better fits your audience)

---

## Locale Normalization Helper

Centralize normalization logic.

```php
function normalizeLocale(?string $locale): ?string
{
    if (! $locale) {
        return null;
    }

    $locale = strtolower($locale);

    $map = [
        'en' => 'en',
        'en-us' => 'en',
        'en-gb' => 'en',

        'pt' => 'pt-pt',
        'pt-pt' => 'pt-pt',
        'pt-br' => 'pt-br',
    ];

    if (isset($map[$locale])) {
        return $map[$locale];
    }

    $base = explode('-', $locale)[0];

    return $map[$base] ?? null;
}
```

This creates one clean source of truth.

---

## Redirect Behavior

Redirect only once when user lands on neutral route:

Example:
- `/` + browser `pt-BR` -> redirect to `/pt-br`
- `/` + browser `pt-PT` -> redirect to `/pt-pt`

Do NOT:
- keep redirecting after user enters localized route
- override explicit user selection
- fight route locale with browser preferences

---

## Language Switcher Rules

When user manually changes language:

- switch route locale
- preserve current page if translation exists
- save preference in session/cookie

Example:
- `/en/news/article-a`
- switch to Portuguese Brazil
- becomes `/pt-br/news/article-a`

If translation missing:
- redirect to localized listing page, or
- fallback to localized homepage

Never generate dead links.

---

## Middleware Responsibilities

Create locale middleware to:

1. read locale from route
2. validate locale
3. set Laravel app locale
4. share locale with views
5. optionally persist locale choice

Example:

```php
app()->setLocale($locale);
```

---

## Frontend Behavior Summary

### First Visit

When user lands on `/`:

1. check saved preference
2. else inspect browser locale
3. normalize locale
4. redirect to best route

Example:
- browser sends `pt-BR,pt;q=0.9,en;q=0.8`
- redirect to `/pt-br`

---

### Returning Visit

If user returns with saved preference:

- saved locale beats browser locale

Example:
- saved = `pt-pt`
- browser = `en-US`
- result = `/pt-pt`

---

### Explicit Route Visit

If URL already contains locale:

- trust URL first

Example:
- visit `/en/news/story`
- browser says `pt-BR`
- stay in `/en`

---

## Blade Frontend Concerns

Always expose active locale:

```blade
<html lang="{{ app()->getLocale() }}">
```

Useful in:
- Blade templates
- JS bootstrap config
- frontend locale switcher state

---

## SEO Guidance

For each localized page:

- self-referencing canonical
- alternate hreflang links
- separate URLs per locale

Example alternates:

- `/en/news/example-story`
- `/pt-pt/news/example-story`
- `/pt-br/news/example-story`

This improves multilingual SEO indexing.

---

## What Not To Do

### Wrong:
```text
pt-BR -> /br
```

### Correct:
```text
pt-BR -> /pt-br
```

---

### Wrong:
```text
pt-PT -> /pt
```

### Correct:
```text
pt-PT -> /pt-pt
```

---

### Wrong:
Collapse both Portuguese variants into one route if content differs.

### Correct:
Keep separate route trees when translations are region-specific.

---

## Final Recommendation

For your Laravel 12 monolith:

- use `/en`, `/pt-pt`, `/pt-br`
- preserve Portuguese regional distinction
- normalize browser locales into exact supported variants
- detect browser locale only on neutral entry
- persist explicit user choice
- never override route locale once selected

This gives:
- correct localization behavior
- better SEO
- predictable UX
- scalable multilingual routing

---

## Recommended Next Implementation Step

Implement in this order:

1. locale-prefixed route group
2. neutral entry redirect `/`
3. locale middleware
4. locale normalization helper
5. language switcher persistence

This gives a solid frontend localization foundation without backend changes.
