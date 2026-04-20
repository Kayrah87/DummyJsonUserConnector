<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/_layout.php';

use Kayrah87\DummyJsonUserConnector\Exception\DummyJsonException;
use Kayrah87\DummyJsonUserConnector\Service\UserService;

$service = UserService::create();

$limit = (int) ($_POST['limit'] ?? $_GET['limit'] ?? 10);
$skip = (int) ($_POST['skip'] ?? $_GET['skip'] ?? 0);
$submitted = $_SERVER['REQUEST_METHOD'] === 'POST';
$result = null;
$error = null;

if ($submitted) {
    try {
        $result = $service->listUsers(limit: $limit, skip: $skip);
    } catch (DummyJsonException $e) {
        $error = $e;
    }
}

page_start('List Users');
?>
<section>
    <h2><code>listUsers(int $limit = 30, int $skip = 0): UserPage</code></h2>
    <p>
        Fetches a paginated page via
        <code>GET /users?limit={limit}&amp;skip={skip}</code>. Defaults match
        the DummyJSON API (<code>limit=30</code>, <code>skip=0</code>). Pass
        <code>limit=0</code> to request every user at once — DummyJSON-specific
        behaviour preserved by the connector.
    </p>
    <p>
        Returns a <code>UserPage</code> DTO — a <code>final readonly</code> value
        object with <code>items</code> (<code>list&lt;User&gt;</code>),
        <code>total</code>, <code>skip</code>, and <code>limit</code>. It
        implements <code>Countable</code> and <code>IteratorAggregate</code>,
        so <code>count($page)</code> returns the page size and
        <code>foreach ($page as $user)</code> walks the items directly.
    </p>
    <p>
        <strong>Throws:</strong> <code>TransportException</code>,
        <code>ApiException</code>, <code>InvalidResponseException</code>.
    </p>
</section>

<section>
    <h2>Try it</h2>
    <form method="post">
        <label>
            Limit
            <input type="number" name="limit" value="<?= htmlspecialchars((string) $limit) ?>" min="0">
        </label>
        <label>
            Skip
            <input type="number" name="skip" value="<?= htmlspecialchars((string) $skip) ?>" min="0">
        </label>
        <button type="submit">Fetch page</button>
    </form>
    <p class="hint">
        Try <code>limit: 5, skip: 0</code> for the first 5,
        <code>limit: 5, skip: 5</code> for the next 5, or
        <code>limit: 0</code> to pull every user (DummyJSON quirk).
    </p>
</section>

<?php if ($result !== null): ?>
<section>
    <h2>Pagination envelope</h2>
    <pre>total (whole collection): <?= $result->total ?>
skip  (offset requested):  <?= $result->skip ?>
limit (max per page):      <?= $result->limit ?>
count (items on this page): <?= count($result) ?></pre>
</section>

<section>
    <h2>Users on this page</h2>
    <ul>
        <?php foreach ($result as $u): ?>
            <li>
                <code>#<?= $u->id ?></code>
                <?= htmlspecialchars($u->firstName) ?>
                <?= htmlspecialchars($u->lastName) ?>
                — <code><?= htmlspecialchars($u->email) ?></code>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php if (count($result) === 0): ?>
    <p class="hint">Empty page — the <code>skip</code> offset is past the collection's <code>total</code>.</p>
    <?php endif; ?>
</section>

<section>
    <h2>Full JSON response</h2>
    <pre><?= htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)) ?></pre>
    <p class="hint">
        Note the JSON envelope uses the key <code>users</code> (matching the
        API's wire shape), even though the PHP-side property is named
        <code>items</code>. Round-trips cleanly against DummyJSON.
    </p>
</section>
<?php endif; ?>

<?php if ($error !== null): ?>
<section class="error">
    <h2>Exception caught</h2>
    <pre>Class:   <?= htmlspecialchars($error::class) ?>

Message: <?= htmlspecialchars($error->getMessage()) ?></pre>
</section>
<?php endif; ?>

<section>
    <h2>Consumer code</h2>
    <pre>use Kayrah87\DummyJsonUserConnector\Service\UserService;

$service = UserService::create();
$page = $service-&gt;listUsers(limit: <?= $limit ?>, skip: <?= $skip ?>);

printf(
    "showing %d of %d (skip %d)\n",
    count($page),
    $page-&gt;total,
    $page-&gt;skip,
);

foreach ($page as $user) {
    echo $user-&gt;firstName . "\n";
}</pre>
</section>

<?php
page_end();
