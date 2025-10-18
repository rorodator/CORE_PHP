<?php
namespace Core\Base;

/**
 * Class PDO
 *
 * Service wrapper registered as `db = Core\\Base\\PDO` in configuration.
 * Provides a lazily-initialized DBInterface backed by PDOAdapter.
 */
class PDO implements DBInterface
{
    /** @var PDOAdapter */
    private $adapter;

    /**
     * Build a PDO-backed DB service using environment/config hints.
     * Defaults to a local SQLite database located at PHP/DATABASE/my_manager.sqlite
     * at the project root when DSN is not provided via environment.
     */
    public function __construct()
    {
        // Get configuration from core()
        $dbConf = [];
        if (core() && core()->getConfigSection('database')) {
            $dbConf = core()->getConfigSection('database');
        }

        // Build DSN from configuration
        list($dsn, $user, $pass) = $this->buildDsnFromConfig($dbConf);

        $this->adapter = new PDOAdapter($dsn, $user, $pass);
        
        // Auto-connect on instantiation for convenience
        $this->connect();
    }

    /** @inheritDoc */
    public function connect()
    {
        $this->adapter->connect();
    }

    /** @inheritDoc */
    public function isConnected()
    {
        return $this->adapter->isConnected();
    }

    /** @inheritDoc */
    public function beginTransaction()
    {
        $this->adapter->beginTransaction();
    }

    /** @inheritDoc */
    public function commit()
    {
        $this->adapter->commit();
    }

    /** @inheritDoc */
    public function rollBack()
    {
        $this->adapter->rollBack();
    }

    /** @inheritDoc */
    public function lastInsertId()
    {
        return $this->adapter->lastInsertId();
    }

    /** @inheritDoc */
    public function execute($sql, array $params = [])
    {
        return $this->adapter->execute($sql, $params);
    }

    /** @inheritDoc */
    public function query($sql, array $params = [])
    {
        return $this->adapter->query($sql, $params);
    }

    /** @inheritDoc */
    public function queryOne($sql, array $params = [])
    {
        return $this->adapter->queryOne($sql, $params);
    }

    /** @inheritDoc */
    public function queryValue($sql, array $params = [])
    {
        return $this->adapter->queryValue($sql, $params);
    }

    /** @inheritDoc */
    public function prepare($sql)
    {
        return $this->adapter->prepare($sql);
    }


    /**
     * Build DSN, user and password from [database] configuration.
     * Supported keys:
     * - dsn: full DSN string (takes precedence)
     * - driver: e.g. sqlite, mysql, pgsql
     * - file: for sqlite file path (must be absolute path)
     * - host, port, dbname, charset (for non-sqlite)
     * - user, password
     *
     * @param array $dbConf
     * @return array [dsn, user, pass]
     */
    private function buildDsnFromConfig(array $dbConf)
    {
        $user = isset($dbConf['user']) ? (string) $dbConf['user'] : null;
        $pass = isset($dbConf['password']) ? (string) $dbConf['password'] : null;

        if (!empty($dbConf['dsn'])) {
            return [(string) $dbConf['dsn'], $user, $pass];
        }

        $driver = isset($dbConf['driver']) ? (string) $dbConf['driver'] : 'sqlite';

        if ($driver === 'sqlite') {
            $file = isset($dbConf['file']) ? (string) $dbConf['file'] : 'PHP/DATABASE/my_manager.sqlite';
            // For SQLite, file path should be absolute as configured in the INI
            $dsn = 'sqlite:' . $file;
            return [$dsn, null, null];
        }

        // Generic builders for common drivers
        $host = isset($dbConf['host']) ? (string) $dbConf['host'] : '127.0.0.1';
        $port = isset($dbConf['port']) ? (string) $dbConf['port'] : '';
        $dbname = isset($dbConf['dbname']) ? (string) $dbConf['dbname'] : '';
        $charset = isset($dbConf['charset']) ? (string) $dbConf['charset'] : '';

        $parts = [];
        if ($host !== '') { $parts[] = 'host=' . $host; }
        if ($port !== '') { $parts[] = 'port=' . $port; }
        if ($dbname !== '') { $parts[] = 'dbname=' . $dbname; }
        if ($charset !== '') { $parts[] = 'charset=' . $charset; }
        $dsn = $driver . ':' . implode(';', $parts);

        return [$dsn, $user, $pass];
    }
}

?>

