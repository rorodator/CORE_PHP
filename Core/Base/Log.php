<?php
namespace Core\Base;

/**
 * Simple file logger used by core()->log
 * Levels: ERROR, INFO, DEBUG, IO
 */
class Log {
    private $level;
    private $filePath;

    public function __construct() {
        $conf = function_exists('core') ? core()->getConfigSection('log') : [];
        $this->level = strtoupper($conf['level'] ?? 'INFO');
        $configuredPath = $conf['file'] ?? 'LOG/my_manager.log';
        // Normalize path: prefer LOG/ if exists
        if (!self::isAbsolutePath($configuredPath)) {
            // Use relative path as given; ensure directory exists on write
            $this->filePath = $configuredPath;
        } else {
            $this->filePath = $configuredPath;
        }
    }

    public function error(string $message): void { $this->write('ERROR', $message); }
    public function info(string $message): void { $this->write('INFO', $message); }
    public function debug(string $message): void { $this->write('DEBUG', $message); }
    public function io(string $message): void { $this->write('IO', $message); }

    private function write(string $level, string $message): void {
        if (!$this->shouldLog($level)) return;
        $line = sprintf("%s [%s] %s\n", date('c'), $level, $message);
        $dir = dirname($this->filePath);
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        @file_put_contents($this->filePath, $line, FILE_APPEND | LOCK_EX);
    }

    private function shouldLog(string $level): bool {
        $order = ['ERROR' => 0, 'INFO' => 1, 'DEBUG' => 2, 'IO' => 1];
        $curr = $order[$this->level] ?? 1;
        $lvl = $order[$level] ?? 1;
        return $lvl <= $curr || $level === 'IO';
    }

    private static function isAbsolutePath(string $path): bool {
        return ($path[0] === '/' || preg_match('/^[A-Za-z]:\\\\/', $path) === 1);
    }
}
?>


