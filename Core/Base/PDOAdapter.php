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
            // Log detailed error server-side, but do not leak details to UI
            core()->log->error('DB connect failed: ' . $e->getMessage() . ' DSN=' . $this->dsn);
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
     * Log SQL error details to server log.
     *
     * @param string $action
     * @param string $sql
     * @param array $params
     * @param PDOException $e
     * @return void
     */
    private function logSqlError($action, $sql, array $params, PDOException $e)
    {
        $safeParams = @json_encode($params);
        core()->log->error("DB {$action} failed: " . $e->getMessage() . " SQL=" . $sql . " PARAMS=" . $safeParams);
    }
}

?>

