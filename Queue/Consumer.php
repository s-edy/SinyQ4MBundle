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
use Siny\Q4MBundle\Queue\Exception\Q4MException;
use Siny\Q4MBundle\Queue\Exception\ConsumerException;

/**
 * This is a class that is very simply Q4M client.
 *
 * @package SinyQ4M
 * @subpackage Queue
 * @author Shinichiro Yuki <edy@siny.jp>
 */
class Consumer
{
    private $q4m;

    private $table;

    private $queue;

    public function __construct(Q4M $q4m)
    {
        $this->q4m = $q4m;
    }

    public function getQueue()
    {
        return $this->queue;
    }

    public function consume()
    {
        $count = func_num_args();
        if ($count === 0) {
            throw new ConsumerException("The 1st argument must specified to consuming table name.");
        }
        $arguments = func_get_args();
        try {
            $this->getQ4M()->waitWithSingleTable(array_shift($arguments));
            $this->queue = call_user_func_array(array($this->getQ4M(), "dequeue"), $arguments);
            return $this->queue;
        } catch (\Exception $e) {
            throw new ConsumerException(sprintf("Failed to consume. arguments=[%s]", var_export($arguments, true)), 0, $e);
        }
    }

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

    public function retry($queue)
    {
        try {
            $this->end();
            $this->getQ4M()->enqueue($this->table, $queue);
        } catch (\Exception $e) {
            throw new ConsumerException("Failed to cancel", 0, $e);
        }
    }


    protected function getQ4M()
    {
        return $this->q4m;
    }
}
