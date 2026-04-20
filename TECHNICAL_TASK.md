# Technical Task Design Record

Built By: Kayleigh Whitehurst <kayleigh.whitehurst@outlook.com>

Date: 2026-04-20

Built for a technical challenge for Plentific. **Not to be published to Packagist.**

AI Used for:
- Interpreting plan into this task document and exploring technical questions
- Scaffolding the repo
- Providing technical advice during the build
- Error/Bug Analysis
- Test Coverage suggestions
- Code Review and refactoring suggestions

---

## What this document is

This file captures the **design process** behind the DummyJSON User Connector
package — the requirements read from the brief, the architecture sketch, and
the 25 open technical questions that were answered before implementation
started. The decisions recorded here shaped every choice in the codebase.

For **how to use the package**, see [README.md](README.md).

---

## Description

A framework-agnostic PHP 8.4 composer package that provides a service for retrieving and creating users against the [DummyJSON](https://dummyjson.com/docs/users) API.

---

## Requirements (from brief)

### Functional

- [x] Retrieve a single user by ID
- [x] Retrieve a paginated list of users
- [x] Create a new user from `firstName`, `lastName`, `email`; return the new user ID
- [x] All users returned by the service are converted to DTOs that:
  - implement `JsonSerializable`
  - expose a `toArray()` (or equivalent) conversion to a standard array
  - only collect `id`, `firstName`, `lastName`, `email` (ignore the rest of the DummyJSON payload)

### Non-functional

- [x] **Framework-agnostic** — must drop into Laravel, Drupal, WordPress, or vanilla PHP with no framework coupling
- [x] Target **PHP 8.4**, strict types, modern PSR standards (PSR-4, PSR-12)
- [x] **PHPStan** static analysis (ideally at `max` / level 9)
- [x] **Unit tests** for the service; mocked transport so tests pass offline and are deterministic
- [x] Optional integration/API tests separate from the default test run
- [x] Clean exception handling: generic transport/API errors surfaced as **domain-specific exceptions** with useful context for consumers
- [x] No reinvented wheels — use established standalone packages where sensible (no full frameworks)

### Known DummyJSON quirks to design around

- `POST /users/add` is **simulated** — the new user is not actually persisted. The endpoint still returns a sequential integer `id` (e.g. `209`). Tests and callers must not assume the returned ID is later retrievable.
- `GET /users` default `limit` is 30; `limit=0` returns all. Paging params are `limit` and `skip` (offset-based). Response envelope: `{ users, total, skip, limit }`.
- The API returns **camelCase** fields (`firstName`, `lastName`).
- No auth is needed for the public user endpoints used here.

---

## Proposed architecture (sketch — to be confirmed)

```
src/
  Client/
    DummyJsonClient.php          // thin PSR-18 wrapper, JSON decode, error mapping
  Service/
    UserService.php              // public API: getUser, listUsers, createUser
  Dto/
    User.php                     // id, firstName, lastName, email + JsonSerializable + toArray
    UserPage.php                 // items[], total, skip, limit (pagination envelope)
    NewUserInput.php             // input DTO for createUser (firstName, lastName, email)
  Exception/
    DummyJsonException.php       // marker interface
    UserNotFoundException.php
    TransportException.php
    InvalidResponseException.php
    ValidationException.php
tests/
  Unit/                          // mocked HTTP, deterministic
  Integration/                   // hits real API, opt-in via env flag
```

Transport layer via **PSR-18 `ClientInterface`** + **PSR-17 factories** so the consumer brings their own HTTP client (Guzzle, Symfony, etc.). The package depends on interfaces, not concrete clients.

> During implementation, `DummyJsonClient.php` was collapsed into `UserService.php` directly — the shape of the service (3 methods, one PSR-18 dependency) didn't justify a second layer of indirection. The rest of the sketch held up unchanged, with the addition of `AbstractResponseException` (see Q2.2) and `ApiException` (see Q2.1).

---

## Open technical questions

These were answered (or explicitly deferred to defaults) before implementation began.

### 1. HTTP transport & dependencies

- **Q1.1** Depend only on `psr/http-client`, `psr/http-factory`, `psr/http-message` and let the consumer supply the client? Or bundle `guzzlehttp/guzzle` as a default and allow override? (PSR-only is more agnostic; bundling is friendlier out-of-the-box.)
  - Use PSR-only with discovery. Bundling versions of guzzle could cause issues with anything that already has a strict version pin of guzzle
- **Q1.2** Are retries/backoff in scope for v1, or do we assume the consumer configures their own HTTP client middleware? If in scope, built-in or via `php-http/retry-plugin`?
  - Out of scope for v1. Consumer configures retries via their own HTTP client middleware. DummyJSON is stable, and baking retries in would duplicate what host frameworks (Laravel, Guzzle middleware, etc.) already provide — and adds surface area (retry semantics, exception mapping) for little gain.
- **Q1.3** Should request/connect timeouts be configurable via the service constructor, or left to the injected client?
  - Left to the injected client. PSR-18 keeps timeouts on the client config, not the request — exposing a service-level knob would fight the standard and leak transport concerns. Consumers already have idiomatic ways to configure timeouts in their framework of choice.

### 2. Exception design

- **Q2.1** What does the consumer most want to distinguish? Proposed hierarchy:
  - `DummyJsonException` (interface, all thrown errors implement)
  - `UserNotFoundException` (404 on `GET /users/{id}`)
  - `ValidationException` (bad input — invalid email, missing fields)
  - `TransportException` (network/timeout, wraps PSR `ClientExceptionInterface`)
  - `InvalidResponseException` (non-JSON, unexpected shape, schema mismatch)
  - `ApiException` (catch-all for 4xx/5xx we don't otherwise classify)
  - Accepted as listed. `DummyJsonException` stays a marker **interface** (PSR-18 pattern) so each concrete class can extend the most appropriate native parent: `ValidationException` → `\InvalidArgumentException`, the rest → `\RuntimeException`. `ApiException` carries `getStatusCode(): int` and `getResponseBody(): ?string` so it's useful, not just a bucket. No `RateLimitException` for v1 — DummyJSON doesn't rate-limit in practice.
- **Q2.2** Do we want to preserve the original PSR exception / response on the domain exception (e.g. `->getResponse()`, `->getPrevious()`)? Strongly recommend yes.
  - Yes, via both channels. Always chain the underlying `ClientExceptionInterface` / `\JsonException` as `$previous` so standard tooling (debuggers, Sentry) picks it up. Response-bearing exceptions (`ApiException`, `UserNotFoundException`, `InvalidResponseException`) also expose a typed `getResponse(): ?ResponseInterface`; `TransportException` and `ValidationException` don't have one and don't fake one. Read the response body to a string at construction time — `StreamInterface` isn't guaranteed rewindable.
- **Q2.3** Should `ValidationException` be thrown client-side before the HTTP call (pre-flight validation), server-side only (based on 400 responses), or both?
  - Both — in case a consumer circumvents upstream/front-end validation. Client-side pre-flight stays narrow: non-empty `firstName`/`lastName`/`email` after trim, `email` passes `filter_var(..., FILTER_VALIDATE_EMAIL)`, and all three fields are rejected if they contain control characters (see Q4.2). Any real `400` from the API also maps to `ValidationException`. (DummyJSON's `POST /users/add` is simulated and won't actually reject bad input, but the server-side path exists for correctness and portability.)

### 3. DTO shape & serialisation

- **Q3.1** Field naming in the DTO & `toArray()` output — match the API's **camelCase** (`firstName`), or normalise to **snake_case** (`first_name`)? The brief writes "firstname" (lowercase, one word) which is ambiguous. Recommend camelCase to match the source API and idiomatic PHP property naming.
  - camelCase throughout — DTO properties, `toArray()` keys, and `JsonSerializable` output all match the source API. Package consumers are responsible for translating into their own stack's conventions (e.g. snake_case for Laravel/Eloquent, etc.). Keeps this package a thin, unopinionated connector with no translation layer to maintain or test.
- **Q3.2** DTOs as `readonly` classes (PHP 8.2+) — agreed? (Immutability + cleaner equality.)
  - Yes — `final readonly class` for all DTOs (`User`, `UserPage`, `NewUserInput`). DTOs are value snapshots, not extension points: mutability serves nothing, and `final` prevents subclassing since consumers should compose, not extend. Pairs cleanly with `JsonSerializable` (no defensive copies needed).
- **Q3.3** Hand-roll the mapping (API array → DTO) or use `symfony/serializer` / `cuyz/valinor`? Hand-rolled keeps deps light; Valinor gives strong typing + validation with minimal code. Lean toward hand-rolled given scope.
  - Hand-rolled, via a static `fromApiArray(array $data): self` named constructor on each DTO. Three DTOs × ~4 scalar fields doesn't justify a mapping library. Keeps shape knowledge next to the shape, gives `InvalidResponseException` precise "missing key: X" / "wrong type for Y" messages, and stays PHPStan-max clean with explicit type checks.
- **Q3.4** Pagination return type: dedicated `UserPage` DTO (items + meta), or a simple `array` + a separate meta object? Dedicated DTO is cleaner and also `JsonSerializable`.
  - Dedicated `UserPage` DTO (`final readonly`, implements `JsonSerializable`, `Countable`, `IteratorAggregate`). Properties: `items` (`list<User>`), `total`, `skip`, `limit`. `jsonSerialize()` emits the API envelope key `users` (not `items`) so the output round-trips against DummyJSON's shape; the PHP-side property is named `items` for read ergonomics. Countable/iterable give consumers `count($page)` and `foreach ($page as $user)` for free.
- **Q3.5** Should `createUser` return **just the `int` ID** (literal reading of the brief) or a full `User` DTO? Brief says "returning a User ID" — I'll return `int` and note it, unless you'd prefer the DTO.
  - Return the full `User` DTO. DummyJSON's `POST /users/add` already echoes the submitted fields plus the new `id`, so we have the data for free — discarding it to return a scalar would be hostile to consumers. The ID is still trivially available via `$user->id`, so this is strictly more informative than returning `int` while still satisfying the brief's "return a User ID" intent. Matches `getUser()`'s shape, keeps the service API cohesive. README will call out the simulated-endpoint quirk explicitly (returned `id` is not retrievable via a later `getUser` call).

### 4. Input validation

- **Q4.1** Validate email format before the POST? If yes, `egulias/email-validator` (RFC) or native `filter_var`? Lean toward `filter_var` for zero extra deps unless you want strict RFC.
  - Native `filter_var(..., FILTER_VALIDATE_EMAIL)`. Zero deps, catches the obviously bad (empty, missing `@`, spaces) which is all a pre-flight check needs. DummyJSON is a simulated endpoint that doesn't validate emails at all, so a strict RFC-5322 validator would be aimed at a stricter API than we're talking to. Server-side `ValidationException` path from Q2.3 still covers anything stricter.
- **Q4.2** Trim / normalise names, or pass through verbatim?
  - Trim all three fields (applied once in `NewUserInput`'s constructor), and reject control characters (`\x00-\x1F\x7F`) via a `preg_match` check — throws `ValidationException` if found. No further normalisation (no lowercasing email, no name-casing, no whitespace collapsing) — those are opinionated choices that belong to the consumer's app, not a thin connector. Rejecting control chars is cheap defensive hygiene: no legitimate name contains a null byte, and failing fast beats passing weird bytes to DummyJSON, loggers, or consumer databases. Injection-wise: JSON body is always built via `json_encode(..., JSON_THROW_ON_ERROR)` (never string concat), and any future PSR-3 logging puts user input in the `$context` array, never interpolated into the message — closes log-forging and JSON-injection footguns.

### 5. Testing strategy

- **Q5.1** Mocking: `php-http/mock-client` (PSR-agnostic, plays well with the PSR-18 choice) vs Guzzle's `MockHandler`. Recommend `php-http/mock-client` to stay transport-agnostic.
  - `php-http/mock-client` + `nyholm/psr7` (PSR-17 factories + PSR-7 request/response) in `require-dev`. Transport-agnostic, matches the PSR-only choice from Q1.1, and avoids having the test suite silently depend on Guzzle when the package itself doesn't.
- **Q5.2** Test framework: PHPUnit 11 (matches PHP 8.4 well) — any preference otherwise?
  - PHPUnit 11 (`^11.0`), plus `phpstan/phpstan-phpunit` for PHPUnit-aware static analysis. As a `require-dev` dependency, PHPUnit is fully contained to this package's own CI/local workflow — consumer apps pinned to older PHPUnit majors (or using Pest, or no test framework) are unaffected. Shipping no `composer.lock` (library convention, already in `.gitignore`) keeps it that way.
- **Q5.3** Integration tests that hit the real DummyJSON API — include them, gated behind `RUN_INTEGRATION=1` so CI/unit runs stay hermetic? Recommend yes.
  - Yes. Two separate PHPUnit suites declared in `phpunit.xml.dist`: `unit` (`tests/Unit`, default, hermetic) and `integration` (`tests/Integration`, opt-in via `composer test:integration`). `composer test` and the main CI job run `unit` only — no network dependency on the default build. Optional nightly CI job runs `integration` to catch upstream API drift. Directory-based split (not env-var gates inside individual tests) so running `composer test` offline doesn't produce a wall of skipped tests. Integration scope stays minimal: smoke-test `getUser`, `listUsers`, `createUser`, and one 404 path — enough to confirm wire-format assumptions, not a full branch sweep.
- **Q5.4** Coverage target / enforcement? (e.g. 90% lines via `pcov` in CI.)
  - 90% line coverage on the `unit` suite, enforced in CI. Coverage driver: `pcov` (fast, coverage-only, no debugger overhead). Enforcement: small hand-rolled `scripts/check-coverage.php` that parses the clover XML and fails if below threshold — no external service dep (Codecov/Coveralls) for a trivial numeric check. 90% not 100% so reasonable defensive branches can exist without forced tests; any skipped code is named explicitly in the report.

### 6. Tooling & quality gates

- **Q6.1** PHPStan level — `max` (level 10 on current PHPStan) with `bleedingEdge`? Include `phpstan/phpstan-strict-rules`?
  - Level `max` (currently 10 on PHPStan 2.x) + `phpstan/phpstan-strict-rules` + `phpstan/phpstan-phpunit`. No `bleedingEdge` in v1 — preview rules churn between minors and can break CI on PHPStan bumps; reproducible analysis level matters more than chasing previews for a submitted challenge. Small codebase means the noise tax of `max` is negligible.
- **Q6.2** Code style: `laravel/pint`, `friendsofphp/php-cs-fixer`, or PER-CS via `php-cs-fixer`? Any house style?
  - `friendsofphp/php-cs-fixer` with the `@PER-CS2.0` ruleset (plus `@PER-CS2.0:risky`, `declare_strict_types`, `ordered_imports` alpha, `no_unused_imports`). PER-CS is the standards-body successor to PSR-12 — correct choice for a framework-agnostic PSR-oriented package. Pint was considered but is a Laravel project's opinionated wrapper, which sends the wrong signal for a framework-neutral library. Exposed via `composer fix` (writes) and `composer lint` (dry-run, CI-friendly).
- **Q6.3** CI: GitHub Actions matrix (PHP 8.4 only, or also 8.3 for portability)? OS: ubuntu-latest sufficient?
  - Composer constraint `"php": "^8.4"` (≥ 8.4, < 9.0 — target 8.4 or higher, not 8.3 or lower). CI matrix: PHP 8.4 (floor) + PHP 8.5 (current latest, released Nov 2025) — both run lint, stan, and the unit suite. `ubuntu-latest` only (no Windows/macOS — expensive minutes, redundant for a pure-PHP package). GitHub Actions as the CI provider (ecosystem default for public GitHub PHP packages). Integration suite (Q5.3) runs on a separate optional nightly job, not the default push/PR build.
- **Q6.4** Rector in scope, or out?
  - Out of scope for v1. Rector's strength is dragging legacy codebases forward — greenfield strict-typed PHP 8.4 code has nothing for it to modernise. The niches it owns (structural refactors, PHP-version upgrades) are already covered by php-cs-fixer (style), PHPStan (types/bugs), and PHPUnit (behaviour). Adds a `rector.php` config and another CI job for no real return on a small package. Revisit if the code grows significantly, or when the PHP floor is bumped to 8.5/8.6 later.

### 7. Packaging & ergonomics

- **Q7.1** Vendor/package name for `composer.json` — `kayrah87/dummyjson-user-connector`? Namespace — `Kayrah87\DummyJsonUserConnector`?
  - Accepted as proposed. Package: `kayrah87/dummyjson-user-connector` (kebab-case Packagist convention, unambiguously mine). Namespace: `Kayrah87\DummyJsonUserConnector` (PSR-4, PascalCase). Autoload: `Kayrah87\\DummyJsonUserConnector\\` → `src/`, `Kayrah87\\DummyJsonUserConnector\\Tests\\` → `tests/` via `autoload-dev`. Not to be published to Packagist (per brief), so name-collision concerns don't apply.
- **Q7.2** License — MIT. `LICENSE` file at repo root (`Copyright (c) 2026 Kayleigh Whitehurst`), `"license": "MIT"` in `composer.json`. Industry default for PHP/Composer packages, permissive, no surprise to reviewers. Apache 2.0 was considered but its patent grant is overkill for a four-field connector.
- **Q7.3** Configuration surface for the service: constructor args (base URI, PSR-18 client, PSR-17 factories, optional PSR-3 logger), or a small `Config`/options object? Constructor with a values object is cleanest at PHP 8.4.
  - Named constructor args with promoted `readonly` properties — for five parameters, a `Config` object is a solution looking for a problem (PHP 8.0 named args already give order-free, pick-what-you-set ergonomics, and defaults live on the signature). Signature: `__construct(ClientInterface $client, RequestFactoryInterface $requestFactory, StreamFactoryInterface $streamFactory, string $baseUri = 'https://dummyjson.com', LoggerInterface $logger = new NullLogger())`. Plus a static `UserService::create()` named constructor that auto-discovers PSR-18/PSR-17 via `php-http/discovery` (Q1.1) so vanilla-PHP consumers get zero-config instantiation. If configuration grows past ~8 knobs later, introduce a `Config` value object then — the refactor is mechanical and backwards-compatible.
- **Q7.4** Add a PSR-3 `LoggerInterface` hook for request/response logging (optional, null logger default)? Useful for the "communicate errors to other developers" brief point.
  - Yes — optional PSR-3 logger with `NullLogger` default (Q7.3 constructor already reflects this). Level map: `debug` for requests/2xx responses, `info` for 404s, `warning` for other 4xx/5xx (with truncated response body), `error` for transport failures and shape mismatches. User-controlled strings go in the PSR-3 `$context` array, never interpolated into the message — closes log-forging (`\n` injection). Response bodies truncated to ~1KB in logs. **PII-safe by default**: `createUser` logs the fact of a POST + field names, never the raw submitted values (names/emails are personal data). Enforced via a private `logRequest`/`logResponse` helper so discipline lives in one place.
- **Q7.5** Any Laravel/WordPress "adapter" examples to ship in the README, or leave integration examples out?
  - Yes — short usage snippets in the README for all four target environments (vanilla PHP, Laravel, Drupal, WordPress). Each ~8–12 lines, purely documentation. **No adapter packages shipped**: the brief asks for a framework-agnostic package, not a Laravel/Drupal/WordPress one, and doubling surface area into adapter sub-packages is out of scope. Snippets must not introduce framework-specific imports into `src/` — they are README-only, the package stays zero-framework-dependency. Directly addresses the brief's "drops into Laravel, Drupal, WordPress, or vanilla PHP" requirement by *showing* it rather than just claiming it.

### 8. Scope boundaries

- **Q8.1** Out of scope confirmation: update, delete, search, filter, auth, carts/posts/todos — yes? Only `getById`, `list`, `create` per the brief.
  - Confirmed out of scope. Public API is strictly the three operations from the brief: `getUser($id)`, `listUsers(...)`, `createUser(...)`. Explicitly out: `PUT /users/:id` (update), `DELETE /users/:id` (delete), `GET /users/search`, `GET /users/filter`, `POST /auth/*` and any auth-gated endpoints, and every non-user DummyJSON resource (posts, todos, carts, products, recipes, comments, etc.). Keeps the exception hierarchy lean (no `AuthException`, no `RateLimitException`, no `ConflictException`) and the service API cohesive.
- **Q8.2** Any caching (PSR-6 / PSR-16)? I'd leave it out of v1 unless requested.
  - Out of scope. Caching can be handled by the host framework (Laravel cache, Drupal cache API, WP transients) or at the HTTP layer via PSR-18 middleware (`kevinrob/guzzle-cache-middleware`, `php-http/cache-plugin`) — the consumer knows their TTLs, cache backend, and invalidation rules; we don't. Same principle as retries (Q1.2): resilience/performance knobs belong in the consumer's stack, not a thin connector. Consumers who want caching wrap `UserService` in a decorator in their own code.
- **Q8.3** Async / concurrent fetch? Out of scope.
  - Confirmed out of scope. PSR-18's `ClientInterface::sendRequest()` is synchronous by contract — supporting async would mean either abandoning PSR-18 (breaks framework-agnosticism) or bolting on a parallel async API. Real-world PHP async is also fragmented across ReactPHP/Amphp/Guzzle-promises/Symfony — picking one breaks the agnostic claim; supporting all is infeasible. Consumers who need concurrency compose around the service (Guzzle `Pool`, `curl_multi_*`, framework-native tools). Same principle as Q1.2 (retries) and Q8.2 (caching): orchestration concerns belong to the consumer.

---

## Build order

The package was built in this order, matching the "inner → outer" strategy:

1. `composer.json`, namespace, autoload.
2. DTOs (`User`, `UserPage`, `NewUserInput`) — shape and validation first.
3. Exceptions (interface, base, and five concretes).
4. `UserService` — wires DTOs and exceptions around PSR-18.
5. Unit tests (59 service + DTO cases with `php-http/mock-client`; 51 exception cases with anonymous subclass for the abstract base = 110 total on first full pass).
6. Integration tests (4 smoke cases, opt-in suite).
7. Tooling: `phpstan.neon.dist`, `.php-cs-fixer.dist.php`, `phpunit.xml.dist`.
8. `scripts/check-coverage.php` and the GitHub Actions CI workflow.

Final: **124 unit + 4 integration tests, 100% line coverage, PHPStan max clean, CS-Fixer clean.**
