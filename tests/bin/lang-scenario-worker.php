#!/usr/bin/env php
<?php
/**
 * CLI worker that executes one Lang REST scenario.
 */

declare(strict_types=1);

use Core\Tests\Support;

require_once __DIR__ . '/../support/lang-scenario-runner.php';

$scenario = $argv[1] ?? '';
if ($scenario === '') {
    fwrite(STDERR, "Missing Lang scenario name.\n");
    exit(2);
}

Support\lang_run_scenario($scenario);
