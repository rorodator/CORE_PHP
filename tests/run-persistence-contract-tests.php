#!/usr/bin/env php
<?php
/**
 * Characterization tests for PDOAdapter / Core PDO transaction contracts.
 */

declare(strict_types=1);

use Core\Base\PDOAdapter;

require_once __DIR__ . '/support/bootstrap.php';

/**
 * @throws RuntimeException
 */
function persistence_expect_runtime_exception(callable $operation, string $message): void
{
    try {
        $operation();
    } catch (RuntimeException $error) {
        return;
    } catch (PDOException $error) {
        return;
    }
    throw new RuntimeException($message);
}

try {
    core_test_boot([
        'services' => [
            'log' => \Core\Base\Log::class,
        ],
        'log' => [
            'level' => 'ERROR',
            'file'  => sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'core-php-persistence-test.log',
        ],
    ]);

    $adapter = new PDOAdapter('sqlite::memory:');
    $adapter->connect();
    assertTrue($adapter->isConnected(), 'Adapter must connect to in-memory SQLite');

    $adapter->execute('CREATE TABLE contract_items (id INTEGER PRIMARY KEY AUTOINCREMENT, label TEXT NOT NULL)');
    $adapter->beginTransaction();
    $adapter->execute('INSERT INTO contract_items (label) VALUES (?)', ['alpha']);
    $adapter->commit();
    assertSame(1, (int)$adapter->queryValue('SELECT COUNT(*) FROM contract_items'), 'Committed row must persist');

    $adapter->beginTransaction();
    $adapter->execute('INSERT INTO contract_items (label) VALUES (?)', ['beta']);
    $adapter->rollBack();
    assertSame(1, (int)$adapter->queryValue('SELECT COUNT(*) FROM contract_items'), 'Rolled-back row must not persist');

    // Current behaviour: rollBack() outside an active transaction surfaces a driver error.
    persistence_expect_runtime_exception(
        static fn () => $adapter->rollBack(),
        'rollBack() outside a transaction must fail'
    );

    persistence_expect_runtime_exception(
        static function () use ($adapter): void {
            $adapter->execute('INSERT INTO contract_items (missing_column) VALUES (?)', ['gamma']);
        },
        'Invalid SQL must be wrapped as RuntimeException'
    );

    // Core\Base\PDO service wiring (SQLite via config, no production changes).
    $dbBootConfig = core_test_config([
        'services' => [
            'db'  => \Core\Base\PDO::class,
            'log' => \Core\Base\Log::class,
        ],
        'database' => [
            'dsn' => 'sqlite::memory:',
        ],
    ]);

    // Fresh process would be ideal; here we exercise PDOAdapter directly for transactions.
    // Smoke-test Core\Base\PDO only when a new process is available — use subprocess.
    $worker = __DIR__ . '/bin/persistence-smoke-worker.php';
    $command = [PHP_BINARY, $worker];
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptors, $pipes, dirname(__DIR__));
    if (!is_resource($process)) {
        throw new RuntimeException('Failed to start persistence smoke worker.');
    }
    fclose($pipes[0]);
    $stdout = trim((string)stream_get_contents($pipes[1]));
    $stderr = trim((string)stream_get_contents($pipes[2]));
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    assertSame(0, $exitCode, 'Persistence smoke worker must succeed: ' . $stderr);
    assertSame('OK', $stdout, 'Persistence smoke worker must report OK');

    fwrite(STDOUT, "CORE_PHP persistence contract tests passed.\n");
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, 'Persistence contract test error: ' . $error->getMessage() . "\n");
    exit(1);
}
