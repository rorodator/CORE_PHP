<?php
namespace Core\Guard;

/**
 * Interface for a named lock guard that can be swapped by configuration.
 */
interface LockGuardInterface
{
    /**
     * Acquire a named lock.
     *
     * @param string $name Lock name (logical name)
     * @param int $ttlSeconds Optional TTL in seconds (may be ignored by some implementations)
     * @return bool True if the lock has been acquired, false otherwise
     */
    public function lock(string $name, int $ttlSeconds = 0): bool;

    /**
     * Release a previously acquired named lock.
     *
     * @param string $name Lock name
     * @return void
     */
    public function unlock(string $name): void;

    /**
     * Check if a lock appears to be held.
     *
     * @param string $name Lock name
     * @return bool True if locked, false if free
     */
    public function isLocked(string $name): bool;
}

?>


