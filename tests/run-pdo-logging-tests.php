#!/usr/bin/env php
<?php
/**
 * Standalone DB diagnostic redaction checks for CORE_PHP.
 */

declare(strict_types=1);

$coreRoot = dirname(__DIR__);

spl_autoload_register(static function (string $class) use ($coreRoot): void {
    $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $class) . '.php';
    $file = $coreRoot . DIRECTORY_SEPARATOR . $relativePath;
    if (is_file($file)) {
        require_once $file;
    }
});

require_once $coreRoot . '/Core/core_header.php';

use Core\Base\Core;
use Core\Base\Log;
use Core\Base\PDOAdapter;

/**
 * @throws RuntimeException
 */
function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/**
 * @throws RuntimeException
 */
function assertNotContains(string $needle, string $haystack, string $message): void
{
    if ($needle !== '' && strpos($haystack, $needle) !== false) {
        throw new RuntimeException($message);
    }
}

/**
 * @throws RuntimeException
 */
function assertContains(string $needle, string $haystack, string $message): void
{
    if ($needle === '' || strpos($haystack, $needle) === false) {
        throw new RuntimeException($message);
    }
}

$sentinel = 'VERY_SECRET_TEST_VALUE_123';
$logFile = sys_get_temp_dir()
    . DIRECTORY_SEPARATOR
    . 'core-php-pdo-log-test-'
    . bin2hex(random_bytes(8))
    . '.log';

$config = [
    'services' => [
        'log' => Log::class,
    ],
    'log' => [
        'file' => $logFile,
        'level' => 'ERROR',
    ],
];

Core::bootFromConfig($config);

$adapter = new PDOAdapter('sqlite::memory:');
$adapter->connect();

try {
    $adapter->execute(
        'INSERT INTO missing_table (secret_col) VALUES (:secret)',
        ['secret' => $sentinel]
    );
} catch (RuntimeException $error) {
    // Expected wrapper from PDOAdapter.
}

try {
    $adapter->query(
        'SELECT * FROM users WHERE email = :email',
        ['email' => $sentinel]
    );
} catch (RuntimeException $error) {
    // Expected wrapper from PDOAdapter.
}

try {
    $adapter->execute("SELECT * FROM users WHERE token = '" . $sentinel . "'");
} catch (RuntimeException $error) {
    // Expected wrapper from PDOAdapter.
}

$logContents = is_file($logFile) ? (string)file_get_contents($logFile) : '';

$connectLogFile = sys_get_temp_dir()
    . DIRECTORY_SEPARATOR
    . 'core-php-pdo-connect-log-test-'
    . bin2hex(random_bytes(8))
    . '.log';

$configConnect = [
    'services' => [
        'log' => Log::class,
    ],
    'log' => [
        'file' => $connectLogFile,
        'level' => 'ERROR',
    ],
];

// Boot a fresh Core instance for the connect failure scenario.
$reflection = new ReflectionClass(Core::class);
$instanceProperty = $reflection->getProperty('instance');
$instanceProperty->setAccessible(true);
$instanceProperty->setValue(null, null);

Core::bootFromConfig($configConnect);

$connectAdapter = new PDOAdapter(
    'mysql:host=127.0.0.1;port=59999;dbname=sensitive_db',
    'db_user',
    $sentinel
);

try {
    $connectAdapter->connect();
} catch (RuntimeException $error) {
    // Expected wrapper from PDOAdapter.
}

$connectLogContents = is_file($connectLogFile) ? (string)file_get_contents($connectLogFile) : '';

assertTrue($logContents !== '', 'SQL failure diagnostics must be written to the log file.');
assertTrue($connectLogContents !== '', 'Connect failure diagnostics must be written to the log file.');

foreach ([$logContents, $connectLogContents] as $contents) {
    assertNotContains($sentinel, $contents, 'Sensitive sentinel must not appear in DB diagnostics.');
    assertNotContains('PARAMS=', $contents, 'Bound parameter dumps must not appear in DB diagnostics.');
    assertNotContains('SQL=', $contents, 'SQL text must not appear in DB diagnostics.');
    assertNotContains('DSN=', $contents, 'DSN details must not appear in DB diagnostics.');
    assertNotContains('sensitive_db', $contents, 'Database name must not appear in DB diagnostics.');
    assertNotContains('db_user', $contents, 'Database username must not appear in DB diagnostics.');
}

assertContains('DB execute failed', $logContents, 'Execute failures must remain identifiable in logs.');
assertContains('DB query failed', $logContents, 'Query failures must remain identifiable in logs.');
assertContains('exception=PDOException', $logContents, 'Exception class must remain in SQL failure logs.');
assertContains('DB connect failed', $connectLogContents, 'Connect failures must remain identifiable in logs.');
assertContains('driver=mysql', $connectLogContents, 'Connect logs may include the DSN driver prefix only.');

@unlink($logFile);
@unlink($connectLogFile);

fwrite(STDOUT, "CORE_PHP PDO logging tests passed.\n");
