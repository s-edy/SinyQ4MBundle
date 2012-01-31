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
use Siny\Q4MBundle\Queue\Consumer;

class ConsumerTest extends \PHPUnit_Framework_TestCase
{
    const Q4M_CLASS   = "Siny\Q4MBundle\Queue\Q4M";
    const DUMMY_TABLE = 'dummy_table';

    private $q4m;
    private $consumer;

    /**
     * Set up Q4M client
     */
    public function setUp()
    {
        $this->q4m = $this->getMock(self::Q4M_CLASS, array(), array(), "", false);
        $this->consumer = new Consumer($this->q4m);
    }

    public function testSelfObjectWillReturnWhenResetFetchMode()
    {
        $this->assertSame($this->consumer, $this->consumer->resetFetchMode(), "Self object wasn't returned");
    }

    public function testGetFetchModeInTheCaseOfDefault()
    {
        $this->assertSame(\PDO::FETCH_ASSOC, $this->consumer->getFetchMode(), "Fetch mode wasn't same.");
    }

    public function testNullReturnWhenInvokingGetQueueInTheCaseOfDefault()
    {
        $this->assertNull($this->consumer->getQueue(), "Queue wasn't null in the case of default");
    }

    public function testSetFetchClassMode()
    {
        $consumer = new Consumer($this->getMockOfQ4MAsInvokeSetFetchMode(true));
        $consumer->setFetchClass("stdClass");
        $this->assertSame(\PDO::FETCH_CLASS, $consumer->getFetchMode(), "The fetch mode wasn't CLASS.");
    }

    public function testSetFetchIntoMode()
    {
        $consumer = new Consumer($this->getMockOfQ4MAsInvokeSetFetchMode(true));
        $consumer->setFetchInto(new \stdClass());
        $this->assertSame(\PDO::FETCH_INTO, $consumer->getFetchMode(), "The fetch mode wasn't INTO.");
    }

    /**
     * @expectedException Siny\Q4MBundle\Queue\Exception\ConsumerException
     */
    public function testExceptionWillOccurWhenInvokeSetFetchClass()
    {
        $consumer = new Consumer($this->getMockOfQ4MAsInvokeSetFetchMode(false));
        $consumer->setFetchClass("stdClass");
    }

    /**
     * @expectedException Siny\Q4MBundle\Queue\Exception\ConsumerException
     */
    public function testExceptionWillOccurWhenInvokeSetFetchInto()
    {
        $consumer = new Consumer($this->getMockOfQ4MAsInvokeSetFetchMode(false));
        $consumer->setFetchInto(new \stdClass());
    }

    /**
     * @dataProvider provideConsume
     */
    public function testAQueueWillReturnWhenConsuming($queue)
    {
        $consumer = new Consumer($this->getMockOfQ4MForDequeue($this->returnValue($queue)));
        $this->assertSame($queue, $consumer->consume(self::DUMMY_TABLE));
        return $consumer;
    }

    /**
     * @dataProvider provideConsume
     */
    public function testAnotherWayToGetAQueueIsGetQueueAfterConsuming($queue)
    {
        $consumer = new Consumer($this->getMockOfQ4MForDequeue($this->returnValue($queue)));
        $dequeuedQueue = $consumer->consume(self::DUMMY_TABLE);
        $this->assertSame($dequeuedQueue, $consumer->getQueue(), "The queue wasn't same.");
    }

    public function provideConsume()
    {
        return array(
            array(array('foo' => 'bar')),
        );
    }

    /**
     * @expectedException Siny\Q4MBundle\Queue\Exception\ConsumerException
     */
    public function testExceptionWillOccurWhenConsuming()
    {
        $consumer = new Consumer($this->getMockOfQ4MForDequeue($this->throwException(new \Exception())));
        $consumer->consume(self::DUMMY_TABLE);
    }

    private function getMockOfQ4MAsInvokeSetFetchMode($result)
    {
        $pdo = $this->getMock(self::Q4M_CLASS, array('setFetchMode'), array(), "", false);
        $pdo->expects($this->once())->method('setFetchMode')->will($this->returnValue($result));
        $q4m = $this->getMock(self::Q4M_CLASS, array('getPDO'), array(), "", false);
        $q4m->expects($this->once())->method('getPDO')->will($this->returnValue($pdo));

        return $q4m;
    }

    private function getMockOfQ4MForDequeue($will)
    {
        $q4m = $this->getMock(self::Q4M_CLASS, array('waitWithSingleTable', 'dequeue'), array(), "", false);
        $q4m->expects($this->once())->method('waitWithSingleTable')->will($this->returnSelf());
        $q4m->expects($this->once())->method('dequeue')->will($will);

        return $q4m;
    }
}
