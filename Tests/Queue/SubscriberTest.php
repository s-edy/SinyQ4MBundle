<?php
/**
 * This file is a part of Siny\Q4MBundle package.
*
* (c) Shinichiro Yuki <edy@siny.jp>
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace Siny\Q4MBundle\Tests\Queue;

use Siny\Q4MBundle\Queue\Q4M;
use Siny\Q4MBundle\Queue\Subscriber;

class SubscriberTest extends \PHPUnit_Framework_TestCase
{
    const Q4M_CLASS   = "Siny\Q4MBundle\Queue\Q4M";
    const DUMMY_TABLE = 'dummy_table';

    private $q4m;
    private $subscriber;

    /**
     * Set up Q4M client
     */
    public function setUp()
    {
        $this->q4m = $this->getMock(self::Q4M_CLASS, array(), array(), "", false);
        $this->subscriber = new Subscriber($this->q4m);
    }

    public function testGetQ4M()
    {
        $this->assertSame($this->q4m, $this->subscriber->getQ4M(), "Q4M class instance wasn't returned.");
    }

    public function testSelfObjectWillReturnWhenSubscribing()
    {
        $this->assertSame($this->subscriber, $this->subscriber->subscribe(self::DUMMY_TABLE, array('foo' => 'bar')), "Self object will return");
    }

    /**
     * @expectedException Siny\Q4MBundle\Queue\Exception\SubscriberException
     */
    public function testExceptionWillOccurWhenSubscribingWithEmptyArray()
    {
        $this->subscriber->subscribe(self::DUMMY_TABLE, array());
    }

    /**
     * @expectedException Siny\Q4MBundle\Queue\Exception\SubscriberException
     */
    public function testExceptionWillOccurWhenSubscribing()
    {
        $q4m = $this->getMock(self::Q4M_CLASS, array('enqueue'), array(), "", false);
        $q4m->expects($this->once())->method('enqueue')->will($this->throwException(new \Exception()));
        $subscriber = new Subscriber($q4m);
        $subscriber->subscribe(self::DUMMY_TABLE, array('foo' => 'bar'));
    }
}
