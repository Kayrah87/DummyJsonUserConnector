<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/_layout.php';

use Kayrah87\DummyJsonUserConnector\Exception\DummyJsonException;
use Kayrah87\DummyJsonUserConnector\Exception\UserNotFoundException;
use Kayrah87\DummyJsonUserConnector\Service\UserService;

$service = UserService::create();

$id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
$result = null;
$error = null;

if ($id > 0) {
    try {
        $result = $service->getUser($id);
    } catch (DummyJsonException $e) {
        $error = $e;
    }
}

page_start('Get User');
?>
<section>
    <h2><code>getUser(int $id): User</code></h2>
    <p>
        Fetches a single user by ID via <code>GET /users/{id}</code>. The JSON
        response is mapped into a <code>User</code> DTO — a <code>final
        readonly</code> value object carrying <code>id</code>,
        <code>firstName</code>, <code>lastName</code>, and <code>email</code>.
        Additional fields from the API payload (age, phone, password, etc.) are
        deliberately ignored.
    </p>
    <p>
        <strong>Throws:</strong>
        <code>UserNotFoundException</code> on 404,
        <code>TransportException</code> on a network/DNS/timeout failure,
        <code>ApiException</code> on any other non-2xx, and
        <code>InvalidResponseException</code> if the response body is missing
        required fields or has the wrong shape.
    </p>
</section>

<section>
    <h2>Try it</h2>
    <form method="post">
        <label>
            User ID
            <input type="number" name="id" value="<?= htmlspecialchars((string) ($id > 0 ? $id : 1)) ?>" required min="1">
        </label>
        <button type="submit">Fetch user</button>
    </form>
    <p class="hint">
        DummyJSON has ~208 users. Try an ID like <code>1</code>, <code>42</code>,
        or <code>208</code> for success — or something huge like
        <code>999999</code> to trigger <code>UserNotFoundException</code>.
    </p>
</section>

<?php if ($result !== null): ?>
<section>
    <h2>Response — <code>User</code> DTO as JSON</h2>
    <pre><?= htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)) ?></pre>
    <p class="hint">
        The DTO implements <code>JsonSerializable</code>, so
        <code>json_encode($user)</code> produces the wire shape directly —
        no intermediate conversion.
    </p>
</section>
<?php endif; ?>

<?php if ($error !== null): ?>
<section class="error">
    <h2>Exception caught</h2>
    <pre>Class:   <?= htmlspecialchars($error::class) ?>

Message: <?= htmlspecialchars($error->getMessage()) ?><?php if ($error instanceof UserNotFoundException): ?>


UserId:  <?= $error->getUserId() ?><?php endif; ?>
</pre>
    <?php if ($error instanceof UserNotFoundException): ?>
    <p class="hint">
        <code>UserNotFoundException::getUserId()</code> lets consumers branch on
        the looked-up ID without re-parsing the exception message.
    </p>
    <?php endif; ?>
</section>
<?php endif; ?>

<section>
    <h2>Consumer code</h2>
    <pre>use Kayrah87\DummyJsonUserConnector\Exception\UserNotFoundException;
use Kayrah87\DummyJsonUserConnector\Service\UserService;

$service = UserService::create();

try {
    $user = $service-&gt;getUser(<?= $id > 0 ? $id : 1 ?>);
    echo $user-&gt;firstName;
} catch (UserNotFoundException $e) {
    echo "no user with id " . $e-&gt;getUserId();
}</pre>
</section>

<?php
page_end();
