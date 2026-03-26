---
name: code-review
description: >
  Performs structured code reviews for Laravel 12 / PHP 8.4 projects with
  jQuery and Vue 2 frontends, using a professional 5-pass methodology
  (Structure → Logic → Security → PSR Compliance → Frontend / JavaScript).
  Checks PHP against PSR-1, PSR-4, PSR-12, PER Coding Style, and interface
  standards. Checks JavaScript for Vue 2 reactivity bugs, jQuery DOM conflicts,
  XSS vectors, and general quality. Use this skill whenever the user asks for a
  code review, PR review, diff review, code audit, or quality check. Also
  trigger when the user shares a branch name, diff, or modified files and says
  things like "review my changes", "check this PR", "audit this for issues",
  "check PSR compliance", "review the frontend", or "does this follow
  standards". Targets Laravel 12 with PHP 8.4, jQuery, and Vue 2.
---

# Laravel 12 + Vue 2 + jQuery Code Review — 5-Pass Methodology

**Target stack: Laravel 12 · PHP 8.4 · jQuery · Vue 2**

Perform code reviews using a structured 5-pass approach. Each pass has a
distinct focus so that PHP structure, logic, security, standards compliance, and
frontend JavaScript concerns are evaluated separately.

Before starting, read the relevant reference files:
- `references/php-psr.md` — PSR standards and PHP 8.4 features
- `references/javascript-vue-jquery.md` — Vue 2, jQuery, and JS quality checks

## Mandatory Rule: strict_types

Every PHP file MUST begin with `declare(strict_types=1)` immediately after the
opening `<?php` tag. This is non-negotiable — flag any file missing it as
🟠 Major.

Expected file header:
```php
<?php

declare(strict_types=1);

namespace App\Service;
```

If a file is missing `declare(strict_types=1)`, flag it in Pass 1 and do not
wait until Pass 4. This rule takes priority.

## Inputs

Accept any of the following as review input:

- A branch name or PR reference (use `git diff` to retrieve changes)
- A set of modified files or file paths
- Inline code pasted in the conversation
- A diff or patch

If the user provides a branch name, run `git diff main...<branch>` (or the
appropriate base branch) to obtain the changeset. If only file paths are given,
read those files and review them in full.

### Project detection

Before reviewing, quickly scan for configuration files:

- `composer.json` — Laravel version, PSR packages, PHP version constraint
- `package.json` — Vue version, jQuery version, build tool (Vite/Webpack/Mix)
- `phpcs.xml` / `.php-cs-fixer.php` — coding standard rules
- `phpstan.neon` — static analysis level
- `phpunit.xml` — test config
- `vite.config.js` / `webpack.mix.js` — frontend build config
- `.eslintrc` / `.eslintrc.js` — JS linting rules

Use this context to calibrate the review. Don't duplicate what CI tooling
already catches.

## Review Process

Work through the five passes in order. Time estimates are guidelines for a
typical changeset of ~200 lines — scale proportionally.

---

### Pass 1 — Structure (~2 min)

Evaluate the overall shape and organization of the code:

- **strict_types declaration**: Every PHP file MUST have
  `declare(strict_types=1)` as the first statement after `<?php`. Flag any
  file missing it as 🟠 Major.
- **Laravel 12 architecture**: Is business logic in the right place? Check for:
  controller bloat (logic belongs in actions, services, or form requests),
  models doing HTTP/view work, raw queries in controllers, routes defined in
  wrong files. Use Laravel 12 patterns — form requests for validation,
  policies for authorization, events/listeners for side effects, jobs for
  async work.
- **PHP 8.4 idioms**: Look for opportunities to use property hooks instead of
  boilerplate getters/setters, asymmetric visibility (`public private(set)`)
  instead of readonly with separate setters, `new` without parentheses for
  method chaining, and `#[Deprecated]` attribute instead of `@deprecated`
  docblocks.
- **Frontend file organization**: Are Vue components in a sensible structure?
  Are jQuery scripts isolated or mixed into Blade templates? Check that JS
  files follow a consistent pattern — components in `/resources/js/components`,
  page scripts organized by feature.
- **Naming**: PHP follows PascalCase/camelCase/UPPER_SNAKE conventions. Vue
  components use PascalCase in definitions, kebab-case in templates. jQuery
  selectors use descriptive classes/IDs, not generic ones.
- **Namespace and PSR-4**: Does the namespace match the directory path? Is the
  class name identical to the file name?
