#!/usr/bin/env php
<?php
/**
 * Smoke-test Core\Base\PDO transaction wiring in an isolated process.
 */

declare(strict_types=1);

require_once __DIR__ . '/../support/bootstrap.php';

try {
    core_test_boot([
        'services' => [
            'db'  => \Core\Base\PDO::class,
            'log' => \Core\Base\Log::class,
        ],
        'database' => [
            'dsn' => 'sqlite::memory:',
        ],
    ]);

    $db = core()->db;
    $db->execute('CREATE TABLE pdo_smoke (id INTEGER PRIMARY KEY AUTOINCREMENT, note TEXT NOT NULL)');
    $db->beginTransaction();
    $db->execute('INSERT INTO pdo_smoke (note) VALUES (?)', ['via-core-pdo']);
    $db->commit();
    $count = (int)$db->queryValue('SELECT COUNT(*) FROM pdo_smoke');
    if ($count !== 1) {
        throw new RuntimeException('Core PDO smoke insert failed.');
    }

    fwrite(STDOUT, "OK\n");
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
