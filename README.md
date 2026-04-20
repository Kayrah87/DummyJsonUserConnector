# DummyJSON User Connector

A framework-agnostic PHP 8.4 connector for the [DummyJSON](https://dummyjson.com/docs/users)
users API. Drops into Laravel, Drupal, WordPress, or vanilla PHP with no
framework coupling.

> For the design record — the 25 locked-in decisions behind every part of this
> implementation — see [TECHNICAL_TASK.md](TECHNICAL_TASK.md).

---

## Requirements

- **PHP 8.4+**
- A **PSR-18** HTTP client installed in your project (Guzzle, Symfony HTTP
  Client, etc.)
- **PSR-17** factories (shipped by most PSR-18 client packages, or standalone
  via `nyholm/psr7`)

## Installation

Because this package isn't published to Packagist, add a VCS repository to
your root `composer.json`:

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/Kayrah87/DummyJsonUserConnector" }
    ],
    "require": {
        "kayrah87/dummyjson-user-connector": "dev-main"
    }
}
```

Then:

```bash
composer install
```

If your project doesn't already have a PSR-18 client, install one — Guzzle is
the easiest choice:

```bash
composer require guzzlehttp/guzzle
```

---

## Quick start

```php
use Kayrah87\DummyJsonUserConnector\Service\UserService;

$service = UserService::create();

// Fetch a single user
$user = $service->getUser(1);
echo $user->firstName;

// Paginated list — defaults to limit: 30, skip: 0
$page = $service->listUsers(limit: 10, skip: 20);
foreach ($page as $user) {
    echo $user->email . PHP_EOL;
}
echo "showing {$page->skip}–" . ($page->skip + count($page)) . " of {$page->total}";

// Create a user (returns full DTO including the new id)
$created = $service->createUser('Alice', 'Smith', 'alice@example.com');
echo "new user id: {$created->id}";
```

`UserService::create()` auto-discovers any installed PSR-18 client and PSR-17
factories via `php-http/discovery`.

---

## Framework integration

### Vanilla PHP

```php
require 'vendor/autoload.php';

use Kayrah87\DummyJsonUserConnector\Service\UserService;

$service = UserService::create();
$user = $service->getUser(1);
```

### Laravel

Register as a singleton in a service provider:

```php
// app/Providers/AppServiceProvider.php

use Illuminate\Support\ServiceProvider;
use Kayrah87\DummyJsonUserConnector\Service\UserService;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            UserService::class,
            fn () => UserService::create(),
        );
    }
}
```

Then inject by constructor anywhere in the app:

```php
use Kayrah87\DummyJsonUserConnector\Service\UserService;

class UserController
{
    public function __construct(private UserService $users) {}

    public function show(int $id)
    {
        return response()->json($this->users->getUser($id));
    }
}
```

Laravel ships Guzzle 7, so discovery wires it up automatically — no further
configuration needed.

### Drupal

Define the service in your module's `*.services.yml`:

```yaml
# modules/custom/mymodule/mymodule.services.yml
services:
  mymodule.dummyjson_users:
    class: Kayrah87\DummyJsonUserConnector\Service\UserService
    factory: ['Kayrah87\DummyJsonUserConnector\Service\UserService', 'create']
```

Resolve via the service container:

```php
$userService = \Drupal::service('mymodule.dummyjson_users');
$user = $userService->getUser(1);
```

Drupal ships Guzzle, so discovery finds it out of the box.

### WordPress

WordPress has no DI container — bootstrap once and expose as a helper:

```php
// mymodule.php (plugin bootstrap)
require_once __DIR__ . '/vendor/autoload.php';

use Kayrah87\DummyJsonUserConnector\Service\UserService;

function dummyjson_users(): UserService
{
    static $service = null;
    return $service ??= UserService::create();
}