- **Dead code**: Unused PHP `use` imports, unreachable code, commented-out
  blocks, orphaned Vue components, unused jQuery event handlers.

---

### Pass 2 — Logic (~3 min)

Examine correctness and robustness:

- **PHP correctness**: Trace the main execution paths. With `strict_types=1`
  enforced, verify function signatures and call sites use correct types.
- **Eloquent pitfalls**: N+1 queries (use `with()` or leverage Laravel 12's
  automatic eager loading), missing `$fillable`/`$guarded`, incorrect
  relationship definitions, `->get()` when `->first()` is intended, missing
  `->exists()` checks.
- **Property hooks**: If used, verify `get`/`set` hooks don't introduce side
  effects. Check for infinite recursion (set hook assigning to `$this->prop`
  instead of `$value`).
- **Vue 2 reactivity**: This is the #1 source of frontend bugs. Check for:
  `this.obj.newProp = value` (not reactive — use `Vue.set()`),
  `this.arr[index] = value` (not reactive — use `splice` or `Vue.set()`),
  `this.arr.length = 0` (not reactive — use `splice`).
- **Vue lifecycle**: DOM access in `created` (should be `mounted`), missing
  cleanup in `beforeDestroy` (event listeners, intervals, subscriptions),
  async `mounted` without loading states.
- **jQuery timing**: Are jQuery plugins initialized before or after Vue mounts?
  Does jQuery modify DOM elements that Vue manages (causes desync)?
- **Edge cases**: Empty arrays, nulls, empty strings, zero vs false in both
  PHP and JS. PHP's `==` vs `===`, JS's `==` vs `===`.
- **Error handling**: PHP exceptions caught at the right level? jQuery AJAX
  calls handling `.fail()`? Vue components using `errorCaptured`?
- **Test coverage**: PHPUnit/Pest tests for backend? Are Vue components tested?
  Do tests assert meaningful outcomes?
- **Performance**: Unnecessary DB queries in loops, loading full collections
  when only a count is needed, heavy jQuery operations in scroll handlers
  without debounce, Vue watchers triggering expensive recalculations.
- **Deprecated features**: PHP 8.4 implicit nullable types, old DOM extension
  methods, `var` in new JS code, deprecated jQuery methods.

---

### Pass 3 — Security (~3 min)

Security is critical in a mixed Laravel + jQuery + Vue stack because there are
multiple layers where user input enters and exits the system.

**PHP / Laravel:**

- **SQL injection**: Watch for `DB::raw()`, raw `whereRaw()`, or `selectRaw()`
  with concatenated user input. Eloquent query builder is safe by default —
  only raw expressions are dangerous.
- **XSS in Blade**: Use `{{ }}` (escaped) not `{!! !!}` (raw) unless content
  is explicitly safe. Check `@json()` directive output — it's safe in
  `<script>` tags but not in HTML attributes.
- **Mass assignment**: Is `$fillable` or `$guarded` properly set on every
  model? Can an attacker set `is_admin` or `role` by crafting a request?
- **Authorization**: Are policies/gates used? Can a user access another user's
  resources by changing an ID in the URL? Check middleware on routes.
- **CSRF**: Web routes must have CSRF protection. Check that the
  `<meta name="csrf-token">` tag exists in the layout and that jQuery AJAX
  uses it via `$.ajaxSetup()`.
- **File uploads**: MIME type validated server-side? Size limits enforced? Files
  stored outside the public directory?
- **Command injection**: Any `exec()`, `shell_exec()`, `system()`, `passthru()`
  or `proc_open()` with user input?
- **Path traversal**: User input in file paths? Validate with `realpath()`.
- **Secrets**: Hardcoded API keys, passwords, tokens in code instead of `.env`?
  Sensitive data in logs or responses?
- **Bcrypt cost**: PHP 8.4 default is 12. Don't let the project override to
  a lower value.

**JavaScript / Vue 2 / jQuery:**

- **`v-html` with user data**: XSS vector. Must sanitize with DOMPurify or
  replace with `{{ }}` text interpolation.
- **`.html(userInput)` in jQuery**: XSS vector. Use `.text()` instead.
- **`$('<div>' + userInput + '</div>')`**: XSS vector. Use
  `$('<div>').text(userInput)` instead.
- **URL binding**: `:href="userUrl"` in Vue or `.attr('href', userUrl)` in
  jQuery can execute `javascript:` protocol URLs. Validate scheme.
