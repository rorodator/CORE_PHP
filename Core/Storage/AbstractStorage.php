<?php
namespace Core\Storage;

/**
 * Shared helpers for storage backends (key validation, project-root resolution).
 */
abstract class AbstractStorage implements StorageInterface
{
    /**
     * Normalize and validate an opaque storage key.
     *
     * Keys are relative, slash-separated paths without parent segments.
     *
     * @throws StorageException
     */
    protected function normalizeKey(string $key): string
    {
        $key = trim(str_replace('\\', '/', $key));
        if ($key === '') {
            throw new StorageException('Storage key must not be empty.');
        }
        if ($key[0] === '/') {
            throw new StorageException('Storage key must be relative.');
        }

        $segments = explode('/', $key);
        $normalized = [];
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') {
                throw new StorageException('Storage key contains invalid segments.');
            }
            if ($segment === '..') {
                throw new StorageException('Storage key must not contain parent segments.');
            }
            $normalized[] = $segment;
        }

        return implode('/', $normalized);
    }

    /**
     * Resolve a configured path relative to the application project root.
     */
    protected function resolveProjectPath(string $path): string
    {
        if ($this->isAbsolutePath($path)) {
            return $path;
        }

        $projectRoot = $this->findProjectRoot();
        return rtrim($projectRoot, '/\\')
            . DIRECTORY_SEPARATOR
            . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }

    /**
     * Ensure a directory exists and is writable when possible.
     */
    protected function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            if (!@mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new StorageException('Unable to create storage directory.');
            }
        }
        if (is_dir($directory) && !is_writable($directory)) {
            @chmod($directory, 0775);
        }
    }

    /**
     * Verify that a candidate path stays inside the configured storage root.
     *
     * @throws StorageException
     */
    protected function assertPathInsideRoot(string $candidatePath, string $rootDirectory): void
    {
        $root = $this->normalizeFilesystemPath($rootDirectory);
        $candidate = $this->normalizeFilesystemPath($candidatePath);
        $rootPrefix = $root . DIRECTORY_SEPARATOR;

        if ($candidate === $root || strpos($candidate, $rootPrefix) === 0) {
            return;
        }

        throw new StorageException('Storage key resolves outside the configured root.');
    }

    protected function isAbsolutePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }
        if ($path[0] === '/' || $path[0] === '\\') {
            return true;
        }
        return (bool)preg_match('/^[A-Za-z]:[\/\\\\]/', $path);
    }

    protected function normalizeFilesystemPath(string $path): string
    {
        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }

    /**
     * Locate the consuming application root from the current execution context.
     */
    protected function findProjectRoot(): string
    {
        $startDir = null;
        if (!empty($_SERVER['SCRIPT_FILENAME']) && is_string($_SERVER['SCRIPT_FILENAME'])) {
            $startDir = dirname($_SERVER['SCRIPT_FILENAME']);
        }
        if (!$startDir || !is_dir($startDir)) {
            $startDir = getcwd() ?: null;
        }
        if (!$startDir) {
            return dirname(__DIR__, 2);
        }

        $dir = $startDir;
        for ($i = 0; $i < 8; $i++) {
            $configDir = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . 'PHP' . DIRECTORY_SEPARATOR . 'CONFIG';
            if (is_dir($configDir)) {
                foreach (glob($configDir . DIRECTORY_SEPARATOR . '*.ini') ?: [] as $iniFile) {
                    if (is_file($iniFile)) {
                        return rtrim($dir, '/\\');
                    }
                }
            }
            $parent = dirname($dir);
            if ($parent === $dir) {
                break;
            }
            $dir = $parent;
        }

        return dirname(__DIR__, 2);
    }
}
