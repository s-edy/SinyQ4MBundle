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

class ConsumerTest extends \PHPUnit_Extensions_Database_TestCase
{
    const Q4M_CLASS   = "Siny\Q4MBundle\Queue\Q4M";

    static private $pdo = null;

    private $connection = null;
    private $q4m;
    private $consumer;

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
        $this->q4m = new Q4M(self::$pdo);
        $this->consumer = new Consumer($this->q4m);
    }

    public function testNullReturnWhenInvokingGetQueueInTheCaseOfDefault()
    {
        $this->assertNull($this->consumer->getQueue(), "Queue wasn't null in the case of default");
    }

    public function testAQueueWillReturnWhenConsuming()
    {
        $expected = $this->getDataSet()->getIterator()->current()->getRow(0);
        $actual   = $this->consumer->consume($GLOBALS['SinyQ4MBundle_TABLE']);
        $this->assertSame($expected, $actual, "Got queue wasn't same.");
        return $this->consumer;
    }

    public function testAnotherWayToGetAQueueIsGetQueueAfterConsuming()
    {
        $expected = $this->consumer->consume($GLOBALS['SinyQ4MBundle_TABLE']);
        $this->assertSame($expected, $this->consumer->getQueue(), "The queue wasn't same.");
    }

    public function testAQueueClassWillReturnWhenConsumingWithFetchClassMode()
    {
        $fetchClass = "Siny\Q4MBundle\Tests\Queue\ConsumerTestFetchClass";
        $actual = $this->consumer->consume($GLOBALS['SinyQ4MBundle_TABLE'], \PDO::FETCH_CLASS, $fetchClass);
        $this->assertInstanceOf($fetchClass, $actual, "The queue class instance was incorrect.");
    }

    /**
     * @expectedException Siny\Q4MBundle\Queue\Exception\ConsumerException
     */
    public function testExceptionWillOccurWhenConsumingWithEmptyArguments()
    {
        $this->consumer->consume();
    }

    /**
     * @expectedException Siny\Q4MBundle\Queue\Exception\ConsumerException
     */
    public function testExceptionWillOccurWhenDequeueingFailedWhenConsuming()
    {
        $this->consumer->consume("NotExistTable");
    }

    public function testQueueWillConsumeWhenEnding()
    {
        $table = $GLOBALS['SinyQ4MBundle_TABLE'];
        $expectedCount = $this->getConnection()->getRowCount($table) - 1;
        $this->consumer->consume($table);
        $this->consumer->end();
        $actualCount = $this->getConnection()->getRowCount($table);

        $this->assertSame($expectedCount, $actualCount, "Queue wasn't consumed.");
    }

    public function testQueueWillNotConsumeWhenEndingInNotOwnerMode()
    {
        $table = $GLOBALS['SinyQ4MBundle_TABLE'];
        $expectedCount = $this->getConnection()->getRowCount($table);
        $this->consumer->end();
        $actualCount = $this->getConnection()->getRowCount($table);

        $this->assertSame($expectedCount, $actualCount, "Queue was consumed.");
    }

    /**
     * @expectedException Siny\Q4MBundle\Queue\Exception\ConsumerException
     */
    public function testExceptionWillOccurWhenEndingFailed()
    {
        $q4m = $this->getMock("Siny\Q4MBundle\Queue\Q4M", array("isModeOwner", "end"), array(self::$pdo));
        $q4m->expects($this->any())->method("isModeOwner")->will($this->returnValue(true));
        $q4m->expects($this->any())->method("end")->will($this->throwException(new \Exception()));
        $consumer = new Consumer($q4m);
        $consumer->end();
    }

    public function testQueueWillNotConsumeWhenAborting()
    {
        $table = $GLOBALS['SinyQ4MBundle_TABLE'];
        $expectedCount = $this->getConnection()->getRowCount($table);
        $this->consumer->consume($table);
        $this->consumer->abort();
        $actualCount = $this->getConnection()->getRowCount($table);

        $this->assertSame($expectedCount, $actualCount, "Queue was consumed.");
    }

    /**
     * @expectedException Siny\Q4MBundle\Queue\Exception\ConsumerException
     */
    public function testExceptionWillOccurWhenAbortingFailed()
    {
        $q4m = $this->getMock("Siny\Q4MBundle\Queue\Q4M", array("isModeOwner", "abort"), array(self::$pdo));
        $q4m->expects($this->any())->method("isModeOwner")->will($this->returnValue(true));
        $q4m->expects($this->any())->method("abort")->will($this->throwException(new \Exception()));
        $consumer = new Consumer($q4m);
        $consumer->abort();
    }
}

class ConsumerTestFetchClass {}
