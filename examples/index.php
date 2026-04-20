<?php

declare(strict_types=1);

require __DIR__ . '/_layout.php';

page_start('Overview');
?>
<section>
    <h2>About this demo</h2>
    <p>
        This sandbox exercises the <code>kayrah87/dummyjson-user-connector</code> package —
        a framework-agnostic PHP 8.4 connector for the
        <a href="https://dummyjson.com/docs/users">DummyJSON users API</a>.
    </p>
    <p>
        Each demo page exercises one public method of
        <code>UserService</code>, lets you submit real input, and shows the
        response (or the exception that was thrown). Source-code snippets on
        each page show exactly what the consumer code looks like.
    </p>
</section>

<section>
    <h2>Public API covered</h2>
    <dl>
        <dt><a href="get-user.php">Get User</a> &rarr; <code>getUser(int $id): User</code></dt>
        <dd>
            Fetches a single user by ID. Demonstrates happy-path JSON decoding
            into the <code>User</code> DTO plus the <code>UserNotFoundException</code>
            path when the ID doesn't exist.
        </dd>

        <dt><a href="list-users.php">List Users</a> &rarr; <code>listUsers(int $limit = 30, int $skip = 0): UserPage</code></dt>
        <dd>
            Paginated list. Returns a <code>UserPage</code> envelope that
            implements <code>Countable</code> and <code>IteratorAggregate</code>,
            so <code>count($page)</code> and <code>foreach</code> just work.
        </dd>

        <dt><a href="create-user.php">Create User</a> &rarr; <code>createUser(string $firstName, string $lastName, string $email): User</code></dt>
        <dd>
            POSTs a new user. Input is trimmed and validated <em>before</em>
            the HTTP call — bad input throws <code>ValidationException</code>
            without wasting a round-trip. Returns the full <code>User</code>
            DTO including the generated <code>id</code>.
        </dd>
    </dl>
</section>

<section>
    <h2>Exception hierarchy</h2>
    <p>
        Every error raised by the package implements the marker interface
        <code>DummyJsonException</code>, so one <code>catch</code> can trap
        anything from the package:
    </p>
    <pre>try {
    $user = $service-&gt;getUser(1);
} catch (DummyJsonException $e) {
    // $e is one of: UserNotFound, Validation, Transport, Api, InvalidResponse
}</pre>
    <ul>
        <li><code>UserNotFoundException</code> — 404 responses, exposes <code>getUserId()</code></li>
        <li><code>ValidationException</code> — bad input or 400 responses, exposes <code>getField()</code></li>
        <li><code>TransportException</code> — network/DNS/timeout failure, exposes <code>getRequestMethod()</code> / <code>getRequestUri()</code></li>
        <li><code>ApiException</code> — other non-2xx responses, exposes <code>getStatusCode()</code> + <code>getResponse()</code></li>
        <li><code>InvalidResponseException</code> — malformed or unexpected response shape, exposes <code>getResponse()</code></li>
    </ul>
</section>

<section>
    <h2>How this demo was wired</h2>
    <p>
        The service is instantiated with one line:
    </p>
    <pre>$service = UserService::create();</pre>
    <p>
        That static factory uses <code>php-http/discovery</code> to find the
        PSR-18 client and PSR-17 factories already installed in the project —
        in this sandbox, that's Guzzle 7 via <code>composer require</code>. No
        other setup needed.
    </p>
</section>
<?php
page_end();
