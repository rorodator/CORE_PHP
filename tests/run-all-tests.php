#!/usr/bin/env php
<?php
/**
 * Run all CORE_PHP standalone test runners.
 */

declare(strict_types=1);

$runners = [
    'run-storage-tests.php',
    'run-core-contract-tests.php',
    'run-persistence-contract-tests.php',
    'run-rest-contract-tests.php',
    'run-lang-contract-tests.php',
    'run-rich-text-html-tests.php',
];

$phpBinary = PHP_BINARY;
if (!is_file($phpBinary)) {
    foreach (['php8.3', 'php8.2', 'php'] as $candidate) {
        $resolved = trim((string)shell_exec('command -v ' . escapeshellarg($candidate)));
        if ($resolved !== '' && is_file($resolved)) {
            $phpBinary = $resolved;
            break;
        }
    }
}

$root = dirname(__DIR__);
$failures = [];

foreach ($runners as $runner) {
    $path = $root . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . $runner;
    if (!is_file($path)) {
        $failures[] = "Missing runner: {$runner}";
        continue;
    }

    $command = escapeshellarg($phpBinary) . ' ' . escapeshellarg($path);
    passthru($command, $exitCode);
    if ($exitCode !== 0) {
        $failures[] = "{$runner} failed with exit code {$exitCode}";
    }
}

if ($failures !== []) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, "All CORE_PHP tests passed.\n");
exit(0);
