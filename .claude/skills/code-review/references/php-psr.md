# PHP PSR Standards — Quick Reference for Code Reviews

Use this reference during Pass 1 (Structure) and the PSR Compliance pass to check
code against the relevant standards. Not every PSR applies to every project — check
which ones the project uses (look at composer.json, phpcs.xml, .php-cs-fixer.php).

## Coding Style Standards

### PSR-1 — Basic Coding Standard
- Files MUST use only `<?php` or `<?=` tags.
- Files MUST use UTF-8 without BOM.
- Files SHOULD declare symbols (classes, functions, constants) OR cause side effects,
  but not both.
- Class names MUST use `StudlyCaps` (PascalCase).
- Class constants MUST be `UPPER_SNAKE_CASE`.
- Method names MUST be `camelCase`.

### PSR-12 — Extended Coding Style (supersedes PSR-2)
- 4 spaces for indentation, never tabs.
- Soft line length limit of 120 characters; no hard limit.
- Unix LF line endings. No trailing whitespace.
- Closing `?>` tag MUST be omitted in PHP-only files.
- One blank line after `namespace`; one blank line after `use` block.
- Opening brace for classes and methods on the NEXT line.
- Opening brace for control structures on the SAME line.
- Visibility MUST be declared on all properties and methods.
- `abstract`/`final` before visibility; `static` after visibility.
- One space after control structure keywords; no space after function/method names.
- `use` imports grouped: classes, then functions, then constants — each group
  separated by a blank line.

### PER Coding Style 3.0 (extends and replaces PSR-12 for modern PHP)
- Trailing commas required on multi-line argument/parameter lists.
- Abbreviations and acronyms treated as regular words (e.g., `HttpClient`, not
  `HTTPClient`).
- PHP file header blocks in strict order: opening tag, file docblock, declare
  statements, namespace, use imports.
- Covers modern syntax: match expressions, named arguments, enums, fibers,
  readonly properties, intersection types, DNF types.

## Autoloading

### PSR-4 — Autoloading Standard (PSR-0 is deprecated)
- Fully qualified class name: `\<Vendor>\(<Namespace>\)*\<ClassName>`
- Each namespace prefix maps to a base directory.
- Subdirectory structure mirrors sub-namespace names.
- File name matches class name exactly, with `.php` extension.
- Verify in `composer.json` under `autoload.psr-4`.

Common violations to look for:
- Class name doesn't match file name.
- Namespace doesn't match directory path.
- Missing or wrong `psr-4` mapping in `composer.json`.

## Interface Standards

### PSR-3 — Logger Interface
- Use `Psr\Log\LoggerInterface` for logging — don't roll your own.
- Eight log levels: emergency, alert, critical, error, warning, notice, info, debug.
- Message MUST be a string or object with `__toString()`.
- Context array with an `exception` key MUST hold an Exception instance.
- Never pass user input directly as the message — use placeholders: `{key}`.

### PSR-7 — HTTP Message Interface
- Request and Response objects are immutable — `with*()` methods return new instances.
- Don't modify the object and forget to capture the return value.
- `getBody()` returns a `StreamInterface` — read it once unless you `rewind()`.
- Headers are case-insensitive.

### PSR-17 — HTTP Factories
- Use factory interfaces (`RequestFactoryInterface`, `ResponseFactoryInterface`, etc.)
  to create PSR-7 objects instead of `new` directly.

### PSR-18 — HTTP Client
- Use `Psr\Http\Client\ClientInterface` for sending HTTP requests.
- Must throw `NetworkExceptionInterface` for network failures,
  `RequestExceptionInterface` for malformed requests.
- Clients must not throw exceptions for 4xx/5xx responses.

### PSR-15 — HTTP Handlers and Middleware
- Request handlers implement `RequestHandlerInterface::handle()`.
- Middleware implements `MiddlewareInterface::process()`.
- Middleware receives both the request and the next handler — must call
  `$handler->handle($request)` or return its own response.

### PSR-11 — Container Interface
- Use `Psr\Container\ContainerInterface` for dependency injection.
- `get($id)` throws `NotFoundExceptionInterface` if entry not found.
- `has($id)` returns bool — use it before `get()` when entry may not exist.
- Don't use the container as a service locator inside business logic.

### PSR-14 — Event Dispatcher
- Events are plain objects; listeners receive them by type.
- `StoppableEventInterface` allows short-circuiting propagation.
- Dispatcher must call listeners in registration order and stop if
  `isPropagationStopped()` returns true.

### PSR-6 — Caching Interface / PSR-16 — Simple Cache
- PSR-6: full pool/item model (`CacheItemPoolInterface`, `CacheItemInterface`).
- PSR-16: simpler key-value API (`CacheInterface` with `get`, `set`, `delete`).
- TTL of `null` means use the implementation default; `0` or negative means delete.
- Keys must be strings of at least one character; `{}()/\@:` are reserved.

### PSR-13 — Hypermedia Links
- For REST APIs: `LinkInterface` and `EvolvableLinkInterface`.
- Links carry `href`, `rel`, and attributes.

### PSR-20 — Clock Interface
- `Psr\Clock\ClockInterface::now()` returns `DateTimeImmutable`.
- Use clock injection instead of `new \DateTime()` or `time()` — makes code testable.

## Mandatory: strict_types

Every PHP file in the project MUST start with `declare(strict_types=1)` after
the opening `<?php` tag. This is a project-wide rule — flag any missing file as
🟠 Major.

```php
<?php

declare(strict_types=1);

namespace App\Service;
```

