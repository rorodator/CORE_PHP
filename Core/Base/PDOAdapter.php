<?php
namespace Core\Base;

use PDO;
use PDOException;

/**
 * Class PDOAdapter
 *
 * PDO-backed implementation of DBInterface, engine-agnostic for callers.
 * Supports DSN like `sqlite:/abs/path/file.sqlite` or e.g. `mysql:host=...;dbname=...`.
 */
class PDOAdapter implements DBInterface
{
    /** @var string */
    private $dsn;

    /** @var string|null */
    private $username;

    /** @var string|null */
    private $password;

    /** @var array */
    private $options;

    /** @var PDO|null */
    private $pdo;

    /**
     * @param string $dsn
     * @param string|null $username
     * @param string|null $password
     * @param array $options
     */
    public function __construct($dsn, $username = null, $password = null, array $options = [])
    {
        $this->dsn = $dsn;
        $this->username = $username;
        $this->password = $password;
        $this->options = $options + [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $this->pdo = null;
    }

    /** @inheritDoc */
    public function connect()
    {
        if ($this->pdo instanceof PDO) {
            return;
        }
        try {
            $this->pdo = new PDO($this->dsn, $this->username, $this->password, $this->options);
        } catch (PDOException $e) {
            // Log safe diagnostics server-side; never expose DSN, credentials, or SQL text.
            core()->log->error($this->formatDbFailureLog('connect', $e));
            throw new \RuntimeException('Database connection error');
        }
    }

    /** @inheritDoc */
    public function isConnected()
    {
        return $this->pdo instanceof PDO;
    }

    /** @inheritDoc */
    public function beginTransaction()
    {
        $this->ensureConnected();
        $this->pdo->beginTransaction();
    }

    /** @inheritDoc */
    public function commit()
    {
        $this->ensureConnected();
        $this->pdo->commit();
    }

    /** @inheritDoc */
    public function rollBack()
    {
        $this->ensureConnected();
        $this->pdo->rollBack();
    }

    /** @inheritDoc */
    public function lastInsertId()
    {
        $this->ensureConnected();
        return $this->pdo->lastInsertId();
    }

    /** @inheritDoc */
    public function execute($sql, array $params = [])
    {
        $this->ensureConnected();
        try {
            if (empty($params)) {
                return $this->pdo->exec($sql);
            }
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            $this->logSqlError('execute', $sql, $params, $e);
            throw new \RuntimeException('Database error');
        }
    }

    /** @inheritDoc */
    public function query($sql, array $params = [])
    {
        $this->ensureConnected();
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->logSqlError('query', $sql, $params, $e);
            throw new \RuntimeException('Database error');
        }
    }

    /** @inheritDoc */
    public function queryOne($sql, array $params = [])
    {
        $this->ensureConnected();
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row !== false ? $row : null;
        } catch (PDOException $e) {
            $this->logSqlError('queryOne', $sql, $params, $e);
            throw new \RuntimeException('Database error');
        }
    }

    /** @inheritDoc */
    public function queryValue($sql, array $params = [])
    {
        $this->ensureConnected();
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $value = $stmt->fetchColumn(0);
            return $value !== false ? $value : null;
        } catch (PDOException $e) {
            $this->logSqlError('queryValue', $sql, $params, $e);
            throw new \RuntimeException('Database error');
        }
    }

    /** @inheritDoc */
    public function prepare($sql)
    {
        $this->ensureConnected();
        try {
            $stmt = $this->pdo->prepare($sql);
            return new PDOStatementAdapter($stmt);
        } catch (PDOException $e) {
            $this->logSqlError('prepare', $sql, [], $e);
            throw new \RuntimeException('Database error');
        }
    }

    /**
     * Ensure connection is available.
     *
     * @return void
     */
    private function ensureConnected()
    {
        if (!$this->pdo instanceof PDO) {
            $this->connect();
        }
    }

    /**
     * Log SQL error diagnostics without SQL text, bound values, or DSN details.
     *
     * @param string $action
     * @param string $sql
     * @param array $params
     * @param PDOException $e
     * @return void
     */
    private function logSqlError($action, $sql, array $params, PDOException $e)
    {
        core()->log->error($this->formatDbFailureLog($action, $e));
    }

    /**
     * Build a conservative DB failure log line (no SQL, params, DSN, or exception message).
     *
     * @param string $operation
     * @param PDOException $e
     * @return string
     */
    private function formatDbFailureLog($operation, PDOException $e)
    {
        $parts = [
            'DB ' . $operation . ' failed',
            'exception=' . get_class($e),
        ];

        $sqlState = $this->extractSqlState($e);
        if ($sqlState !== '') {
            $parts[] = 'sqlstate=' . $sqlState;
        }

        $driverCode = $this->extractDriverCode($e);
        if ($driverCode !== null) {
            $parts[] = 'code=' . $driverCode;
        }

        if ($operation === 'connect') {
            $driverHint = $this->extractDsnDriverHint($this->dsn);
            if ($driverHint !== '') {
                $parts[] = 'driver=' . $driverHint;
            }
        }

        return implode('; ', $parts);
    }

    /**
     * Extract SQLSTATE from a PDOException without logging the full message.
     *
     * @param PDOException $e
     * @return string
     */
    private function extractSqlState(PDOException $e)
    {
        $code = (string)$e->getCode();
        if (preg_match('/^[A-Z0-9]{5}$/', $code) === 1) {
            return $code;
        }
        if (preg_match('/SQLSTATE\[([A-Z0-9]{5})\]/', $e->getMessage(), $matches) === 1) {
            return $matches[1];
        }
        return '';
    }

    /**
     * Extract a numeric driver error code when present in the PDO message format.
     *
     * @param PDOException $e
     * @return int|null
     */
    private function extractDriverCode(PDOException $e)
    {
        if (preg_match('/SQLSTATE\[[^\]]+\]:\s(?:[^:]*:\s)?(\d+)/', $e->getMessage(), $matches) === 1) {
            return (int)$matches[1];
        }
        return null;
    }

    /**
     * Return only the DSN driver prefix (e.g. mysql, sqlite) — never host/db/credentials.
     *
     * @param string $dsn
     * @return string
     */
    private function extractDsnDriverHint($dsn)
    {
        $separator = strpos($dsn, ':');
        if ($separator === false) {
            return '';
        }
        return substr($dsn, 0, $separator);
    }
}
