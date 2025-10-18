<?php
namespace Core\Base;

use PDOStatement;

/**
 * Class PDOStatementAdapter
 *
 * Adapts \PDOStatement to StatementInterface.
 */
class PDOStatementAdapter implements StatementInterface
{
    /** @var PDOStatement */
    private $statement;

    /**
     * @param PDOStatement $statement
     */
    public function __construct(PDOStatement $statement)
    {
        $this->statement = $statement;
    }

    /** @inheritDoc */
    public function execute(array $params = [])
    {
        return $this->statement->execute($params);
    }

    /** @inheritDoc */
    public function fetchAll()
    {
        return $this->statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** @inheritDoc */
    public function fetchOne()
    {
        $row = $this->statement->fetch(\PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /** @inheritDoc */
    public function fetchValue()
    {
        $value = $this->statement->fetchColumn(0);
        return $value !== false ? $value : null;
    }

    /** @inheritDoc */
    public function rowCount()
    {
        return $this->statement->rowCount();
    }
}

?>

