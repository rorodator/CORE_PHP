<?php
namespace Core\Base;

/**
 * Interface StatementInterface
 *
 * Represents a prepared statement abstraction.
 */
interface StatementInterface
{
    /**
     * Bind parameters and execute the statement.
     *
     * @param array $params
     * @return bool
     */
    public function execute(array $params = []);

    /**
     * Fetch all rows as associative arrays.
     *
     * @return array
     */
    public function fetchAll();

    /**
     * Fetch the first row as an associative array or null.
     *
     * @return array|null
     */
    public function fetchOne();

    /**
     * Fetch the value of the first column in the first row or null.
     *
     * @return mixed
     */
    public function fetchValue();

    /**
     * Return number of affected rows for the last operation.
     *
     * @return int
     */
    public function rowCount();
}

?>