- **Dynamic component names**: `<component :is="userInput">` can instantiate
  arbitrary components. Whitelist values.
- **jQuery `.text()` + Vue templates**: If jQuery writes user input via
  `.text()` into an element that Vue compiles, Mustache syntax in the text
  can execute as Vue expressions. This is a known Vue + jQuery XSS gadget.
- **CSRF in AJAX**: Every jQuery `$.ajax()` or `$.post()` to Laravel web
  routes must include the CSRF token. Check for the global `$.ajaxSetup` or
  per-request `X-CSRF-TOKEN` header.
- **Prototype pollution**: Vue 2's template compiler is vulnerable to prototype
  pollution (CVE-2024-6783). If using the full build with in-browser
  compilation, flag it.
- **Hardcoded secrets in JS**: API keys, tokens, or credentials in `.js` files
  are exposed to every user.
- **Console statements**: `console.log()` in production code can leak data.

---

### Pass 4 — PSR Compliance (~2 min)

Check PHP code against PSR standards. Reference `references/php-psr.md` for
detailed rules. Focus on violations with real impact.

**Always check:**

- **PSR-1**: Class naming (PascalCase), method naming (camelCase), constant
  naming (UPPER_SNAKE_CASE), no side effects in class files.
- **PSR-4**: Namespace matches directory path, class name matches file name,
  `composer.json` autoload mapping is correct.
- **PSR-12 / PER Coding Style**: Formatting, braces, spacing, visibility
  declarations, `use` import ordering. Only flag what the project's linter
  won't catch.

**Check when relevant:**

- **PSR-3** (Logging): Using `LoggerInterface`? Log levels appropriate?
  Placeholders instead of string concatenation?
- **PSR-7 / PSR-17 / PSR-18** (HTTP): PSR-7 objects treated as immutable?
  Factories used? ClientInterface for HTTP requests?
- **PSR-11** (Container): Container used as service locator in business logic
  (anti-pattern)? Services type-hinted in constructors?
- **PSR-15** (Middleware): Implements `MiddlewareInterface`? Delegates to next
  handler correctly?
- **PSR-14** (Events): Events are plain objects? Stoppable propagation
  respected?
- **PSR-6 / PSR-16** (Cache): Valid cache keys? TTL handled correctly?
- **PSR-20** (Clock): Time injected via `ClockInterface` instead of
  `new \DateTime()` or `time()`?

---

### Pass 5 — Frontend / JavaScript (~3 min)

Review Vue 2 components, jQuery code, and general JavaScript quality. Reference
`references/javascript-vue-jquery.md` for detailed checks.

**Vue 2 component quality:**

- Props have type declarations and validators (not just `props: ['foo']`).
- `data` is a function returning a fresh object, not a plain object.
- Computed properties used for derived state (cached), methods for actions.
- Components don't mutate props directly — emit events instead.
- `$nextTick` used when reading DOM after data changes.
- Watchers used for side effects only, not data transformation (use computed).
- Components clean up in `beforeDestroy` — no leaked listeners/intervals.

**Vue 2 + jQuery coexistence:**

- jQuery should NOT manipulate DOM elements that Vue manages. Vue's virtual
  DOM will overwrite jQuery's changes on next re-render.
- If jQuery plugins are needed on Vue-managed elements, use refs and
  initialize in `mounted`, destroy in `beforeDestroy`.
- Don't use jQuery event binding on Vue elements — use `v-on` / `@` syntax.
- Watch for `.text()` content being parsed by Vue's template compiler.

**General JS quality:**

