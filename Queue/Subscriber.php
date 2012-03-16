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
use Siny\Q4MBundle\Queue\Exception\SubscriberException;

/**
 * This is a subscriber class for Q4M
 *
 * @package    SinyQ4M
 * @subpackage Queue
 * @author     Shinichiro Yuki <edy@siny.jp>
 */
class Subscriber
{
    /**
     * Q4M
     *
     * @var Q4M
     */
    private $q4m;

    /**
     * Construct with Q4M to subscribe
     *
     * @param Siny\Q4MBundle\Queue\Q4M $q4m
     */
    public function __construct(Q4M $q4m)
    {
        $this->q4m = $q4m;
    }

    /**
     * Get Q4M
     *
     * @return Siny\Q4MBundle\Queue\Q4M
     */
    public function getQ4M()
    {
        return $this->q4m;
    }

    /**
     * Subscribe
     *
     * @param string $table table name to subscribe
     * @param array  $queue queue to subscribe
     *
     * @return Siny\Q4MBundle\Queue\Subscriber
     *
     * @throws SubscriberException
     */
    public function subscribe($table, array $queue)
    {
        try {
            if (empty($queue)) {
                throw new \InvalidArgumentException("Queue was empty.");
            }
            $this->getQ4M()->enqueue($table, $queue);
        } catch (\Exception $e) {
            throw new SubscriberException(sprintf("Failed to subscribe. table=[%s], queue=[%s]", $table, var_export($queue, true)), 0, $e);
        }

        return $this;
    }
}
