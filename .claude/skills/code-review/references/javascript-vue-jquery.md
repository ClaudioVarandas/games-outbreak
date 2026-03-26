# JavaScript Review Reference — jQuery & Vue 2

Use this reference during Pass 5 (Frontend / JavaScript) to check jQuery and
Vue 2 code for correctness, security, and quality issues.

## Vue 2 — End of Life Warning

Vue 2 reached End of Life on December 31, 2023. It no longer receives security
patches, bug fixes, or new features. This means:

- Known vulnerabilities (e.g., CVE-2024-6783 — XSS via prototype pollution in
  the template compiler) will NOT be patched.
- Flag any new Vue 2 components as 🟡 Minor with a note recommending a
  migration plan to Vue 3.
- Don't block PRs over this — the migration is a strategic decision, not a
  per-commit fix. But do note it.

## Vue 2 Review Checklist

### Component Structure
- **Single File Components**: `.vue` files should have `<template>`, `<script>`,
  and `<style>` sections in that order.
- **Component naming**: PascalCase for component definitions, kebab-case in
  templates (`<my-component>`). File names should match component names.
- **Props validation**: Props should have type declarations and validators when
  possible. Avoid `props: ['foo']` — use the object syntax with `type`,
  `required`, and `default`.
- **Data must be a function**: In components, `data` must return a fresh object
  from a function, not be a plain object (causes shared state bugs).
- **Avoid mutating props**: Components should never modify props directly. Emit
  an event or use a local copy.
- **Computed vs methods**: Use computed properties for derived state (they're
  cached). Use methods for actions or when caching isn't desired.

### Reactivity Gotchas (Vue 2 specific)
- **Adding new properties**: `this.obj.newProp = value` is NOT reactive.
  Must use `Vue.set(this.obj, 'newProp', value)` or `this.$set()`.
- **Array index assignment**: `this.arr[index] = value` is NOT reactive.
  Use `Vue.set(this.arr, index, value)` or `this.arr.splice(index, 1, value)`.
- **Array length**: `this.arr.length = 0` is NOT reactive. Use
  `this.arr.splice(0)`.
- **Deleting properties**: `delete this.obj.prop` is NOT reactive. Use
  `Vue.delete(this.obj, 'prop')` or `this.$delete()`.
- These are the most common source of "it works but doesn't update the UI" bugs
  in Vue 2.

### Lifecycle & Async
- **Don't access DOM in `created`**: The DOM isn't ready yet. Use `mounted`.
- **Clean up in `beforeDestroy`**: Remove event listeners, intervals, timeouts,
  and subscriptions. Memory leaks are common in Vue 2 SPAs.
- **Async in `mounted`**: If `mounted` is `async`, the component renders before
  the promise resolves. Make sure there's a loading state.
- **Watch vs computed**: Use `watch` for side effects (API calls, logging), use
  `computed` for derived data. If a `watch` just transforms data, it should
  probably be a `computed`.
- **`$nextTick`**: When you need to read DOM after a data change, use
  `this.$nextTick()`. Direct DOM reads after data mutation may see stale state.

### Vue 2 Security
- **`v-html` is dangerous**: Renders raw HTML — equivalent to `innerHTML`. If
  the content comes from user input or an API, it's an XSS vector. Use `{{ }}`
  (text interpolation, auto-escaped) whenever possible. If `v-html` is
  necessary, sanitize with DOMPurify first.
- **Never use user input as a template**: `new Vue({ template: userInput })` is
  arbitrary code execution.
- **URL binding**: `:href="userUrl"` can execute `javascript:` URLs. Validate
  that URLs start with `http://` or `https://`.
- **Dynamic component names**: `<component :is="userInput">` can instantiate
  arbitrary components. Whitelist allowed values.
- **Prototype pollution**: Vue 2's template compiler is vulnerable to prototype
  pollution (CVE-2024-6783). If using the full build (with in-browser
  compilation), be aware that `Object.prototype` pollution can lead to XSS.

