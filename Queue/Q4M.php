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
 * @package    SinyQ4M
 * @subpackage Queue
 * @author     Shinichiro Yuki <edy@siny.jp>
 */
class Q4M
{
    /**
     * The version of Q4M
     *
     * @var string
     */
    const VERSION = '0.2.2';

    /**
     * DSN
     *
     * @var string
     */
    private $dsn;

    /**
     * Connection user name
     */
    private $user;

    /**
     * Connection password
     *
     * @var string
     */
    private $password;

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
     * @param string $dsn      DSN string
     * @param string $user     connection user name
     * @param string $password connection password
     */
    public function __construct($dsn, $user, $password)
    {
        $this->dsn = $dsn;
        $this->user = $user;
        $this->password = $password;
        $this->reconnect();
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
     *
     * @return boolean if mode is owner, return true. otherwise returns false.
     */
    public function isModeOwner()
    {
        return $this->isModeOwner;
    }

    /**
     * Get table name
     *
     * @return mixed return the table name when its name was set.
     *               if its name was not set, return null.
     */
    public function getWaitingTableName()
    {
        return $this->waitingTable;
    }

    /**
     * Reconnect
     *
     * @return null
     *
     * @throws Q4MException
     */
    public function reconnect()
    {
        try {
            $this->pdo = new \PDO($this->dsn, $this->user, $this->password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (\Exception $e) {
            throw new Q4MException(sprintf("Connecting failed."), 0, $e);
        }
    }

    /**
     * Enqueue
     *
     * @param string $table      specify a table name of queueing target.
     * @param array  $parameters the queueing parameters
     *
     * @return \Siny\Q4MBundle\Queue\Q4M
     *
     * @throws Q4MException
     */
    public function enqueue($table, array $parameters)
    {
        try {
            $statement = $this->bind($this->getPDO()->prepare($this->generateEnqueueSQL($table, $parameters)), $parameters);
            $result = $statement->execute();
            if ($result !== true) {
                throw new Q4MException(sprintf("Failed to insert. error=[%s]", implode(",", $statement->errorInfo())));
            }
        } catch (Exception $e) {
            $parameterString = str_replace("\n", "", var_export($parameters, true));
            throw new Q4MException(sprintf("Failed to enqueue. table=[%s], parameters=[%s]", $table, $parameterString), 0, $e);
        }

        return $this;
    }

    /**
     * Dequeue
     *
     * @param integer $style PDO fetch style
     *
     * @return mixed (depends on the fetch type)
     *
     * @throws Exception
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
        } catch (Exception $e) {
            throw new Q4MException(sprintf("Failed to dequeue. arguments=[%s]", var_export($arguments, true)), 0, $e);
        }

        return $this->fetch($statement, $arguments[0]);
    }

    /**
     * Wait with single table
     *
     * @param string $table table name for waiting
     *
     * @return \Siny\Q4MBundle\Queue\Q4M
     *
     * @throws Exception
     */
    public function waitWithSingleTable($table)
    {
        $function = sprintf("queue_wait(%s)", $this->getPDO()->quote($table));
        try {
            $this->executeFunction($function);
        } catch (Exception $e) {
            throw new Q4MException(sprintf("Failed to wait with single table. function=[%s]", $function), 0, $e);
        }

        return $this->wait($table);
    }

    /**
     * Wait with plural tables
     *
     * @param array   $tables  table name for waiting
     * @param integer $timeout timeout seconds
     *
     * @return \Siny\Q4MBundle\Queue\Q4M
     *
     * @throws Q4MException
     */
    public function waitWithPluralTables(array $tables, $timeout = 1)
    {
        $arguments = array();
        foreach ($tables as $table) {
            $arguments[] = $this->getPDO()->quote($table);
        }
        $arguments[] = intval($timeout);
        $function = sprintf("queue_wait(%s)", implode(",", $arguments));
        try {
            $value = $this->executeFunction($function, true);
            $index = intval($value[$function]) - 1;
            if (! isset($tables[$index])) {
                throw new Q4MException("All table aren't available.");
            }
        } catch (Exception $e) {
            $tablesString = str_replace("\n", "", var_export($tables, true));
            throw new Q4MException(sprintf("Failed to wait with plural tables. tables=[%s]", $tablesString), 0, $e);
        }

        return $this->wait($tables[$index]);
    }

    /**
     * End
     *
     * @return \Siny\Q4MBundle\Queue\Q4M
     *
     * @throws Exception
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
     * @return \Siny\Q4MBundle\Queue\Q4M
     *
     * @throws Exception
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
     *
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
     * @param string $function A function name to finish. must be specified 'queue_abort' or 'queue_end'.
     *
     * @return \Siny\Q4MBundle\Queue\Q4M
     *
     * @throws Exception
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
     * @param string  $function      A function to execute.
     * @param boolean $executionOnly whether validate result row
     *
     * @return Siny\Q4MBundle\Queue\Q4M
     *
     * @throws Q4MException
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
        } catch (Exception $e) {
            throw new Q4MException(sprintf("Failed to execute function. function=[%s]", $function), 0, $e);
        }

        return $value[$function];
    }

    /**
     * Generate enqueue SQL
     *
     * @param string $table      table name for enqueueing
     * @param array  $parameters enqueueing parameters
     *
     * @return string
     *
     * @throws Q4MException
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
     * @param PDOStatement $statement  statement object to bind parameters
     * @param array        $parameters parameters in order to bind
     *
     * @return PDOStatement
     *
     * @throws Q4MException
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
     * @param string $sql SQL to execute query
     *
     * @return PDOStatement
     *
     * @throws Q4MException
     */
    private function query($sql)
    {
        $statement = $this->getPDO()->query($sql);
        if ($statement === false) {
            throw new Q4MException(sprintf("Failed to execute query. sql=[%s], message=[%s]", $sql, implode(",", $this->getPDO()->errorInfo())));
        }

        return $statement;
    }

    /**
     * Execute fetch by statement
     *
     * @param PDOStatement $statement statement object to fetch row
     * @param integer      $style     style to fetch row
     *
     * @return mixed (depends on the fetch type)
     *
     * @throws Q4MException
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
