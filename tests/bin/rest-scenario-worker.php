#!/usr/bin/env php
<?php
/**
 * CLI worker that executes one RestService scenario (may call exit()).
 */

declare(strict_types=1);

use Core\Tests\Support;

require_once __DIR__ . '/../support/rest-scenario-runner.php';

$scenario = $argv[1] ?? '';
if ($scenario === '') {
    fwrite(STDERR, "Missing scenario name.\n");
    exit(2);
}

Support\rest_run_scenario($scenario);
