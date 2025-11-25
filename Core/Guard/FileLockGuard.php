<?php
namespace Core\Guard;

/**
 * File-based implementation of named locks using flock().
 * Suitable for single-server deployments.
 */
class FileLockGuard implements LockGuardInterface
{
    /**
     * Directory where lock files are stored.
     * @var string
     */
    private $locksDir;

    /**
     * Map of lock name to open file handles.
     * @var array<string, resource>
     */
    private $handles = [];

    public function __construct()
    {
        // Resolve locks directory from config, with a sensible default
        $cfg = \core()->getConfigSection('guard');
        $dir = isset($cfg['dir']) ? (string)$cfg['dir'] : 'PHP/CACHE/locks';
        $this->locksDir = $this->resolvePath($dir);
        $this->ensureDir($this->locksDir);
    }

    /**
     * @inheritDoc
     */
    public function lock(string $name, int $ttlSeconds = 0): bool
    {
        // Re-entrant: if we already hold this lock, consider it acquired
        if (isset($this->handles[$name]) && is_resource($this->handles[$name])) {
            return true;
        }
        $path = $this->pathFor($name);
        $fp = @fopen($path, 'c');
        if ($fp === false) {
            return false;
        }
        // Try to acquire exclusive lock, with small backoff.
        $acquired = false;
        $attempts = 0;
        if ($ttlSeconds > 0) {
            $deadline = time() + $ttlSeconds;
            do {
                $attempts++;
                $acquired = @flock($fp, LOCK_EX | LOCK_NB);
                if ($acquired) break;
                // brief sleep before retry
                usleep(50_000); // 50ms
            } while (time() < $deadline);
        } else {
            // short, fixed retries to smooth transient contention
            for ($i = 0; $i < 10; $i++) {
                $attempts++;
                $acquired = @flock($fp, LOCK_EX | LOCK_NB);
                if ($acquired) break;
                usleep(20_000); // 20ms
            }
        }
        if (!$acquired) {
            @fclose($fp);
            return false;
        }
        // Write some metadata (optional)
        $meta = json_encode([
            'name' => $name,
            'pid' => getmypid(),
            'time' => time(),
            'ttl'  => $ttlSeconds
        ]);
        @ftruncate($fp, 0);
        @fwrite($fp, $meta);
        @fflush($fp);
        // Keep handle to release later
        $this->handles[$name] = $fp;
        // Permissions, best effort
        @chmod($path, 0777);
        return true;
    }

    /**
     * @inheritDoc
     */
    public function unlock(string $name): void
    {
        if (!isset($this->handles[$name])) {
            // Try best-effort cleanup if stray file exists
            $path = $this->pathFor($name);
            // Do not unlink blindly; lock ownership is unknown
            return;
        }
        $fp = $this->handles[$name];
        @flock($fp, LOCK_UN);
        @fclose($fp);
        unset($this->handles[$name]);
        // Keep the file to avoid races; it's harmless
    }

    /**
     * @inheritDoc
     */
    public function isLocked(string $name): bool
    {
        $path = $this->pathFor($name);
        $fp = @fopen($path, 'c');
        if ($fp === false) {
            return false;
        }
        $acquired = @flock($fp, LOCK_EX | LOCK_NB);
        if ($acquired) {
            // Immediately release; not locked by others
            @flock($fp, LOCK_UN);
            @fclose($fp);
            return false;
        }
        @fclose($fp);
        return true;
    }

    private function resolvePath(string $path): string
    {
        // If absolute path, return as is
        if (strlen($path) > 0 && ($path[0] === '/' || preg_match('/^[A-Za-z]:[\/\\\\]/', $path))) {
            return $path;
        }
        // Try to resolve project root by locating the MyManager.ini from the current execution context
        $projectRoot = $this->findProjectRoot();
        return rtrim($projectRoot, '/\\') . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }

    /**
     * Attempt to locate the application project root by searching upwards
     * for PHP/CONFIG/MyManager.ini from the current script directory.
     *
     * @return string Absolute path to the project root
     */
    private function findProjectRoot(): string
    {
        // Prefer the directory of the current script (web or CLI)
        $startDir = null;
        if (!empty($_SERVER['SCRIPT_FILENAME']) && is_string($_SERVER['SCRIPT_FILENAME'])) {
            $startDir = dirname($_SERVER['SCRIPT_FILENAME']);
        }
        if (!$startDir || !is_dir($startDir)) {
            $startDir = getcwd() ?: null;
        }
        // Fallback to repository-relative (CORE) root
        if (!$startDir) {
            return dirname(__DIR__, 3);
        }
        $dir = $startDir;
        for ($i = 0; $i < 8; $i++) {
            $candidate = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . 'PHP' . DIRECTORY_SEPARATOR . 'CONFIG' . DIRECTORY_SEPARATOR . 'MyManager.ini';
            if (file_exists($candidate)) {
                return rtrim($dir, '/\\');
            }
            $parent = dirname($dir);
            if ($parent === $dir) {
                break;
            }
            $dir = $parent;
        }
        // As a last resort, use the CORE repository root (previous behavior)
        return dirname(__DIR__, 3);
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        if (is_dir($dir) && !is_writable($dir)) {
            @chmod($dir, 0777);
        }
    }

    private function pathFor(string $name): string
    {
        $safe = sha1($name);
        return rtrim($this->locksDir, '/\\') . DIRECTORY_SEPARATOR . $safe . '.lock';
    }
}

?>