// Usage anywhere:
$user = dummyjson_users()->getUser(1);
```

---

## Public API

### `getUser(int $id): User`

Retrieves a single user by ID.

Throws:
- `UserNotFoundException` — 404
- `TransportException` — network/DNS/timeout failure
- `ApiException` — other non-2xx
- `InvalidResponseException` — malformed or unexpected response shape

### `listUsers(int $limit = 30, int $skip = 0): UserPage`

Returns a paginated page of users. Defaults match the DummyJSON API
(`limit=30, skip=0`). Pass `limit: 0` to request all users.

`UserPage` implements `Countable` and `IteratorAggregate`, so
`count($page)` and `foreach ($page as $user)` just work.

Throws: `TransportException`, `ApiException`, `InvalidResponseException`.

### `createUser(string $firstName, string $lastName, string $email): User`

Creates a user via `POST /users/add`. Inputs are trimmed and validated
client-side in `NewUserInput` *before* the HTTP call — invalid input fails
fast without a round trip.

Returns the full `User` DTO (the new ID is available as `$user->id`).

Throws:
- `ValidationException` — empty fields, invalid email, control characters,
  **or** a 400 response from the API (both paths map to the same type)
- `TransportException`, `ApiException`, `InvalidResponseException` as above

> ⚠ **Simulated endpoint**: DummyJSON's `POST /users/add` does not persist
> the user. The returned `id` is sequential (e.g. `209`) but is not
> retrievable via a later `getUser()` call. Do not assume round-trip.

---

## DTOs

All DTOs use **camelCase** throughout (property names, `toArray()` keys,
`JsonSerializable` output) to match the source API. Translating into
`snake_case` or other conventions is the consumer's responsibility.

| DTO | Fields | Notes |
|---|---|---|
| `User` | `id`, `firstName`, `lastName`, `email` | `JsonSerializable`, `toArray()` |
| `UserPage` | `items` (`list<User>`), `total`, `skip`, `limit` | `Countable`, `IteratorAggregate`; JSON output uses API envelope key `users` |
| `NewUserInput` | `firstName`, `lastName`, `email` | Construction validates and trims |

Each DTO also exposes `static fromApiArray(array $data): self` for
reconstructing from a decoded JSON payload, throwing
`InvalidResponseException` (or `ValidationException` for `NewUserInput`) on
bad shape.

---

## Configuration

### Explicit constructor

Use the constructor directly when you want control over the HTTP client
(timeouts, middleware, headers, etc.) or need to point at a staging endpoint:

```php
use GuzzleHttp\Client;
use Nyholm\Psr7\Factory\Psr17Factory;
use Kayrah87\DummyJsonUserConnector\Service\UserService;

$psr17 = new Psr17Factory();

$service = new UserService(
    client: new Client(['timeout' => 5, 'connect_timeout' => 2]),
    requestFactory: $psr17,
    streamFactory: $psr17,
    baseUri: 'https://dummyjson.com',
    logger: $psr3Logger, // optional
);
```

### PSR-3 logging

Pass any PSR-3 `LoggerInterface` to surface request and error events:

| Level | When |
|---|---|
| `debug` | Every outbound request |
| `info` | 404 responses (user not found) |
| `warning` | Other 4xx/5xx responses (body truncated to 1 KB) |
| `error` | Transport failures and response-shape mismatches |

User-supplied data (names, emails) is placed in the structured `$context`
array, never interpolated into the log message — prevents log injection.
`createUser` logs the *fact* of a POST plus field names only, never the raw
submitted values (PII-safe by default).

### Custom base URI

Useful for testing against a local mock server:

```php
$service = new UserService(
    client: $client,
    requestFactory: $psr17,
    streamFactory: $psr17,
    baseUri: 'http://localhost:8080',
);
```

---

## Exception hierarchy

All exceptions implement the marker interface `DummyJsonException`, so a
consumer can trap any failure from this package with one `catch`:

```php
use Kayrah87\DummyJsonUserConnector\Exception\DummyJsonException;

try {
    $user = $service->getUser(1);
} catch (DummyJsonException $e) {
    // $e is one of: UserNotFound, Validation, Transport, InvalidResponse, Api
}
```

```
DummyJsonException (interface — marker)
├── AbstractResponseException (abstract)
│   │   getResponse(): ?ResponseInterface
│   │   getResponseBody(): ?string
│   ├── InvalidResponseException
│   ├── UserNotFoundException      (+ getUserId(): int)
│   └── ApiException               (+ getStatusCode(): int)
├── TransportException             (+ getRequestMethod(), getRequestUri())
└── ValidationException            (+ getField(): ?string)
```

`AbstractResponseException` caches the response body at construction because
PSR-7 `StreamInterface` is not guaranteed to be rewindable. The underlying
PSR-18 exception (or `JsonException`, etc.) is chained as `$previous` so
tooling like Sentry picks up the full error chain automatically.

---

## Testing

For contributors working on this package:

```bash
composer test             # unit suite (hermetic, offline)
composer test:integration # smoke tests against the live DummyJSON API
composer test:coverage    # unit tests with clover coverage report
composer check-coverage   # enforce 90% line-coverage threshold
composer lint             # php-cs-fixer dry-run (read-only)
composer fix              # php-cs-fixer write mode
composer stan             # PHPStan max + strict-rules + phpunit rules
composer ci               # full local CI: lint → stan → coverage → threshold

---or---

composer verify           # Runs fix (writes) then stan, unit tests, integration tests, coverage, threshold check. Use before committing to verify code quality.
```

The integration suite is opt-in (separate PHPUnit suite) — running
`composer test` offline never hits the network.

---

## License

[MIT](LICENSE) © 2026 Kayleigh Whitehurst.
