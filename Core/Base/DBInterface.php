<?php
namespace Core\Base;

/**
 * Interface DBInterface
 *
 * Defines an engine-agnostic database abstraction to decouple
 * application code from any specific driver (e.g. SQLite, MySQL).
 */
interface DBInterface
{
    /**
     * Establish the underlying connection if not yet connected.
     *
     * @return void
     */
    public function connect();

    /**
     * Indicates whether a connection is currently available.
     *
     * @return bool
     */
    public function isConnected();

    /**
     * Begin a database transaction.
     *
     * @return void
     */
    public function beginTransaction();

    /**
     * Commit the current transaction.
     *
     * @return void
     */
    public function commit();

    /**
     * Roll back the current transaction.
     *
     * @return void
     */
    public function rollBack();

    /**
     * Return the last inserted identifier for the current connection.
     *
     * @return string
     */
    public function lastInsertId();

    /**
     * Execute a statement and return number of affected rows.
     *
     * @param string $sql
     * @param array $params
     * @return int
     */
    public function execute($sql, array $params = []);

    /**
     * Execute a query and return all rows as associative arrays.
     *
     * @param string $sql
     * @param array $params
     * @return array
     */
    public function query($sql, array $params = []);

    /**
     * Execute a query and return the first row or null when none.
     *
     * @param string $sql
     * @param array $params
     * @return array|null
     */
    public function queryOne($sql, array $params = []);

    /**
     * Execute a query and return the first column of the first row.
     *
     * @param string $sql
     * @param array $params
     * @return mixed
     */
    public function queryValue($sql, array $params = []);

    /**
     * Prepare a statement for repeated execution.
     *
     * @param string $sql
     * @return StatementInterface
     */
    public function prepare($sql);
}

?>

