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

/**
 * @param callable(string): void $operation
 * @throws RuntimeException
 */
function assertInvalidKeyRejected(callable $operation, string $invalidKey, string $method): void
{
    try {
        $operation($invalidKey);
    } catch (StorageException $error) {
        return;
    }
    throw new RuntimeException("Invalid storage key must be rejected by {$method}: {$invalidKey}");
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

$streamKey = 'verify/objects/streamed.bin';
$streamPayload = str_repeat('stream-chunk-', 1000);
$source = fopen('php://memory', 'r+');
fwrite($source, $streamPayload);
rewind($source);
$storage->putStream($streamKey, $source);
fclose($source);
assertTrue($storage->read($streamKey) === $streamPayload, 'putStream() must persist streamed bytes.');

assertTrue($storage->delete($key), 'delete() must remove an existing object.');
assertTrue(!$storage->exists($key), 'exists() must be false after delete().');
assertTrue(!$storage->delete($key), 'delete() must return false for missing objects.');

$invalidKeys = ['', '/absolute/key', '../escape', 'nested/../escape'];
foreach ($invalidKeys as $invalidKey) {
    assertInvalidKeyRejected(
        static fn (string $key) => $storage->put($key, 'x'),
        $invalidKey,
        'put()'
    );
    assertInvalidKeyRejected(
        static function (string $key) use ($storage): void {
            $payload = fopen('php://memory', 'r+');
            fwrite($payload, 'x');
            rewind($payload);
            try {
                $storage->putStream($key, $payload);
            } finally {
                fclose($payload);
            }
        },
        $invalidKey,
        'putStream()'
    );
    assertInvalidKeyRejected(
        static fn (string $key) => $storage->read($key),
        $invalidKey,
        'read()'
    );
    assertInvalidKeyRejected(
        static fn (string $key) => $storage->openReadStream($key),
        $invalidKey,
        'openReadStream()'
    );
    assertInvalidKeyRejected(
        static fn (string $key) => $storage->exists($key),
        $invalidKey,
        'exists()'
    );
    assertInvalidKeyRejected(
        static fn (string $key) => $storage->delete($key),
        $invalidKey,
        'delete()'
    );
}

$lockedKey = 'verify/objects/locked.bin';
$storage->put($lockedKey, 'locked');
$lockedDirectory = $tempRoot . DIRECTORY_SEPARATOR . 'verify' . DIRECTORY_SEPARATOR . 'objects';
$previousMode = fileperms($lockedDirectory);
chmod($lockedDirectory, 0555);
try {
    $deleteFailed = false;
    try {
        $storage->delete($lockedKey);
    } catch (StorageException $error) {
        $deleteFailed = true;
    }
    assertTrue($deleteFailed, 'delete() must throw when backend removal fails.');
} finally {
    chmod($lockedDirectory, $previousMode !== false ? ($previousMode & 0777) : 0755);
}

@unlink($tempRoot . DIRECTORY_SEPARATOR . 'verify' . DIRECTORY_SEPARATOR . 'objects' . DIRECTORY_SEPARATOR . 'streamed.bin');
@unlink($tempRoot . DIRECTORY_SEPARATOR . 'verify' . DIRECTORY_SEPARATOR . 'objects' . DIRECTORY_SEPARATOR . 'locked.bin');
@rmdir($lockedDirectory);
@rmdir($tempRoot . DIRECTORY_SEPARATOR . 'verify');
@rmdir($tempRoot);

fwrite(STDOUT, "CORE_PHP storage tests passed.\n");