### Vue 2 + jQuery Interaction Risks
- **DOM conflicts**: jQuery and Vue both manipulate the DOM. If jQuery modifies
  DOM that Vue controls, Vue's virtual DOM gets out of sync and bugs follow.
  Rule: jQuery should only touch elements OUTSIDE Vue's mounted root, or
  elements explicitly excluded via `v-once` / refs.
- **Event conflicts**: Don't use jQuery to bind events on Vue-managed elements.
  Use Vue's `@click`, `v-on`, etc. instead.
- **`.text()` + Vue templates**: jQuery's `.text()` is normally XSS-safe, but
  if the element is later parsed by Vue's template compiler, Mustache syntax
  (`{{ }}`) inside the text can execute as Vue expressions. This is a known
  gadget vector.
- **Initialization order**: Make sure Vue components mount AFTER jQuery plugins
  initialize on their target elements, or isolate them entirely.

## jQuery Review Checklist

### General Quality
- **Cache selectors**: `$('.foo')` queries the DOM every time. If used more
  than once, cache it: `const $foo = $('.foo')`.
- **Chain methods**: jQuery returns `this`, so chain instead of repeating
  selectors: `$el.addClass('active').show().text('Done')`.
- **Event delegation**: For dynamically added elements, use event delegation:
  `$(parent).on('click', '.child', handler)` — not `$('.child').click()`.
- **`$(document).ready()`**: In modern jQuery, prefer `$(function() { })` short
  form. If scripts are at the bottom of `<body>`, it may not even be needed.
- **Avoid `.html()` with user input**: Just like `v-html`, jQuery's `.html()`
  renders raw HTML. Use `.text()` for user-provided content.

### jQuery Security
- **`.html(userInput)`** — XSS vector. Always use `.text()` for user content.
- **`$('<div>' + userInput + '</div>')`** — XSS vector. User input in HTML
  string constructors is dangerous. Use `$('<div>').text(userInput)` instead.
- **AJAX CSRF**: jQuery AJAX calls to your own backend must include the CSRF
  token. In Laravel, set it globally:
  ```javascript
  $.ajaxSetup({
      headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
  });
  ```
- **`$.globalEval()`** — executes arbitrary JavaScript. Should never appear in
  production code.
- **`.attr('href', userInput)`** — can inject `javascript:` URLs. Validate the
  protocol.
- **JSON parsing**: Use `JSON.parse()`, not `$.parseJSON()` (deprecated) or
  `eval()`.

### jQuery + Laravel Integration
- **CSRF token**: Every AJAX request to a Laravel backend must send the CSRF
  token. Check for the meta tag in the layout:
  `<meta name="csrf-token" content="{{ csrf_token() }}">` and the `$.ajaxSetup`
  global header.
- **API routes**: Requests to `api/` routes use token/session auth, not CSRF.
  Make sure auth middleware is applied.
- **Error handling**: jQuery AJAX should handle errors — check for `.fail()` or
  `error` callback. Silent failures hide bugs and security issues.

## General JavaScript Quality Checks

- **`===` vs `==`**: Always use strict equality. Loose equality in JavaScript
  has even more surprising coercions than PHP.
- **`var` vs `let`/`const`**: Prefer `const` by default, `let` when reassignment
  is needed. `var` has function-scoped hoisting bugs. In legacy jQuery code,
  `var` may be everywhere — only flag it in new code.
- **Error handling**: `try/catch` around async operations. Unhandled promise
  rejections crash silently.
- **Console statements**: Remove `console.log()` from production code. Check
  for accidental `console.error()` that leaks sensitive data.
- **Hardcoded secrets**: API keys, tokens, or credentials in JavaScript files
  are exposed to every user. They belong on the server side.
- **Unused variables/imports**: Dead code in JavaScript bundles increases load
  time for nothing.
- **Dependency versions**: Check `package.json` for outdated or vulnerable
  packages. Run `npm audit` mentally.
