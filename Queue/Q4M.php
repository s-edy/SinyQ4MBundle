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

use Siny\Q4MBundle\Queue\Exception\Q4MException;
use \PDO;
use \PDOException;
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
     * Waiting queue table name
     *
     * @var string
     */
    private $waitingTable;

    /**
     * Constructing with PDO
     *
     * @param PDO $pdo
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
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
     * Get table name
     *
     * @return mixed - return the table name when its name was set.
     *                 if its name was not set, return null.
     */
    public function getWaitingTableName()
    {
        return $this->waitingTable;
    }

    /**
     * Enqueue
     *
     * @param string $table     - specify a table name of queueing target.
     * @param array $parameters - the queueing parameters
     * @throws Q4MException
     * @return \Siny\Q4MBundle\Queue\Q4M
     */
    public function enqueue($table, array $parameters)
    {
        try {
            $count = count($parameters);
            if ($count === 0) {
                throw new Q4MException("The parameter is empty.");
            }
            $placeholders = array_fill(0, $count, '?');
            $sql = sprintf(
                "INSERT INTO %s (%s) VALUES (%s)",
                $table, implode(",", array_keys($parameters)), implode(",", $placeholders));
            $statement = $this->pdo->prepare($sql);
            $index = 0;
            foreach ($parameters as $key => $value) {
                if (is_array($value)) {
                    throw new Q4MException(sprintf("The parameter must be primitive. key=[%s]", $key));
                } else if (is_bool($value)) {
                    $type = PDO::PARAM_BOOL;
                } else if (is_int($value) || ctype_digit(strval($value))) {
                    $type = PDO::PARAM_INT;
                } else {
                    $type = PDO::PARAM_STR;
                }
                $statement->bindValue(++$index, $value, $type);
            }
            try {
                $result = $statement->execute();
                if ($result !== true) {
                    throw new Q4MException(sprintf("Failed to insert. error=[%s]", implode(",", $statement->errorInfo())));
                }
                return $this;
            } catch (PDOException $e) {
                throw new Q4MException("Failed to execute SQL.", 0, $e);
            }
        } catch (Exception $e) {
            throw new Q4MException(sprintf(
                "Failed to enqueue. table=[%s], parameters=[%s]",
                $table, str_replace("\n", "", var_export($parameters, true))), 0, $e);
        }
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
        try {
            if ($this->isModeOwner() === false) {
                throw new Q4MException("Must execute any wait function before dequeueing.");
            }
            $statement = $this->query(sprintf('SELECT * FROM %s', $this->getWaitingTableName()));
            return $this->fetch($statement, $style);
        } catch (Exception $e) {
            throw new Q4MException(sprintf("Failed to dequeue. style=[%s]", $style), 0, $e);
        }
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
        try {
            $function = sprintf("queue_wait(%s)", $this->pdo->quote($table));
            $this->executeFunction($function);
            $this->isModeOwner = true;
            $this->waitingTable = $table;
            return $this;
        } catch (Exception $e) {
            throw new Q4MException(sprintf("Failed to wait on single table. function=[%s]", $function), 0, $e);
        }
    }

    /**
     * End
     *
     * @throws Exception
     * @return \Siny\Q4MBundle\Queue\Q4M
     */
    public function end()
    {
        try {
            return $this->terminate('queue_end()');
        } catch (Exception $e) {
            throw new Q4MException("Failed to queue_end()", 0, $e);
        }
    }

    /**
     * Abort
     *
     * @throws Exception
     * @return \Siny\Q4MBundle\Queue\Q4M
     */
    public function abort()
    {
        try {
            return $this->terminate('queue_abort()');
        } catch (Exception $e) {
            throw new Q4MException("Failed to queue_abort()", 0, $e);
        }
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
        $this->executeFunction($function);
        $this->isModeOwner  = false;
        $this->waitingTable = null;
        return $this;
    }

    /**
     * Execute function
     *
     * @param string $function - A function to execute.
     * @throws Q4MException
     * @return Siny\Q4MBundle\Queue\Q4M
     */
    private function executeFunction($function, $executionOnly = true)
    {
        try {
            $statement = $this->query(sprintf('SELECT %s', $function));
            $value = $this->fetch($statement, PDO::FETCH_ASSOC);
            if (! isset($value[$function])) {
                throw new Q4MException("Failed to fetch");
            } else if ($executionOnly && $value[$function] !== "1") {
                throw new Q4MException("Response is incorrect");
            }
            return $value[$function];
        } catch (Exception $e) {
            throw new Q4MException(sprintf("Failed to execute function. function=[%s]", $function), 0, $e);
        }
    }

    /**
     * Execute query by PDO
     *
     * @param string $sql
     * @throws Q4MException
     * @return PDOStatement
     */
    private function query($sql)
    {
        $statement = $this->pdo->query($sql);
        if ($statement === false) {
            throw new Q4MException(sprintf(
                "Failed to execute query. sql=[%s], message=[%s]",
                $sql, implode(",", $this->pdo->errorInfo())));
        }
        return $statement;
    }

    /**
     * Execute fetch by statement
     *
     * @param PDOStatement $statement
     * @param integer $style
     * @throws Q4MException
     * @return mixed (depends on the fetch type)
     */
    private function fetch($statement, $style)
    {
        $object = $statement->fetch($style);
        if ($object === false) {
            throw new Q4MException("Failed to fetch.");
        }
        return $object;
    }
}
