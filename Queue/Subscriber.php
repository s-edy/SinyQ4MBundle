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
use Siny\Q4MBundle\Queue\Exception\SubscriberException;

/**
 * This is a class that is very simply Q4M client.
 *
 * @package SinyQ4M
 * @subpackage Queue
 * @author Shinichiro Yuki <edy@siny.jp>
 */
class Subscriber
{
    private $q4m;

    public function __construct(Q4M $q4m)
    {
        $this->q4m = $q4m;
    }

    public function getQ4M()
    {
        return $this->q4m;
    }

    public function subscribe($table, array $queue)
    {
        try {
            if (empty($queue)) {
                throw new \InvalidArgumentException("Queue was empty.");
            }
            $this->getQ4M()->enqueue($table, $queue);
            return $this;
        } catch (\Exception $e) {
            throw new SubscriberException(sprintf("Failed to subscribe. table=[%s], queue=[%s]", $table, var_export($queue, true)), 0, $e);
        }
    }
}
