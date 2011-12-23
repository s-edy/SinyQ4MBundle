<?php
/**
 * This file is a part of Siny\Q4MBundle package.
 *
 * (c) Shinichiro Yuki <edy@siny.jp>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Siny\Q4MBundle\Queue;

use \PDO;
use \Exception;

/**
 * This is a class that is very simply Q4M client.
 *
 * @package SinyQ4M
 * @subpackage Queue
 * @author Shinichiro Yuki <edy@siny.jp>
 */
class Q4M
{
    /**
     * PDO object
     * @var PDO
     */
    private $pdo;

    /**
     * Whether mode is owner
     *
     * @var boolean
     */
    private $isModeOwner = false;

    /**
     * Queue table name
     *
     * @var string
     */
    private $table;

    /**
     * Constructing with PDO
     *
     * @param PDO $pdo
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get PDO object
     *
     * @return \Siny\Q4MBundle\Queue\PDO
     */
    public function getPDO()
    {
        return $this->pdo;
    }

    /**
     * Is mode Owner ?
     * @return boolean
     */
    public function isModeOwner()
    {
        return $this->isModeOwner;
    }

    /**
     * Wait on single table
     *
     * @param string $table
     * @throws Exception
     * @return \Siny\Q4MBundle\Queue\Q4M
     */
    public function waitOnSingleTable($table)
    {
        $statement = $this->pdo->prepare("SELECT queue_wait(:table)");
        $statement->bindParam(':table', $table, PDO::PARAM_STR);
        $result = $statement->execute();
        if ($result !== true) {
            throw new Exception(sprintf("Failed to queue_wait(). table=[%s]", $table));
        }
        $this->isModeOwner = true;
        $this->table = $table;
        return $this;
    }

    public function enqueue($table, array $parameters)
    {
        $count = count($parameters);
        $placeholders = array_fill(0, $count, '?');
        $statement = $this->pdo->prepare(sprintf("INSERT INTO %s (%s) VALUES (%s)", $table, implode(",", array_keys($parameters)), implode(",", $placeholders)));
        $index = 0;
        foreach ($parameters as $key => $value) {
            if (is_array($value)) {
                throw new Exception(sprintf("Parameter must be primitive. key=[%s]", $key));
            } else if (is_int($value) || ctype_digit(strval($value))) {
                $type = PDO::PARAM_INT;
            } elseif (is_bool($value)) {
                $type = PDO::PARAM_BOOL;
            } else {
                $type = PDO::PARAM_STR;
            }
            $statement->bindValue(++$index, $value, $type);
        }
        $result = $statement->execute();
        if ($result !== true) {
            throw new Exception(sprintf("Failed to enqueue. table=[%s] parameters=[%s]", $table, implode(",", $parameters)));
        }
        return $this;
    }

    /**
     * Dequeue
     *
     * @param integer $style - PDO fetch style
     * @throws Exception
     * @return mixed (depends on the fetch type)
     */
    public function dequeue($style = PDO::FETCH_ASSOC)
    {
        $statement = $this->pdo->query(sprintf('SELECT * FROM %s', $this->table));
        if ($statement === false) {
            throw new Exception(sprintf("Failed to dequeue. table=[%s]", $this->table));
        }
        $object = $statement->fetch($style);
        if ($object === false) {
            throw new Exception(sprintf("Failed to fetch. table=[%s]", $this->table));
        }
        return $object;
    }

    /**
     * End
     *
     * @throws Exception
     * @return \Siny\Q4MBundle\Queue\Q4M
     */
    public function end()
    {
        return $this->terminate('queue_end');
    }

    /**
     * Abort
     *
     * @throws Exception
     * @return \Siny\Q4MBundle\Queue\Q4M
     */
    public function abort()
    {
        return $this->terminate('queue_abort');
    }

    /**
     * Terminate
     *
     * @param string $function - A function name to finish. must be specified 'queue_abort' or 'queue_end'.
     * @throws Exception
     * @return \Siny\Q4MBundle\Queue\Q4M
     */
    private function terminate($function)
    {
        $result = $this->pdo->query(sprintf('SELECT %s()', $function));
        if ($result === false) {
            throw new Exception(sprintf("Failed to %s(). [%s]", var_export($this->pdo->errorInfo())));
        }
        $this->isModeOwner = false;
        return $this;
    }
}
