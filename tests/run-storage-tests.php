#!/usr/bin/env php
<?php
/**
 * Standalone storage contract checks for CORE_PHP.
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
use Core\Storage\FileStorage;
use Core\Storage\StorageException;

/**
 * @throws RuntimeException
 */
function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$tempRoot = sys_get_temp_dir()
    . DIRECTORY_SEPARATOR
    . 'core-php-storage-test-'
    . bin2hex(random_bytes(8));

$config = [
    'services' => [
        'storage' => FileStorage::class,
    ],
    'storage' => [
        'root' => $tempRoot,
    ],
];

Core::bootFromConfig($config);
$storage = core()->storage;
assertTrue($storage instanceof FileStorage, 'core()->storage must resolve FileStorage.');

$key = 'verify/objects/sample.bin';
$contents = 'core-storage-' . random_int(1000, 9999);
$storage->put($key, $contents);
assertTrue($storage->exists($key), 'put() must make the object readable via exists().');
assertTrue($storage->read($key) === $contents, 'read() must return stored bytes.');

$stream = $storage->openReadStream($key);
assertTrue(is_resource($stream), 'openReadStream() must return a resource.');
$streamContents = stream_get_contents($stream);
fclose($stream);
assertTrue($streamContents === $contents, 'openReadStream() must expose stored bytes.');

assertTrue($storage->delete($key), 'delete() must remove an existing object.');
assertTrue(!$storage->exists($key), 'exists() must be false after delete().');
assertTrue(!$storage->delete($key), 'delete() must return false for missing objects.');

$invalidKeys = ['', '/absolute/key', '../escape', 'nested/../escape'];
foreach ($invalidKeys as $invalidKey) {
    $rejected = false;
    try {
        $storage->put($invalidKey, 'x');
    } catch (StorageException $error) {
        $rejected = true;
    }
    assertTrue($rejected, "Invalid storage key must be rejected: {$invalidKey}");
}

@unlink($tempRoot . DIRECTORY_SEPARATOR . 'verify' . DIRECTORY_SEPARATOR . 'objects' . DIRECTORY_SEPARATOR . 'sample.bin');
@rmdir($tempRoot . DIRECTORY_SEPARATOR . 'verify' . DIRECTORY_SEPARATOR . 'objects');
@rmdir($tempRoot . DIRECTORY_SEPARATOR . 'verify');
@rmdir($tempRoot);

fwrite(STDOUT, "CORE_PHP storage tests passed.\n");