- `===` instead of `==` (strict equality).
- `const` by default, `let` when reassignment needed. Flag `var` in new code.
- Error handling on async operations (`.catch()`, `try/catch`).
- No `console.log()` in production code.
- No hardcoded secrets, API keys, or tokens.
- Event delegation for dynamically created elements.
- Cached jQuery selectors (don't re-query the DOM repeatedly).
- Debounced scroll/resize handlers.

**Vue 2 EOL note:**

Vue 2 reached End of Life on December 31, 2023 and no longer receives security
patches. Don't block PRs over this — but do note it once per review as a
strategic concern if new Vue 2 components are being added.

---

## Output Format

| Severity | Meaning | Action required |
|----------|---------|-----------------|
| 🔴 Critical | Security vulnerability or data-loss risk | Must fix before merge |
| 🟠 Major | Bug, logic error, or significant design flaw | Should fix before merge |
| 🟡 Minor | Code quality, readability, or style issue | Nice to fix |
| 🔵 PSR | PHP standards compliance violation | Fix to maintain consistency |
| 🟣 Frontend | JavaScript / Vue / jQuery issue | Fix to maintain quality |
| 💡 Suggestion | Improvement idea, alternative approach | Optional |

### Report template

```
## Code Review Summary

**Files reviewed**: [list]
**Stack**: Laravel 12 · PHP 8.4 · jQuery [version] · Vue 2 [version]
**Standards enforced**: [PSR-12, PER CS 3.0, ESLint rules — based on config]
**strict_types compliance**: [all files / N files missing — list them]
**Overall assessment**: [Approve / Approve with changes / Request changes]

### Pass 1 — Structure
- [severity] [file:line] Description

### Pass 2 — Logic
- [severity] [file:line] Description

### Pass 3 — Security
- [severity] [file:line] Description

### Pass 4 — PSR Compliance
- [severity] [PSR-N] [file:line] Description

### Pass 5 — Frontend / JavaScript
- [severity] [file:line] Description

### Highlights
- Things done well worth calling out

### Recommended next steps
1. ...
```

## Guidelines

- **Be specific**: Reference file and line. Quote code when it helps.
- **Explain the "why"**: Don't just say "this is wrong" — explain the
  consequence and conditions under which it breaks.
- **Suggest fixes**: Offer concrete fixes, especially for PSR violations,
  Vue 2 reactivity bugs, and XSS vectors.
- **Acknowledge good work**: Clean code, good test coverage, proper
  separation of concerns — call it out.
- **Calibrate severity**: A missing trailing comma is 🔵 PSR, not 🟠 Major.
  A `v-html` with static trusted content is 🟡 Minor, not 🔴 Critical.
- **Don't duplicate the linter**: If phpcs/ESLint/phpstan are configured,
  focus on what they can't catch — semantic issues, architecture, security.
- **Frontend context matters**: Legacy jQuery code doesn't need to be rewritten
  to Vue in every PR. Flag improvements for new code, respect constraints on
  existing code.---
  name: code-review-laravel
  description: >
  Performs structured code reviews for Laravel 12 / PHP 8.4 projects with
  jQuery and Vue 2 frontends, using a professional 5-pass methodology
  (Structure → Logic → Security → PSR Compliance → Frontend / JavaScript).
  Checks PHP against PSR-1, PSR-4, PSR-12, PER Coding Style, and interface
  standards. Checks JavaScript for Vue 2 reactivity bugs, jQuery DOM conflicts,
  XSS vectors, and general quality. Use this skill whenever the user asks for a
  code review, PR review, diff review, code audit, or quality check. Also
  trigger when the user shares a branch name, diff, or modified files and says
  things like "review my changes", "check this PR", "audit this for issues",
  "check PSR compliance", "review the frontend", or "does this follow
  standards". Targets Laravel 12 with PHP 8.4, jQuery, and Vue 2.
---

# Laravel 12 + Vue 2 + jQuery Code Review — 5-Pass Methodology

**Target stack: Laravel 12 · PHP 8.4 · jQuery · Vue 2**

Perform code reviews using a structured 5-pass approach. Each pass has a
distinct focus so that PHP structure, logic, security, standards compliance, and
frontend JavaScript concerns are evaluated separately.

Before starting, read the relevant reference files:
- `references/php-psr.md` — PSR standards and PHP 8.4 features
- `references/javascript-vue-jquery.md` — Vue 2, jQuery, and JS quality checks

## Mandatory Rule: strict_types

Every PHP file MUST begin with `declare(strict_types=1)` immediately after the
opening `<?php` tag. This is non-negotiable — flag any file missing it as
🟠 Major.

Expected file header:
```php
<?php

declare(strict_types=1);

namespace App\Service;
```

If a file is missing `declare(strict_types=1)`, flag it in Pass 1 and do not
wait until Pass 4. This rule takes priority.

## Inputs

Accept any of the following as review input:

- A branch name or PR reference (use `git diff` to retrieve changes)
- A set of modified files or file paths
- Inline code pasted in the conversation
- A diff or patch

If the user provides a branch name, run `git diff main...<branch>` (or the
appropriate base branch) to obtain the changeset. If only file paths are given,
read those files and review them in full.

### Project detection

Before reviewing, quickly scan for configuration files:

- `composer.json` — Laravel version, PSR packages, PHP version constraint
- `package.json` — Vue version, jQuery version, build tool (Vite/Webpack/Mix)
- `phpcs.xml` / `.php-cs-fixer.php` — coding standard rules
- `phpstan.neon` — static analysis level
- `phpunit.xml` — test config
- `vite.config.js` / `webpack.mix.js` — frontend build config
- `.eslintrc` / `.eslintrc.js` — JS linting rules

Use this context to calibrate the review. Don't duplicate what CI tooling
already catches.

## Review Process

Work through the five passes in order. Time estimates are guidelines for a
typical changeset of ~200 lines — scale proportionally.

---

### Pass 1 — Structure (~2 min)

Evaluate the overall shape and organization of the code:

- **strict_types declaration**: Every PHP file MUST have
  `declare(strict_types=1)` as the first statement after `<?php`. Flag any
  file missing it as 🟠 Major.
- **Laravel 12 architecture**: Is business logic in the right place? Check for:
  controller bloat (logic belongs in actions, services, or form requests),
  models doing HTTP/view work, raw queries in controllers, routes defined in
  wrong files. Use Laravel 12 patterns — form requests for validation,
  policies for authorization, events/listeners for side effects, jobs for
  async work.
- **PHP 8.4 idioms**: Look for opportunities to use property hooks instead of
  boilerplate getters/setters, asymmetric visibility (`public private(set)`)
  instead of readonly with separate setters, `new` without parentheses for
  method chaining, and `#[Deprecated]` attribute instead of `@deprecated`
  docblocks.
- **Frontend file organization**: Are Vue components in a sensible structure?
  Are jQuery scripts isolated or mixed into Blade templates? Check that JS
  files follow a consistent pattern — components in `/resources/js/components`,
  page scripts organized by feature.
- **Naming**: PHP follows PascalCase/camelCase/UPPER_SNAKE conventions. Vue
  components use PascalCase in definitions, kebab-case in templates. jQuery
  selectors use descriptive classes/IDs, not generic ones.
- **Namespace and PSR-4**: Does the namespace match the directory path? Is the
  class name identical to the file name?
- **Dead code**: Unused PHP `use` imports, unreachable code, commented-out
  blocks, orphaned Vue components, unused jQuery event handlers.

---

### Pass 2 — Logic (~3 min)

Examine correctness and robustness:

- **PHP correctness**: Trace the main execution paths. With `strict_types=1`
  enforced, verify function signatures and call sites use correct types.
- **Eloquent pitfalls**: N+1 queries (use `with()` or leverage Laravel 12's
  automatic eager loading), missing `$fillable`/`$guarded`, incorrect
  relationship definitions, `->get()` when `->first()` is intended, missing
  `->exists()` checks.
- **Property hooks**: If used, verify `get`/`set` hooks don't introduce side
  effects. Check for infinite recursion (set hook assigning to `$this->prop`
  instead of `$value`).
- **Vue 2 reactivity**: This is the #1 source of frontend bugs. Check for:
  `this.obj.newProp = value` (not reactive — use `Vue.set()`),
  `this.arr[index] = value` (not reactive — use `splice` or `Vue.set()`),
  `this.arr.length = 0` (not reactive — use `splice`).
- **Vue lifecycle**: DOM access in `created` (should be `mounted`), missing
  cleanup in `beforeDestroy` (event listeners, intervals, subscriptions),
  async `mounted` without loading states.
- **jQuery timing**: Are jQuery plugins initialized before or after Vue mounts?
  Does jQuery modify DOM elements that Vue manages (causes desync)?
- **Edge cases**: Empty arrays, nulls, empty strings, zero vs false in both
  PHP and JS. PHP's `==` vs `===`, JS's `==` vs `===`.
- **Error handling**: PHP exceptions caught at the right level? jQuery AJAX
  calls handling `.fail()`? Vue components using `errorCaptured`?
- **Test coverage**: PHPUnit/Pest tests for backend? Are Vue components tested?
  Do tests assert meaningful outcomes?
- **Performance**: Unnecessary DB queries in loops, loading full collections
  when only a count is needed, heavy jQuery operations in scroll handlers
  without debounce, Vue watchers triggering expensive recalculations.
- **Deprecated features**: PHP 8.4 implicit nullable types, old DOM extension
  methods, `var` in new JS code, deprecated jQuery methods.

---

### Pass 3 — Security (~3 min)

Security is critical in a mixed Laravel + jQuery + Vue stack because there are
multiple layers where user input enters and exits the system.

**PHP / Laravel:**

- **SQL injection**: Watch for `DB::raw()`, raw `whereRaw()`, or `selectRaw()`
  with concatenated user input. Eloquent query builder is safe by default —
  only raw expressions are dangerous.
- **XSS in Blade**: Use `{{ }}` (escaped) not `{!! !!}` (raw) unless content
  is explicitly safe. Check `@json()` directive output — it's safe in
  `<script>` tags but not in HTML attributes.
- **Mass assignment**: Is `$fillable` or `$guarded` properly set on every
  model? Can an attacker set `is_admin` or `role` by crafting a request?
- **Authorization**: Are policies/gates used? Can a user access another user's
  resources by changing an ID in the URL? Check middleware on routes.
- **CSRF**: Web routes must have CSRF protection. Check that the
  `<meta name="csrf-token">` tag exists in the layout and that jQuery AJAX
  uses it via `$.ajaxSetup()`.
- **File uploads**: MIME type validated server-side? Size limits enforced? Files
  stored outside the public directory?
- **Command injection**: Any `exec()`, `shell_exec()`, `system()`, `passthru()`
  or `proc_open()` with user input?
- **Path traversal**: User input in file paths? Validate with `realpath()`.
- **Secrets**: Hardcoded API keys, passwords, tokens in code instead of `.env`?
  Sensitive data in logs or responses?
- **Bcrypt cost**: PHP 8.4 default is 12. Don't let the project override to
  a lower value.

**JavaScript / Vue 2 / jQuery:**

- **`v-html` with user data**: XSS vector. Must sanitize with DOMPurify or
  replace with `{{ }}` text interpolation.
- **`.html(userInput)` in jQuery**: XSS vector. Use `.text()` instead.
- **`$('<div>' + userInput + '</div>')`**: XSS vector. Use
  `$('<div>').text(userInput)` instead.
- **URL binding**: `:href="userUrl"` in Vue or `.attr('href', userUrl)` in
  jQuery can execute `javascript:` protocol URLs. Validate scheme.
- **Dynamic component names**: `<component :is="userInput">` can instantiate
  arbitrary components. Whitelist values.
- **jQuery `.text()` + Vue templates**: If jQuery writes user input via
  `.text()` into an element that Vue compiles, Mustache syntax in the text
  can execute as Vue expressions. This is a known Vue + jQuery XSS gadget.
- **CSRF in AJAX**: Every jQuery `$.ajax()` or `$.post()` to Laravel web
  routes must include the CSRF token. Check for the global `$.ajaxSetup` or
  per-request `X-CSRF-TOKEN` header.
- **Prototype pollution**: Vue 2's template compiler is vulnerable to prototype
  pollution (CVE-2024-6783). If using the full build with in-browser
  compilation, flag it.
- **Hardcoded secrets in JS**: API keys, tokens, or credentials in `.js` files
  are exposed to every user.
- **Console statements**: `console.log()` in production code can leak data.

---

### Pass 4 — PSR Compliance (~2 min)

Check PHP code against PSR standards. Reference `references/php-psr.md` for
detailed rules. Focus on violations with real impact.

**Always check:**

- **PSR-1**: Class naming (PascalCase), method naming (camelCase), constant
  naming (UPPER_SNAKE_CASE), no side effects in class files.
- **PSR-4**: Namespace matches directory path, class name matches file name,
  `composer.json` autoload mapping is correct.
- **PSR-12 / PER Coding Style**: Formatting, braces, spacing, visibility
  declarations, `use` import ordering. Only flag what the project's linter
  won't catch.

**Check when relevant:**

- **PSR-3** (Logging): Using `LoggerInterface`? Log levels appropriate?
  Placeholders instead of string concatenation?
- **PSR-7 / PSR-17 / PSR-18** (HTTP): PSR-7 objects treated as immutable?
  Factories used? ClientInterface for HTTP requests?
- **PSR-11** (Container): Container used as service locator in business logic
  (anti-pattern)? Services type-hinted in constructors?
- **PSR-15** (Middleware): Implements `MiddlewareInterface`? Delegates to next
  handler correctly?
- **PSR-14** (Events): Events are plain objects? Stoppable propagation
  respected?
- **PSR-6 / PSR-16** (Cache): Valid cache keys? TTL handled correctly?
- **PSR-20** (Clock): Time injected via `ClockInterface` instead of
  `new \DateTime()` or `time()`?

---

### Pass 5 — Frontend / JavaScript (~3 min)

Review Vue 2 components, jQuery code, and general JavaScript quality. Reference
`references/javascript-vue-jquery.md` for detailed checks.

**Vue 2 component quality:**

- Props have type declarations and validators (not just `props: ['foo']`).
- `data` is a function returning a fresh object, not a plain object.
- Computed properties used for derived state (cached), methods for actions.
- Components don't mutate props directly — emit events instead.
- `$nextTick` used when reading DOM after data changes.
- Watchers used for side effects only, not data transformation (use computed).
- Components clean up in `beforeDestroy` — no leaked listeners/intervals.

**Vue 2 + jQuery coexistence:**

- jQuery should NOT manipulate DOM elements that Vue manages. Vue's virtual
  DOM will overwrite jQuery's changes on next re-render.
- If jQuery plugins are needed on Vue-managed elements, use refs and
  initialize in `mounted`, destroy in `beforeDestroy`.
- Don't use jQuery event binding on Vue elements — use `v-on` / `@` syntax.
- Watch for `.text()` content being parsed by Vue's template compiler.

**General JS quality:**

- `===` instead of `==` (strict equality).
- `const` by default, `let` when reassignment needed. Flag `var` in new code.
- Error handling on async operations (`.catch()`, `try/catch`).
- No `console.log()` in production code.
- No hardcoded secrets, API keys, or tokens.
- Event delegation for dynamically created elements.
- Cached jQuery selectors (don't re-query the DOM repeatedly).
- Debounced scroll/resize handlers.

**Vue 2 EOL note:**

Vue 2 reached End of Life on December 31, 2023 and no longer receives security
patches. Don't block PRs over this — but do note it once per review as a
strategic concern if new Vue 2 components are being added.

---

## Output Format

| Severity | Meaning | Action required |
|----------|---------|-----------------|
| 🔴 Critical | Security vulnerability or data-loss risk | Must fix before merge |
| 🟠 Major | Bug, logic error, or significant design flaw | Should fix before merge |
| 🟡 Minor | Code quality, readability, or style issue | Nice to fix |
| 🔵 PSR | PHP standards compliance violation | Fix to maintain consistency |
| 🟣 Frontend | JavaScript / Vue / jQuery issue | Fix to maintain quality |
| 💡 Suggestion | Improvement idea, alternative approach | Optional |

### Report template

```
## Code Review Summary

**Files reviewed**: [list]
**Stack**: Laravel 12 · PHP 8.4 · jQuery [version] · Vue 2 [version]
**Standards enforced**: [PSR-12, PER CS 3.0, ESLint rules — based on config]
**strict_types compliance**: [all files / N files missing — list them]
**Overall assessment**: [Approve / Approve with changes / Request changes]

### Pass 1 — Structure
- [severity] [file:line] Description

### Pass 2 — Logic
- [severity] [file:line] Description

### Pass 3 — Security
- [severity] [file:line] Description

### Pass 4 — PSR Compliance
- [severity] [PSR-N] [file:line] Description

### Pass 5 — Frontend / JavaScript
- [severity] [file:line] Description

### Highlights
- Things done well worth calling out

### Recommended next steps
1. ...
```

## Guidelines

- **Be specific**: Reference file and line. Quote code when it helps.
- **Explain the "why"**: Don't just say "this is wrong" — explain the
  consequence and conditions under which it breaks.
- **Suggest fixes**: Offer concrete fixes, especially for PSR violations,
  Vue 2 reactivity bugs, and XSS vectors.
- **Acknowledge good work**: Clean code, good test coverage, proper
  separation of concerns — call it out.
- **Calibrate severity**: A missing trailing comma is 🔵 PSR, not 🟠 Major.
  A `v-html` with static trusted content is 🟡 Minor, not 🔴 Critical.
- **Don't duplicate the linter**: If phpcs/ESLint/phpstan are configured,
  focus on what they can't catch — semantic issues, architecture, security.
- **Frontend context matters**: Legacy jQuery code doesn't need to be rewritten
  to Vue in every PR. Flag improvements for new code, respect constraints on
  existing code.
