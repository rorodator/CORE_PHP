<?php
namespace Core\Storage;

/**
 * Local filesystem storage mapped from opaque keys to files under [storage] root.
 */
class FileStorage extends AbstractStorage
{
    /** @var string Absolute path to the storage root directory. */
    private string $rootDirectory;

    public function __construct()
    {
        $config = function_exists('core') ? core()->getConfigSection('storage') : [];
        $root = isset($config['root']) ? (string)$config['root'] : 'PHP/CACHE/storage';
        $resolvedRoot = $this->resolveProjectPath($root);
        $this->ensureDirectory($resolvedRoot);
        $canonicalRoot = realpath($resolvedRoot);
        $this->rootDirectory = $canonicalRoot !== false
            ? $canonicalRoot
            : $this->normalizeFilesystemPath($resolvedRoot);
    }

    /**
     * @inheritDoc
     */
    public function put(string $key, string $contents): void
    {
        $path = $this->absolutePathForKey($key);
        $this->ensureDirectory(dirname($path));
        if (@file_put_contents($path, $contents, LOCK_EX) === false) {
            throw new StorageException('Unable to write storage object.');
        }
    }

    /**
     * @inheritDoc
     */
    public function putStream(string $key, $stream): void
    {
        if (!is_resource($stream)) {
            throw new StorageException('Storage stream must be a readable resource.');
        }

        $path = $this->absolutePathForKey($key);
        $this->ensureDirectory(dirname($path));
        $destination = @fopen($path, 'wb');
        if ($destination === false) {
            throw new StorageException('Unable to write storage object.');
        }

        $copied = @stream_copy_to_stream($stream, $destination);
        @fclose($destination);
        if ($copied === false) {
            @unlink($path);
            throw new StorageException('Unable to write storage object.');
        }
    }

    /**
     * @inheritDoc
     */
    public function read(string $key): string
    {
        $path = $this->absolutePathForKey($key, true);
        $contents = @file_get_contents($path);
        if ($contents === false) {
            throw new StorageException('Storage object is unreadable.');
        }
        return $contents;
    }

    /**
     * @inheritDoc
     */
    public function openReadStream(string $key)
    {
        $path = $this->absolutePathForKey($key, true);
        $stream = @fopen($path, 'rb');
        if ($stream === false) {
            throw new StorageException('Storage object is unreadable.');
        }
        return $stream;
    }

    /**
     * @inheritDoc
     */
    public function delete(string $key): bool
    {
        $path = $this->absolutePathForKey($key);
        if (!is_file($path)) {
            return false;
        }
        if (!@unlink($path)) {
            throw new StorageException('Unable to delete storage object.');
        }
        return true;
    }

    /**
     * @inheritDoc
     */
    public function exists(string $key): bool
    {
        $path = $this->absolutePathForKey($key);
        return is_file($path);
    }

    /**
     * @param string $key
     * @param bool $mustExist When true, missing objects raise StorageException.
     * @return string Absolute filesystem path for the key.
     */
    private function absolutePathForKey(string $key, bool $mustExist = false): string
    {
        $normalizedKey = $this->normalizeKey($key);
        $path = $this->rootDirectory
            . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $normalizedKey);
        $this->assertPathInsideRoot($path, $this->rootDirectory);

        if ($mustExist && !is_file($path)) {
            throw new StorageException('Storage object was not found.');
        }

        return $path;
    }
}
