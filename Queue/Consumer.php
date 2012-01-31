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

    private $fetchMode = \PDO::FETCH_ASSOC;

    private $queue;

    public function __construct(Q4M $q4m)
    {
        $this->q4m = $q4m;
    }

    public function resetFetchMode()
    {
        $this->fetchMode = \PDO::FETCH_ASSOC;

        return $this;
    }

    public function getFetchMode()
    {
        return $this->fetchMode;
    }

    public function getQueue()
    {
        return $this->queue;
    }

    public function setFetchClass($className, array $arguments = array())
    {
        $result = $this->getQ4M()->getPDO()->setFetchMode(\PDO::FETCH_CLASS, $className, $arguments);
        if (! $result) {
            throw new ConsumerException(sprintf("Failed to set fetch mode to FETCH_CLASS. className=[%s], arguments=[%s]", $className, var_export($arguments, true)));
        }
        $this->fetchMode = \PDO::FETCH_CLASS;
    }

    public function setFetchInto($object)
    {
        $result = $this->getQ4M()->getPDO()->setFetchMode(\PDO::FETCH_INTO, $object);
        if (! $result) {
            throw new ConsumerException(sprintf("Failed to set fetch mode to FETCH_INTO. object=[%s]", get_class($object)));
        }
        $this->fetchMode = \PDO::FETCH_INTO;
     }

    public function consume($table)
    {
        try {
            $this->queue = $this->getQ4M()->waitWithSingleTable($table)->dequeue($this->getFetchMode());
            return $this->queue;
        } catch (\Exception $e) {
            throw new ConsumerException(sprintf("Failed to consume. table=[%s]", $table), 0, $e);
        }
    }

    protected function getQ4M()
    {
        return $this->q4m;
    }
}
