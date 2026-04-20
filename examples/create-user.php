<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/_layout.php';

use Kayrah87\DummyJsonUserConnector\Exception\DummyJsonException;
use Kayrah87\DummyJsonUserConnector\Exception\ValidationException;
use Kayrah87\DummyJsonUserConnector\Service\UserService;

$service = UserService::create();

$firstName = (string) ($_POST['firstName'] ?? '');
$lastName = (string) ($_POST['lastName'] ?? '');
$email = (string) ($_POST['email'] ?? '');
$submitted = $_SERVER['REQUEST_METHOD'] === 'POST';
$result = null;
$error = null;

if ($submitted) {
    try {
        $result = $service->createUser($firstName, $lastName, $email);
    } catch (DummyJsonException $e) {
        $error = $e;
    }
}

page_start('Create User');
?>
<section>
    <h2><code>createUser(string $firstName, string $lastName, string $email): User</code></h2>
    <p>
        Creates a user via <code>POST /users/add</code>. Input passes through
        <code>NewUserInput</code>, which <strong>validates client-side before
        any HTTP call</strong> — empty fields, whitespace-only values, invalid
        email format, or control characters all raise
        <code>ValidationException</code> without wasting a round-trip.
    </p>
    <p>
        Returns the full <code>User</code> DTO — the new <code>id</code> is
        available as <code>$user-&gt;id</code>. Input is also trimmed on the
        way in, so <code>"  Alice  "</code> becomes <code>"Alice"</code>
        before being sent.
    </p>
    <p>
        <strong>Simulated endpoint caveat:</strong> DummyJSON's
        <code>POST /users/add</code> does not actually persist the user. The
        returned <code>id</code> is sequential (e.g. <code>209</code>) but is
        not retrievable via a later <code>getUser()</code> call. The connector
        faithfully mirrors that behaviour — do not assume round-trip.
    </p>
    <p>
        <strong>Throws:</strong> <code>ValidationException</code> (client-side
        rules or a 400 response from the API),
        <code>TransportException</code>, <code>ApiException</code>,
        <code>InvalidResponseException</code>.
    </p>
</section>

<section>
    <h2>Try it</h2>
    <form method="post">
        <label>
            First name
            <input type="text" name="firstName" value="<?= htmlspecialchars($firstName) ?>" placeholder="Alice">
        </label>
        <label>
            Last name
            <input type="text" name="lastName" value="<?= htmlspecialchars($lastName) ?>" placeholder="Smith">
        </label>
        <label>
            Email
            <input type="text" name="email" value="<?= htmlspecialchars($email) ?>" placeholder="alice@example.com">
        </label>
        <button type="submit">Create user</button>
    </form>
    <p class="hint">
        Try submitting valid input for the happy path — or leave a field empty,
        enter <code>not-an-email</code>, or pad a name with spaces to see
        validation behaviour.
    </p>
</section>

<?php if ($result !== null): ?>
<section>
    <h2>Response — new <code>User</code> DTO</h2>
    <pre><?= htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)) ?></pre>
    <p>
        Newly assigned <code>id</code>: <strong><?= $result->id ?></strong>
        <span class="hint">(remember — simulated, not actually persisted upstream)</span>
    </p>
</section>
<?php endif; ?>

<?php if ($error !== null): ?>
<section class="error">
    <h2>Exception caught</h2>
    <pre>Class:   <?= htmlspecialchars($error::class) ?>

Message: <?= htmlspecialchars($error->getMessage()) ?><?php if ($error instanceof ValidationException && $error->getField() !== null): ?>


Field:   <?= htmlspecialchars((string) $error->getField()) ?><?php endif; ?>
</pre>
    <?php if ($error instanceof ValidationException): ?>
    <p class="hint">
        <code>ValidationException</code> fires from two paths that both map to
        the same exception type:
        (1) client-side pre-flight checks in <code>NewUserInput</code> — no
        HTTP call happens when this fails;
        (2) server-side 400 responses from the API, surfaced via
        <code>ValidationException::apiRejected()</code>. If
        <code>getField()</code> is set, it's the client-side path.
    </p>
    <?php endif; ?>
</section>
<?php endif; ?>

<section>
    <h2>Consumer code</h2>
    <pre>use Kayrah87\DummyJsonUserConnector\Exception\ValidationException;
use Kayrah87\DummyJsonUserConnector\Service\UserService;

$service = UserService::create();

try {
    $user = $service-&gt;createUser(
        firstName: <?= var_export($firstName !== '' ? $firstName : 'Alice', true) ?>,
        lastName:  <?= var_export($lastName !== '' ? $lastName : 'Smith', true) ?>,
        email:     <?= var_export($email !== '' ? $email : 'alice@example.com', true) ?>,
    );
    printf("created user #%d\n", $user-&gt;id);
} catch (ValidationException $e) {
    printf("invalid input on field %s: %s\n", $e-&gt;getField() ?? '(unknown)', $e-&gt;getMessage());
}</pre>
</section>

<?php
page_end();
