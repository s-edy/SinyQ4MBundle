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

class SubscriberTest extends \PHPUnit_Extensions_Database_TestCase
{
    const Q4M_CLASS   = "Siny\Q4MBundle\Queue\Q4M";

    static private $pdo = null;

    private $connection = null;
    private $q4m;
    private $subscriber;

    /**
     * Get connection
     *
     * @see PHPUnit_Extensions_Database_TestCase::getConnection()
     *
     * @return PHPUnit_Extensions_Database_DB_DefaultDatabaseConnection
     */
    public function getConnection()
    {
        if (is_null($this->connection)) {
            if (is_null(self::$pdo)) {
                self::$pdo = new \PDO($GLOBALS['SinyQ4MBundle_DSN'], $GLOBALS['SinyQ4MBundle_USER'], $GLOBALS['SinyQ4MBundle_PASSWORD']);
            }
            $this->connection = $this->createDefaultDBConnection(self::$pdo, $GLOBALS['SinyQ4MBundle_DBNAME']);
        }
        return $this->connection;
    }

    /**
     * Get data set
     *
     * @see PHPUnit_Extensions_Database_TestCase::getDataSet()
     *
     * @return PHPUnit_Extensions_Database_DataSet_IDataSet
     */
    public function getDataSet()
    {
        return $this->createXmlDataSet(__DIR__ . DIRECTORY_SEPARATOR . 'Q4MFixture.xml');
    }

    /**
     * Set up Q4M client
     */
    public function setUp()
    {
        parent::setUp();
        $this->q4m = new Q4m($GLOBALS['SinyQ4MBundle_DSN'], $GLOBALS['SinyQ4MBundle_USER'], $GLOBALS['SinyQ4MBundle_PASSWORD']);
        $this->subscriber = new Subscriber($this->q4m);
    }

    /**
     * Get Q4M instance
     */
    public function testGetQ4M()
    {
        $this->assertSame($this->q4m, $this->subscriber->getQ4M(), "Q4M class instance wasn't returned.");
    }

    /**
     * Self object will return when subscribing
     *
     * @dataProvider provideSubscribing
     */
    public function testSelfObjectWillReturnWhenSubscribing(array $queue)
    {
        $this->assertSame($this->subscriber, $this->subscriber->subscribe($GLOBALS['SinyQ4MBundle_TABLE'], $queue), "Self object will return");
    }

    /**
     * Subscribed queue was enqueued
     *
     * @dataProvider provideSubscribing
     */
    public function testSubscribedQueueWasEnqueued(array $queue)
    {
        $this->subscriber->subscribe($GLOBALS['SinyQ4MBundle_TABLE'], $queue);

        $row = $this->getMySQLRow($this->getConnection()->getRowCount($GLOBALS['SinyQ4MBundle_TABLE']) - 1);
        $this->assertSame($queue['id'], intval($row['id']));
        $this->assertSame($queue['message'], $row['message']);
        $this->assertSame($queue['priority'], intval($row['priority']));
    }

    public function provideSubscribing()
    {
        return array(
            array(array('id' => 99, 'message' => 'hello, world', 'priority' => 5)),
        );
    }

    /**
     * Exception will occur when subscribing with empty array
     *
     * @expectedException Siny\Q4MBundle\Queue\Exception\SubscriberException
     */
    public function testExceptionWillOccurWhenSubscribingWithEmptyArray()
    {
        $this->subscriber->subscribe($GLOBALS['SinyQ4MBundle_TABLE'], array());
    }

    /**
     * Exception will occur when subscribing
     *
     * @expectedException Siny\Q4MBundle\Queue\Exception\SubscriberException
     */
    public function testExceptionWillOccurWhenSubscribing()
    {
        $q4m = $this->getMock(self::Q4M_CLASS, array('enqueue'), array($GLOBALS['SinyQ4MBundle_DSN'], $GLOBALS['SinyQ4MBundle_USER'], $GLOBALS['SinyQ4MBundle_PASSWORD']));
        $q4m->expects($this->once())->method('enqueue')->will($this->throwException(new \Exception()));
        $subscriber = new Subscriber($q4m);
        $subscriber->subscribe($GLOBALS['SinyQ4MBundle_TABLE'], array('foo' => 'bar'));
    }

    /**
     * Get MySQL row
     *
     * @param integer $index
     * @return array
     */
    private function getMySQLRow($index)
    {
        $pdo = new \PDO($GLOBALS['SinyQ4MBundle_DSN'], $GLOBALS['SinyQ4MBundle_USER'], $GLOBALS['SinyQ4MBundle_PASSWORD']);
        $statement = $pdo->prepare(sprintf("SELECT * FROM %s LIMIT 1 OFFSET :offset", $GLOBALS["SinyQ4MBundle_TABLE"]));
        $statement->bindValue(':offset', $index, \PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetch(\PDO::FETCH_ASSOC);
    }
}
