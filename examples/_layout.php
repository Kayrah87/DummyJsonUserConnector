<?php

declare(strict_types=1);

/**
 * Shared HTML/CSS/nav shell for the examples. Each demo page calls
 * page_start(title) at the top and page_end() at the bottom.
 */

function page_start(string $title): void
{
    $navItems = [
        'index.php' => 'Overview',
        'get-user.php' => 'Get User',
        'list-users.php' => 'List Users',
        'create-user.php' => 'Create User',
    ];

    $current = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));

    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title) ?> — DummyJSON Connector Demo</title>
    <style>
        :root {
            --fg: #1a1a1a;
            --bg: #fafafa;
            --accent: #0070f3;
            --border: #e5e5e5;
            --code-bg: #f4f4f5;
            --error-fg: #b91c1c;
            --error-bg: #fef2f2;
            --hint: #6b7280;
        }
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            margin: 0;
            background: var(--bg);
            color: var(--fg);
            line-height: 1.55;
        }
        header { background: #fff; border-bottom: 1px solid var(--border); padding: 1rem 2rem; }
        header h1 { margin: 0 0 .5rem; font-size: 1.25rem; }
        nav a {
            display: inline-block;
            padding: .35rem .75rem;
            margin-right: .25rem;
            color: var(--fg);
            text-decoration: none;
            border-radius: 4px;
            font-size: .9rem;
        }
        nav a:hover { background: var(--code-bg); }
        nav a.active { background: var(--accent); color: #fff; }
        main { max-width: 880px; margin: 0 auto; padding: 2rem 1.5rem; }
        section {
            background: #fff;
            padding: 1.25rem 1.5rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            margin-bottom: 1rem;
        }
        section.error { background: var(--error-bg); border-color: #fecaca; }
        section.error h2 { color: var(--error-fg); }
        h2 { margin-top: 0; font-size: 1.05rem; }
        code {
            background: var(--code-bg);
            padding: .1rem .35rem;
            border-radius: 3px;
            font-family: ui-monospace, "SF Mono", Menlo, Consolas, monospace;
            font-size: .9em;
        }
        pre {
            background: var(--code-bg);
            padding: .85rem 1rem;
            border-radius: 4px;
            overflow-x: auto;
            font-family: ui-monospace, "SF Mono", Menlo, Consolas, monospace;
            font-size: .85rem;
            margin: .5rem 0;
        }
        pre code { background: transparent; padding: 0; font-size: inherit; }
        form { display: flex; flex-wrap: wrap; gap: .75rem; align-items: flex-end; }
        label { display: flex; flex-direction: column; font-size: .8rem; color: var(--hint); gap: .25rem; }
        input {
            padding: .45rem .65rem;
            border: 1px solid var(--border);
            border-radius: 4px;
            font-size: .95rem;
            min-width: 150px;
            color: var(--fg);
            background: #fff;
            font-family: inherit;
        }
        button {
            padding: .5rem 1.1rem;
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: .9rem;
            font-family: inherit;
        }
        button:hover { filter: brightness(1.08); }
        dl dt { margin-top: 1rem; font-weight: 600; }
        dl dt:first-child { margin-top: 0; }
        dl dd { margin-left: 0; color: var(--hint); font-size: .9rem; }
        .hint { color: var(--hint); font-size: .88rem; font-style: italic; margin: .5rem 0 0; }
        footer {
            text-align: center;
            padding: 1.5rem 1rem 2rem;
            color: var(--hint);
            font-size: .85rem;
        }
        footer a { color: var(--accent); text-decoration: none; }
        ul { margin: .5rem 0; padding-left: 1.25rem; }
        li { margin: .15rem 0; }
    </style>
</head>
<body>
    <header>
        <h1>DummyJSON User Connector — Live Demo</h1>
        <nav>
            <?php foreach ($navItems as $href => $label): ?>
                <a href="<?= htmlspecialchars($href) ?>"
                   class="<?= $current === $href ? 'active' : '' ?>"><?= htmlspecialchars($label) ?></a>
            <?php endforeach; ?>
        </nav>
    </header>
    <main>
<?php
}

function page_end(): void
{
    ?>
    </main>
    <footer>
        Package: <code>kayrah87/dummyjson-user-connector</code> —
        <a href="https://github.com/Kayrah87/DummyJsonUserConnector">source on GitHub</a>
    </footer>
</body>
</html>
<?php
}
