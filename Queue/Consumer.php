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

use Siny\Q4MBundle\Queue\Q4M;
use Siny\Q4MBundle\Queue\Exception\ConsumerException;

/**
 * This is a consumer class for Q4M
 *
 * @package SinyQ4M
 * @subpackage Queue
 * @author Shinichiro Yuki <edy@siny.jp>
 */
class Consumer
{
    /**
     * Q4M
     *
     * @var Q4M
     */
    private $q4m;

    /**
     * Consuming target table
     *
     * @var string
     */
    private $table;

    /**
     * Queue
     *
     * @var mixed
     */
    private $queue;

    /**
     * Construct with Q4M to consume
     *
     * @param Q4M $q4m
     */
    public function __construct(Q4M $q4m)
    {
        $this->q4m = $q4m;
    }

    /**
     * GetQ4M
     *
     * @return Q4M
     */
    public function getQ4M()
    {
        return $this->q4m;
    }

    /**
     * Get queue
     *
     * @return mixed Queue
     */
    public function getQueue()
    {
        return $this->queue;
    }

    /**
     * Consume
     *
     * you can specify parameters to dequeue and fetch, like `PDOStatement::setFetchMode()`.
     * consume($table string [, $style integer][, mixed options][, ...])
     *
     * @throws \Siny\Q4MBundle\Queue\Exception\ConsumerException
     * @return mixed - Queue
     */
    public function consume()
    {
        $count = func_num_args();
        if ($count === 0) {
            throw new ConsumerException("The 1st argument must specified to consuming table name.");
        }
        $arguments = func_get_args();
        try {
            $this->table = array_shift($arguments);
            $this->getQ4M()->waitWithSingleTable($this->table);
            $this->queue = call_user_func_array(array($this->getQ4M(), "dequeue"), $arguments);
            return $this->getQueue();
        } catch (\Exception $e) {
            throw new ConsumerException(sprintf("Failed to consume. arguments=[%s]", var_export($arguments, true)), 0, $e);
        }
    }

    /**
     * End
     *
     * @throws ConsumerException
     */
    public function end()
    {
        try {
            if ($this->getQ4M()->isModeOwner()) {
                $this->getQ4M()->end();
            }
        } catch (\Exception $e) {
            throw new ConsumerException("Failed to end", 0, $e);
        }
    }

    /**
     * Abort
     *
     * @throws ConsumerException
     */
    public function abort()
    {
        try {
            if ($this->getQ4M()->isModeOwner()) {
                $this->getQ4M()->abort();
            }
        } catch (\Exception $e) {
            throw new ConsumerException("Failed to abort", 0, $e);
        }
    }

    /**
     * Retry
     *
     * Re-enqueue a queue keeped inside, and ends it.
     *
     * @throws \LogicException
     * @throws ConsumerException
     */
    public function retry()
    {
        try {
            if (is_null($this->getQueue())) {
                throw new \LogicException("nothing to retry subscribing queue.");
            }
            $this->getQ4M()->enqueue($this->table, $this->getQueue());
            $this->end();
        } catch (\Exception $e) {
            throw new ConsumerException(sprintf("Failed to retry. queue=[%s]", serialize($this->getQueue())), 0, $e);
        }
    }
}
