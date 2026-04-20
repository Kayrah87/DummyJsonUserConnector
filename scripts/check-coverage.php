<?php

declare(strict_types=1);

/**
 * Line-coverage gate.
 *
 * Parses the aggregated `<project><metrics>` element of a PHPUnit clover report
 * and exits non-zero if `coveredstatements / statements` is below the given
 * percentage threshold.
 *
 * Usage:
 *   php scripts/check-coverage.php <clover-file> <threshold>
 *
 * Example:
 *   php scripts/check-coverage.php build/logs/clover.xml 90
 *
 * Exit codes:
 *   0 — coverage meets or exceeds the threshold
 *   1 — coverage is below the threshold (the gate failed)
 *   2 — usage error, missing file, or malformed clover XML
 */

function fail(string $message, int $code = 2): never
{
    \fwrite(\STDERR, $message . \PHP_EOL);
    exit($code);
}

if (!isset($argv[1], $argv[2])) {
    fail('Usage: php scripts/check-coverage.php <clover.xml> <threshold>');
}

$cloverPath = (string) $argv[1];
$threshold = (float) $argv[2];

if (!\is_file($cloverPath)) {
    fail("Clover report not found: {$cloverPath}");
}

if ($threshold < 0.0 || $threshold > 100.0) {
    fail("Threshold must be between 0 and 100; got {$threshold}");
}

$xml = \simplexml_load_file($cloverPath);
if ($xml === false || !isset($xml->project->metrics)) {
    fail("Failed to parse clover XML or no <project><metrics> in: {$cloverPath}");
}

$metrics = $xml->project->metrics;
$statements = (int) $metrics['statements'];
$covered = (int) $metrics['coveredstatements'];

if ($statements === 0) {
    fail('Clover report contains zero executable statements');
}

$percentage = ($covered / $statements) * 100;
$formatted = \number_format($percentage, 2);

if ($percentage + 0.0001 < $threshold) {
    fail(
        "Coverage {$formatted}% is below threshold {$threshold}% ({$covered}/{$statements} statements)",
        1,
    );
}

echo "Coverage {$formatted}% meets threshold {$threshold}% ({$covered}/{$statements} statements)" . \PHP_EOL;
