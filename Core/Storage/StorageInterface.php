<?php
namespace Core\Storage;

/**
 * Contract for opaque binary object storage.
 *
 * Callers pass logical storage keys (for example "journeys/42/cover.bin").
 * Implementations map keys to a backend medium without exposing filesystem paths,
 * bucket names, or provider URLs to application code.
 *
 * Register a concrete implementation in INI [services] storage — for example
 * Core\Storage\FileStorage locally or a future Core\Storage\S3Storage adapter.
 * Swap backends by changing the registered class, not caller code.
 */
interface StorageInterface
{
    /**
     * Persist binary contents under a storage key.
     *
     * Overwrites an existing object with the same key.
     *
     * @param string $key Opaque relative key.
     * @param string $contents Raw bytes.
     */
    public function put(string $key, string $contents): void;

    /**
     * Read the full object contents.
     *
     * @param string $key Opaque relative key.
     * @return string Raw bytes.
     * @throws StorageException When the key is invalid or the object is missing.
     */
    public function read(string $key): string;

    /**
     * Open a read-only stream for large objects.
     *
     * The caller must fclose() the returned resource.
     *
     * @param string $key Opaque relative key.
     * @return resource
     * @throws StorageException When the key is invalid or the object is missing.
     */
    public function openReadStream(string $key);

    /**
     * Remove an object when present.
     *
     * @param string $key Opaque relative key.
     * @return bool True when an object existed and was removed, false when absent.
     */
    public function delete(string $key): bool;

    /**
     * Whether an object exists for the given key.
     *
     * @param string $key Opaque relative key.
     */
    public function exists(string $key): bool;
}