Why this matters: without strict types, PHP silently coerces `"123"` to `123`,
`1` to `true`, etc. This hides bugs that only surface in production under
unexpected input. With PHP 8.4's advanced type system (union types, intersection
types, DNF types, typed properties), strict_types is essential to get real
safety from type declarations.

## PHP 8.4 Features — Review Checklist

These are PHP 8.4 specific features to check during review:

### Property Hooks (new in 8.4)
Property hooks replace boilerplate getters/setters with inline `get` and `set`
logic directly on the property.

```php
class User
{
    public string $name {
        get => strtoupper($this->name);
        set => trim($value);
    }
}
```

Review checks:
- Hooks should validate or transform, not trigger side effects (DB calls, I/O).
- Watch for infinite recursion: a `set` hook that assigns to `$this->name`
  instead of using `$value` will loop.
- Hooks don't replace all getters/setters — complex logic with multiple
  dependencies still belongs in methods.
- Property hooks are virtual by default when they only have a `get` hook — they
  don't use storage. Make sure the developer intends this.

### Asymmetric Visibility (new in 8.4)
Properties can have different visibility for read vs write:

```php
class Order
{
    public private(set) string $status = 'pending';
}
```

Review checks:
- Verify the visibility split makes sense — `public private(set)` is the most
  common and useful pattern.
- Don't combine with `readonly` unless there's a specific reason — asymmetric
  visibility often replaces readonly use cases.
- External code should not need to write to a property that's write-restricted.

### Method Chaining on `new` (new in 8.4)
No need for extra parentheses:

```php
// PHP 8.4 — clean
$name = new ReflectionClass($obj)->getShortName();

// Before 8.4 — required wrapping
$name = (new ReflectionClass($obj))->getShortName();
```

Review checks:
- Encourage using the new syntax for readability.
- Flag old `(new Foo())` patterns in new code — suggest updating.

### `#[Deprecated]` Attribute (new in 8.4)
Replaces `@deprecated` docblocks with a proper language-level attribute:

```php
#[Deprecated("Use newMethod() instead", since: "2.0")]
public function oldMethod(): void { }
```

Review checks:
- New deprecations should use the attribute, not docblocks.
- Include the `since` parameter for versioned libraries.
- Check that the alternative method actually exists.

### New Array Functions (new in 8.4)
- `array_find($array, $callback)` — first matching value (replaces
  `array_filter` + `reset` combos).
- `array_find_key($array, $callback)` — first matching key.
- `array_any($array, $callback)` — true if any element matches.
- `array_all($array, $callback)` — true if all elements match.

Review checks:
- Flag verbose `foreach` loops or `array_filter` + `reset`/`current` patterns
  that could be simplified with these functions.
- These are more readable and express intent clearly.

### HTML5 DOM API (new in 8.4)
New `Dom\HTMLDocument` and `Dom\XMLDocument` classes replace the old DOM API.

Review checks:
- New code should use `Dom\HTMLDocument::createFromString()` and
  `Dom\XMLDocument::createFromString()` instead of `DOMDocument`.
- Old DOM methods are deprecated — flag usage in new code.

### BCMath Object API (new in 8.4)
`BcMath\Number` provides an OOP interface with operator overloading:

```php
use BcMath\Number;
$result = new Number('1.5') + new Number('2.5');
```

Review checks:
- For new arbitrary precision code, prefer the object API over procedural
  `bcadd()`, `bcmul()`, etc.

### Other PHP 8.4 Changes
- **Default bcrypt cost raised to 12**: If the project overrides the cost to a
  lower value, flag it.
- **Implicit nullable types deprecated**: `function foo(Type $x = null)` is
  deprecated — use `function foo(?Type $x = null)` or `Type|null`.
- **`Deprecated` extensions**: IMAP and Pspell moved to PECL. Old DOM methods
  deprecated.
- **Multibyte trim functions**: `mb_trim()`, `mb_ltrim()`, `mb_rtrim()` are
  now built-in — no need for custom implementations.
- **Lazy objects**: `ReflectionClass::newLazyGhost()` and
  `newLazyProxy()` are available — useful for DI containers and ORMs. If the
  project implements its own lazy proxy pattern, consider suggesting the
  native API.

## General PHP Review Checklist

These are PHP-specific issues worth checking beyond the PSR standards:

- **Strict types**: Covered above — mandatory for all files.
- **Type declarations**: Are parameter types, return types, and property types declared?
  Prefer union types, intersection types, and DNF types over mixed.
- **Readonly properties**: For value objects and DTOs, use `readonly` (PHP 8.1+) or
  asymmetric visibility (PHP 8.4) depending on whether the value needs internal mutability.
- **Enums**: Prefer enums over class constants for fixed sets of values (PHP 8.1+).
- **Named arguments**: When calling functions with many optional parameters, named
  arguments improve readability.
- **Match expressions**: Prefer `match` over `switch` for value-returning logic — it
  uses strict comparison and doesn't fall through.
- **Null safety**: Use null coalescing (`??`), nullsafe operator (`?->`), and avoid
  suppressing errors with `@`.
- **Array functions vs loops**: Prefer `array_map`, `array_filter`, `array_reduce`
  for transformations when it improves clarity.
- **Exception hierarchy**: Custom exceptions should extend SPL exceptions. Catch
  specific exceptions, not `\Exception` or `\Throwable` broadly.
- **Composer autoload optimization**: `composer dump-autoload -o` for production.
  Check for `classmap` usage in legacy code that should migrate to PSR-4.
