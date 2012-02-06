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
use \PDOStatement;
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
     * The version of Q4M
     *
     * @var string
     */
    const VERSION = '0.1.2';

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
     * Get version
     *
     * @return string
     */
    public function getVersion()
    {
        return self::VERSION;
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
            $statement = $this->bind($this->pdo->prepare($this->generateEnqueueSQL($table, $parameters)), $parameters);
            $result = $statement->execute();
            if ($result !== true) {
                throw new Q4MException(sprintf("Failed to insert. error=[%s]", implode(",", $statement->errorInfo())));
            }
            return $this;
        } catch (Exception $e) {
            $parameterString = str_replace("\n", "", var_export($parameters, true));
            throw new Q4MException(sprintf("Failed to enqueue. table=[%s], parameters=[%s]", $table, $parameterString), 0, $e);
        }
    }

    /**
     * Dequeue
     *
     * @param integer $style - PDO fetch style
     * @throws Exception
     * @return mixed (depends on the fetch type)
     */
    public function dequeue()
    {
        if ($this->isModeOwner() === false) {
            throw new Q4MException("Must execute any wait function before dequeueing.");
        }
        $count = func_num_args();
        $arguments = ($count === 0) ? array(\PDO::FETCH_ASSOC) : func_get_args();
        try {
            $statement = $this->query(sprintf('SELECT * FROM %s', $this->getWaitingTableName()));
            if ($count > 1) {
                if (call_user_func_array(array($statement, "setFetchMode"), $arguments) === false) {
                    throw new Q4MException("Failed to setFetchMode.");
                }
            }
            return $this->fetch($statement, $arguments[0]);
        } catch (Exception $e) {
            throw new Q4MException(sprintf("Failed to dequeue. arguments=[%s]", var_export($arguments, true)), 0, $e);
        }
    }

    /**
     * Wait with single table
     *
     * @param string $table
     * @throws Exception
     * @return \Siny\Q4MBundle\Queue\Q4M
     */
    public function waitWithSingleTable($table)
    {
        $function = sprintf("queue_wait(%s)", $this->pdo->quote($table));
        try {
            $this->executeFunction($function);
            return $this->wait($table);
        } catch (Exception $e) {
            throw new Q4MException(sprintf("Failed to wait with single table. function=[%s]", $function), 0, $e);
        }
    }

    /**
     * Wait with plural tables
     *
     * @param array $tables
     * @param integer $timeout
     * @throws Q4MException
     * @return \Siny\Q4MBundle\Queue\Q4M
     */
    public function waitWithPluralTables(array $tables, $timeout = 1)
    {
        $arguments = array();
        foreach ($tables as $table) {
            $arguments[] = $this->pdo->quote($table);
        }
        $arguments[] = intval($timeout);
        $function = sprintf("queue_wait(%s)", implode(",", $arguments));
        try {
            $value = $this->executeFunction($function, true);
            $index = intval($value[$function]) - 1;
            if (! isset($tables[$index])) {
                throw new Q4MException("All table aren't available.");
            }
            return $this->wait($tables[$index]);
        } catch (Exception $e) {
            $tablesString = str_replace("\n", "", var_export($tables, true));
            throw new Q4MException(sprintf("Failed to wait with plural tables. tables=[%s]", $tablesString), 0, $e);
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
     * Wait
     *
     * @param string $table
     * @return \Siny\Q4MBundle\Queue\Q4M
     */
    private function wait($table)
    {
        $this->waitingTable = $table;
        $this->isModeOwner  = true;
        return $this;
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
    private function executeFunction($function, $executionOnly = false)
    {
        try {
            $value = $this->fetch($this->query(sprintf('SELECT %s', $function)), PDO::FETCH_ASSOC);
            if (! isset($value[$function])) {
                throw new Q4MException("Failed to fetch");
            } else if (! $executionOnly && $value[$function] !== "1") {
                throw new Q4MException("Response is incorrect");
            }
            return $value[$function];
        } catch (Exception $e) {
            throw new Q4MException(sprintf("Failed to execute function. function=[%s]", $function), 0, $e);
        }
    }

    /**
     * Generate enqueue SQL
     *
     * @param string $table
     * @param array $parameters
     * @throws Q4MException
     * @return string
     */
    private function generateEnqueueSQL($table, array $parameters)
    {
        $count = count($parameters);
        if ($count === 0) {
            throw new Q4MException("The parameter is empty.");
        }
        $columns = array();
        foreach (array_keys($parameters) as $column) {
            $columns[] = sprintf('`%s`', $column);
        }
        $keys   = implode(",", $columns);
        $values = implode(",", array_fill(0, $count, '?'));
        return sprintf("INSERT INTO `%s` (%s) VALUES (%s)", $table, $keys, $values);
    }

    /**
     * Bind parameters
     *
     * @param PDOStatement $statement
     * @param array $parameters
     * @throws Q4MException
     * @return PDOStatement
     */
    private function bind(PDOStatement $statement, array $parameters)
    {
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
        return $statement;
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
            throw new Q4MException(sprintf("Failed to execute query. sql=[%s], message=[%s]", $sql, implode(",", $this->pdo->errorInfo())));
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
    private function fetch(PDOStatement $statement, $style)
    {
        $object = $statement->fetch($style);
        if ($object === false) {
            throw new Q4MException("Failed to fetch.");
        }
        return $object;
    }
}
