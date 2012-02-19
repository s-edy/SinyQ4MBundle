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
use \PDO;
use \PDOException;
use \ReflectionProperty;

class Q4MTest extends \PHPUnit_Extensions_Database_TestCase
{
    const VERSION = '0.2.2';

    const FETCH_CLASS = "Siny\Q4MBundle\Tests\Queue\Q4MTestFetchClass";

    /**
     * PDO object
     * @var PDO
     */
    static private $pdo = null;

    /**
     * Connection
     *
     * @var PHPUnit_Extensions_Database_DB_DefaultDatabaseConnection
     */
    private $connection = null;

    /**
     * Q4M client
     *
     * @var Siny\Q4MBundle\Queue\Q4M
     */
    private $q4m;

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
                self::$pdo = new PDO($GLOBALS['SinyQ4MBundle_DSN'], $GLOBALS['SinyQ4MBundle_USER'], $GLOBALS['SinyQ4MBundle_PASSWORD']);
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
        $this->q4m = new Q4M($GLOBALS['SinyQ4MBundle_DSN'], $GLOBALS['SinyQ4MBundle_USER'], $GLOBALS['SinyQ4MBundle_PASSWORD']);
    }

    /**
     * Tear down
     *
     * @see PHPUnit_Extensions_Database_TestCase::tearDown()
     */
    public function tearDown()
    {
        parent::tearDown();
        $this->forceAbort();
    }

    /**
     * Get Q4M version
     */
    public function testGetVersion()
    {
        $this->assertSame(self::VERSION, $this->q4m->getVersion());
    }

    /**
     * Get a PDO object
     */
    public function testGetPdo()
    {
        $this->assertInstanceOf("\PDO", $this->q4m->getPDO(), "A PDO will retuen when invoking getPDO().");
    }

    /**
     * The mode in the case of default
     */
    public function testIsModeOwnerInTheCaseOfDefault()
    {
        $this->assertFalse($this->q4m->isModeOwner(), "current mode is NON-OWNER");
    }

    /**
     * Recconect
     */
    public function testRecconectingPdoConnection()
    {
        try {
            $this->q4m->reconnect();
            $this->assertTrue(true, "Connected successfully");
        } catch (\Exception $e) {
            $this->fail("Connection failed.");
        }
    }

    /**
     * The waiting table name in the case of default
     */
    public function testGetWaitingTableNameInTheCaseOfDefault()
    {
        $this->assertNull($this->q4m->getWaitingTableName(), "Returned table name wasn't null.");
    }

    /**
     * Self object will return self object when invoking enqueue
     */
    public function testSelfObjectWillReturnWhenInvokingEnqueue()
    {
        $this->assertSame($this->q4m, $this->q4m->enqueue($this->getTableName(), $this->getFixtureRow(0)), "Q4M object not returned.");
    }

    /**
     * Values will insert at specified table when invoking enqueue
     */
    public function testValuesWillInsertAtSpecifiedTableWhenInvokingEnqueue()
    {
        $expected = $this->getRowCount() + 1;
        $this->q4m->enqueue($this->getTableName(), $this->getFixtureRow(0));

        $this->assertSame($expected, $this->getRowCount(), "Did not insert new value.");
    }

    /**
     * Queueing values will insert at last row when invoking Enqueue
     */
    public function testQueueingValuesWillInsertAtLastRowWhenInvokingEnqueue()
    {
        $this->q4m->enqueue($this->getTableName(), $this->getFixtureRow(0));

        $this->assertSame($this->getFixtureRow(0), $this->getMySQLRow($this->getRowCount() - 1), "Failed to enqueue.");
    }

    /**
     * Enqueueing any parameters correctly
     *
     * @dataProvider provideEnqueueingAnyParametersCorrectly
     */
    public function testEnqueueingAnyParametersCorrectly($value, $message)
    {
        $this->q4m->enqueue($this->getTableName(), $value);

        $this->assertEquals($value, $this->getMySQLRow($this->getRowCount() - 1), $message);
    }
    public function provideEnqueueingAnyParametersCorrectly()
    {
        $value = $this->getFixtureRow(0);
        return array(
            array(array_merge($value, array('message' => 'ABC')), "String value couldn't insert."),
            array(array_merge($value, array('message' => 123)),   "Positive integer value couldn't insert."),
            array(array_merge($value, array('message' => -456)),  "Negative integer value couldn't insert."),
            array(array_merge($value, array('message' => 7.89)),  "Float value couldn't insert."),
            array(array_merge($value, array('message' => true)),  "Boolean (true) value couldn't insert."),
        );
    }

    /**
     * Mode did not change after enqueueing
     *
     * @dataProvider provideModeDidNotChangeAfterEnqueueing
     */
    public function testModeDidNotChangeAfterEnqueueing($isModeOwner)
    {
        $this->setOwnerMode($this->q4m, $isModeOwner);

        $this->q4m->enqueue($this->getTableName(), $this->getFixtureRow(0));
        $this->assertSame($isModeOwner, $this->q4m->isModeOwner(), "Mode was changed.");
    }
    public function provideModeDidNotChangeAfterEnqueueing()
    {
        return array(array(true), array(false));
    }

    /**
     * Exception will occur when gave a invalid parameter when invoking enqueue
     *
     * @dataProvider provideInvalidEnqueueParameters
     * @expectedException Siny\Q4MBundle\Queue\Exception\Q4MException
     */
    public function testExceptionWillOccurWhenGaveAInvalidParameterWhenInvokingEnqueue($value, $table = null)
    {
        if (is_null($table)) {
            $table = $this->getTableName();
        }
        $this->q4m->enqueue($table, $value);
        $this->fail("Exception wasn't occurred.");
    }
    public function provideInvalidEnqueueParameters()
    {
        $validValue = $this->getFixtureRow(0);
        return array(
            array($validValue, 'Invalid table name'), // Failed to execute SQL.
            array(array()),                           // The parameter is empty.
            array(array(array(1))),                   // The parameter must be primitive.
        );
    }

    /**
     * Exception will occur when SQL execution failed when invoking enqueue
     *
     * @expectedException Siny\Q4MBundle\Queue\Exception\Q4MException
     */
    public function testExceptionWillOccurWhenSqlExecutionFailedWhenInvokingEnqueue()
    {
        $pdo = $this->getMockOfPDOToFailExecution(array('ABCDE', 99, "Mock Error Code"));
        $q4m = $this->getMock("Siny\Q4MBundle\Queue\Q4M", array("getPDO"), array($GLOBALS['SinyQ4MBundle_DSN'], $GLOBALS['SinyQ4MBundle_USER'], $GLOBALS['SinyQ4MBundle_PASSWORD']));
        $q4m->expects($this->any())->method("getPDO")->will($this->returnValue($pdo));
        $q4m->enqueue($this->getTableName(), $this->getFixtureRow(0));
    }

    /**
     * Self object will return when invoking wait with single table
     */
    public function testSelfObjectWillReturnWhenInvokingWaitWithSingleTable()
    {
        $this->assertSame(
            $this->q4m,
            $this->q4m->waitWithSingleTable($this->getTableName()),
            "`queue_wait()` function end unsuccessfully");
    }

    /**
     * Mode will turn owner when invoking wait with single table
     */
    public function testModeWillTurnOwnerWhenInvokingWaitWithSingleTable()
    {
        $this->q4m->waitWithSingleTable($this->getTableName());

        $this->assertTrue($this->q4m->isModeOwner(), "The mode wasn't OWNER");
    }

    /**
     * Can wait again when the mode is owner, but will consume a row.
     */
    public function testCanwaitWithSingleTableAgainWhenTheModeIsOwnerButWillConsumeARow()
    {
        $expected = $this->getRowCount() - 1;

        $this->q4m->waitWithSingleTable($this->getTableName());
        $this->q4m->waitWithSingleTable($this->getTableName());
        $this->forceAbort();

        $this->assertSame($expected, $this->getRowCount(), "A row wasn't consumed.");
    }

    /**
     * Exception will occur when SQL execution failed at invoking waitWithSingleTable
     *
     * @expectedException Siny\Q4MBundle\Queue\Exception\Q4MException
     */
    public function testExceptionWillOccurWhenSqlExecutionFailedAtInvokingWaitWithSingleTable()
    {
        $pdo = $this->getMockOfPDOToFailExecution();
        $q4m = $this->getMock("Siny\Q4MBundle\Queue\Q4M", array("getPDO"), array($GLOBALS['SinyQ4MBundle_DSN'], $GLOBALS['SinyQ4MBundle_USER'], $GLOBALS['SinyQ4MBundle_PASSWORD']));
        $q4m->expects($this->any())->method("getPDO")->will($this->returnValue($pdo));
        $q4m->waitWithSingleTable($this->getTableName());
    }

    /**
     * Self object will return when invoking wait with plural tables
     */
    public function testSelfObjectWillReturnWhenInvokingWaitWithPluralTables()
    {
        $this->assertSame(
            $this->q4m,
            $this->q4m->waitWithPluralTables($this->getPluralTableNames()),
            "`queue_wait()` function end unsuccessfully");
    }

    /**
     * Mode will turn owner when invoking wait with plural tables
     */
    public function testModeWillTurnOwnerWhenInvokingWaitWithPluralTables()
    {
        $this->q4m->waitWithPluralTables($this->getPluralTableNames());

        $this->assertTrue($this->q4m->isModeOwner(), "The mode wasn't OWNER");
    }

    /**
     * Highest priority table of all tables will be set after wait with plural tables
     */
    public function testHighestPriorityTableOfAllTablesWillBeSetAfterWaitWithPluralTables()
    {
        $tables = $this->getPluralTableNames();
        $this->q4m->waitWithPluralTables($tables);

        $this->assertSame($tables[0], $this->q4m->getWaitingTableName(), "Waiting table name wasn't set correctly");
    }

    /**
     * Next High priority table will be set after wait with plural tables when the highest priority table is empty.
     */
    public function testNextHighPriorityTableWillBeSetAfterWaitWithPluralTablesWhenTheHighestPriorityTableIsEmpty()
    {
        self::$pdo->query(sprintf('TRUNCATE TABLE `%s`', $this->getTableName()));
        $tables = $this->getPluralTableNames();
        $this->q4m->waitWithPluralTables($tables);

        $this->assertSame($tables[1], $this->q4m->getWaitingTableName(), "Waiting table name wasn't set correctly");
    }

    /**
     * Exception will occur when all tables are not available when invoing wait with plural tables
     *
     * @expectedException Siny\Q4MBundle\Queue\Exception\Q4MException
     */
    public function testExceptionWillOccurWhenAllTablesAreNotAvailableWhenInvokingWaitWithPluralTables()
    {
        $tables = $this->getPluralTableNames();
        foreach ($tables as $table) {
            self::$pdo->query(sprintf('TRUNCATE TABLE `%s`', $table));
        }
        $this->q4m->waitWithPluralTables($tables);
    }

    /**
     * Exception Will Occur when invoking dequeue when the mode is not OWNER
     *
     * @expectedException Siny\Q4MBundle\Queue\Exception\Q4MException
     */
    public function testExceptionWillOccurWhenInvokingDequeueWhenTheModeIsNotOwner()
    {
        $this->q4m->dequeue();
    }

    /**
     * dequeued value is array in the case of default
     */
    public function testDequeuedValueIsArrayInTheCaseOfDefault()
    {
        $this->q4m->waitWithSingleTable($this->getTableName());

        $value = $this->q4m->dequeue();

        $this->assertInternalType('array', $value, "dequeued values is not array.");

        return $value;
    }

    /**
     * Can choice dequeued value type
     */
    public function testCanChoiceDequeuedValueType()
    {
        $this->q4m->waitWithSingleTable($this->getTableName());

        $this->assertInstanceOf('stdClass', $this->q4m->dequeue(PDO::FETCH_OBJ), "dequeued values is not stdClass class instance.");
    }

    /**
     * Can choice dequeued value type as fetching column mode
     */
    public function testCanChoiceDequeuedValueTypeAsFetchingColumnMode()
    {
        $this->q4m->waitWithSingleTable($this->getTableName());

        $queue = $this->q4m->dequeue(PDO::FETCH_COLUMN, 0);
        $row = $this->getFixtureRow(0);
        $this->assertSame($row["id"], $queue, "dequeued values is not column value.");
    }

    /**
     * Can choice dequeued value type as fetching class mode
     */
    public function testCanChoiceDequeuedValueTypeAsFetchingClassMode()
    {
        $this->q4m->waitWithSingleTable($this->getTableName());

        $queue = $this->q4m->dequeue(PDO::FETCH_CLASS, self::FETCH_CLASS, array());
        $this->assertInstanceOf(self::FETCH_CLASS, $queue, "dequeued values is not class instance for fetching.");
    }

    /**
     * Can choice dequeued value type as fetching into mode
     */
    public function testCanChoiceDequeuedValueTypeAsFetchingIntoMode()
    {
        $this->q4m->waitWithSingleTable($this->getTableName());

        $class = self::FETCH_CLASS;
        $object = new $class();
        $queue = $this->q4m->dequeue(PDO::FETCH_INTO, $object);
        $this->assertSame($object, $queue, "dequeued values is not class instance for fetching.");
    }

    /**
     * Exception will occur when setFetchMode failed when dequeueing
     *
     * @expectedException Siny\Q4MBundle\Queue\Exception\Q4MException
     */
    public function testExceptionWillOccurWhenSetFetchModeFailedWhenDequeueing()
    {
        $statement = $this->getMock('PDOStatement', array('fetch'));
        $statement->expects($this->any())->method('setFetchMode')->will($this->returnValue(false));
        $pdo = $this->getMock('PDO', array('query'), array('sqlite::memory:'));
        $pdo->expects($this->any())->method('query')->will($this->returnValue($statement));
        $q4m = $this->getMock("Siny\Q4MBundle\Queue\Q4M", array("getPDO"), array($GLOBALS['SinyQ4MBundle_DSN'], $GLOBALS['SinyQ4MBundle_USER'], $GLOBALS['SinyQ4MBundle_PASSWORD']));
        $q4m->expects($this->any())->method("getPDO")->will($this->returnValue($pdo));
        $this->setOwnerMode($q4m, true);

        $q4m->dequeue(PDO::FETCH_INTO, new \stdClass());
    }

    /**
     * The first queue will return when invoking dequeue
     *
     * @depends testDequeuedValueIsArrayInTheCaseOfDefault
     */
    public function testTheFirstQueueWillReturnWhenInvokingDequeue(array $value)
    {
        $this->assertSame($this->getFixtureRow(0), $value, "The value is not first queue.");
    }

    /**
     * Exception will occur when query execution failed when invoking dequeue.
     *
     * @dataProvider provideFailedToExecuteDequeue
     *
     * @expectedException Siny\Q4MBundle\Queue\Exception\Q4MException
     */
    public function testExceptionWillOccurWhenQueryExecutionFailedWhenInvokingDequeue(PDO $pdo)
    {
        $q4m = $this->getMock("Siny\Q4MBundle\Queue\Q4M", array("getPDO"), array($GLOBALS['SinyQ4MBundle_DSN'], $GLOBALS['SinyQ4MBundle_USER'], $GLOBALS['SinyQ4MBundle_PASSWORD']));
        $q4m->expects($this->any())->method("getPDO")->will($this->returnValue($pdo));
        $this->setOwnerMode($q4m, true);
        $q4m->dequeue();
    }
    public function provideFailedToExecuteDequeue()
    {
        $pdo = $this->getMock('PDO', array('query'), array('sqlite::memory:'));
        $pdo->expects($this->any())
            ->method('query')
            ->will($this->returnValue(false));

        return array(array($pdo));
    }

    /**
     * Exception will occur when fetch execution failed when invoking dequeue.
     *
     * @expectedException Siny\Q4MBundle\Queue\Exception\Q4MException
     */
    public function testExceptionWillOccurWhenFetchExecutionFailedWhenInvokingDequeue()
    {
        $pdo = $this->getMockOfPDOToFetchFailed();
        $q4m = $this->getMock("Siny\Q4MBundle\Queue\Q4M", array("getPDO"), array($GLOBALS['SinyQ4MBundle_DSN'], $GLOBALS['SinyQ4MBundle_USER'], $GLOBALS['SinyQ4MBundle_PASSWORD']));
        $q4m->expects($this->any())->method("getPDO")->will($this->returnValue($pdo));
        $this->setOwnerMode($q4m, true);
        $q4m->dequeue();
    }

    /**
     * Self object will return when invoking End
     */
    public function testSelfObjectWillReturnWhenInvokingEnd()
    {
        $this->assertSame($this->q4m, $this->q4m->end(), "Self object wasn't returned.");
    }

    /**
     * End function will consume a row while waiting
     */
    public function testEndFunctionWillConsumeARowWhileWaiting()
    {
        $expected = $this->getRowCount() - 1;
        $this->q4m->waitWithSingleTable($this->getTableName());
        $this->q4m->end();
        $this->assertSame($expected, $this->getRowCount(), "A row wasn't consumed.");
    }

    /**
     * End function will consume a row while not waiting
     */
    public function testEndFunctionWillConsumeARowWhileNotWaiting()
    {
        $expected = $this->getRowCount();
        $this->q4m->end();
        $this->assertSame($expected, $this->getRowCount(), "A row was consumed.");
    }

    /**
     * The mode will go out OWNER when invoking End
     */
    public function testTheModeWillGoOutOwnerWhenInvokingEnd()
    {
        $this->q4m->waitWithSingleTable($this->getTableName());
        $this->assertTrue($this->q4m->isModeOwner(), "The mode wasn't OWNER");
        $this->q4m->end();
        $this->assertFalse($this->q4m->isModeOwner(), "The mode wasn' NON-OWNER");
    }

    /**
     * Exception will occur when fetching failed when invoking end
     *
     * @expectedException Siny\Q4MBundle\Queue\Exception\Q4MException
     */
    public function testExceptionWillOccurWhenFetchingFailedWhenInvokingEnd()
    {
        $pdo = $this->getMockOfPDOToFetchFailed();
        $q4m = $this->getMock("Siny\Q4MBundle\Queue\Q4M", array("getPDO"), array($GLOBALS['SinyQ4MBundle_DSN'], $GLOBALS['SinyQ4MBundle_USER'], $GLOBALS['SinyQ4MBundle_PASSWORD']));
        $q4m->expects($this->any())->method("getPDO")->will($this->returnValue($pdo));
        $q4m->end();
    }

    /**
     * Exception will occur when incorrect response received when invoking end
     *
     * @expectedException Siny\Q4MBundle\Queue\Exception\Q4MException
     */
    public function testExceptionWillOccurWhenIncorrectResponseWhenInvokingEnd()
    {
        $pdo = $this->getMockOfPDOWhichReturnIncorrectResponse(array('queue_end()' => "0"));
        $q4m = $this->getMock("Siny\Q4MBundle\Queue\Q4M", array("getPDO"), array($GLOBALS['SinyQ4MBundle_DSN'], $GLOBALS['SinyQ4MBundle_USER'], $GLOBALS['SinyQ4MBundle_PASSWORD']));
        $q4m->expects($this->any())->method("getPDO")->will($this->returnValue($pdo));
        $q4m->end();
    }

    /**
     * Exception will occur when invoking abort while the mode is not OWNER.
     *
     * @expectedException Siny\Q4MBundle\Queue\Exception\Q4MException
     */
    public function testExceptionWillOccurWhenInvokingAbortWhileTheModeIsNotOwner()
    {
        $this->q4m->abort();
    }

    /**
     * Self object will return when invoking Abort
     */
    public function testSelfObjectWillReturnWhenInvokingAbort()
    {
        $this->q4m->waitWithSingleTable($this->getTableName());
        $this->assertSame($this->q4m, $this->q4m->abort(), "Self object wasn't returned.");
    }

    /**
     * Abort function will not consume any row
     */
    public function testAbortFunctionWillNotConsumeAnyRow()
    {
        $expected = $this->getRowCount();
        $this->q4m->waitWithSingleTable($this->getTableName());
        $this->q4m->abort();
        $this->assertSame($expected, $this->getRowCount(), "A row was consumed.");
    }

    /**
     * The mode will go out OWNER when invoking Abort
     */
    public function testTheModeWillGoOutOwnerWhenInvokingAbort()
    {
        $this->q4m->waitWithSingleTable($this->getTableName());
        $this->assertTrue($this->q4m->isModeOwner(), "The mode wasn't OWNER");
        $this->q4m->abort();
        $this->assertFalse($this->q4m->isModeOwner(), "The mode wasn' NON-OWNER");
    }

    /**
     * Exception will occur when fetching failed when invoking abort
     *
     * @expectedException Siny\Q4MBundle\Queue\Exception\Q4MException
     */
    public function testExceptionWillOccurWhenFetchingFailedWhenInvokingAbort()
    {
        $pdo = $this->getMockOfPDOToFetchFailed();
        $q4m = $this->getMock("Siny\Q4MBundle\Queue\Q4M", array("getPDO"), array($GLOBALS['SinyQ4MBundle_DSN'], $GLOBALS['SinyQ4MBundle_USER'], $GLOBALS['SinyQ4MBundle_PASSWORD']));
        $q4m->expects($this->any())->method("getPDO")->will($this->returnValue($pdo));
        $q4m->abort();
    }

    /**
     * Exception will occur when incorrect response received when invoking abort
     *
     * @expectedException Siny\Q4MBundle\Queue\Exception\Q4MException
     */
    public function testExceptionWillOccurWhenIncorrectResponseWhenInvokingAbort()
    {
        $pdo = $this->getMockOfPDOWhichReturnIncorrectResponse(array('queue_end()' => "0"));
        $q4m = $this->getMock("Siny\Q4MBundle\Queue\Q4M", array("getPDO"), array($GLOBALS['SinyQ4MBundle_DSN'], $GLOBALS['SinyQ4MBundle_USER'], $GLOBALS['SinyQ4MBundle_PASSWORD']));
        $q4m->expects($this->any())->method("getPDO")->will($this->returnValue($pdo));
        $q4m->abort();
    }

    /**
     * Force abort
     */
    private function forceAbort()
    {
        try {
            self::$pdo->query('SELECT queue_abort()');
        } catch (PDOException $e) {
            //nothing to do
        }
    }

    /**
     * Get fixture row
     *
     * @param integer $index
     * @return array
     */
    private function getFixtureRow($index)
    {
        return $this->getDataSet()->getIterator()->current()->getRow($index);
    }

    /**
     * Get row count
     *
     * @return integer
     */
    private function getRowCount()
    {
        return $this->getConnection()->getRowCount($this->getTableName());
    }

    /**
     * Get MySQL row
     *
     * @param integer $index
     * @return array
     */
    private function getMySQLRow($index)
    {
        $statement = self::$pdo->prepare(sprintf("SELECT * FROM %s LIMIT 1 OFFSET :offset", $this->getTableName()));
        $statement->bindValue(':offset', $index, PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get table name
     *
     * @return string
     */
    private function getTableName()
    {
        return $GLOBALS['SinyQ4MBundle_TABLE'];
    }

    /**
     * Get prioritized multiple table names
     */
    private function getPluralTableNames()
    {
        return array($this->getTableName(), $GLOBALS['SinyQ4MBundle_LOW_PRIORITY_TABLE']);
    }

    /**
     * Get mock of PDO to fail execution
     */
    private function getMockOfPDOToFailExecution(array $message = array('ABCDE', 99, "Mock Error Code"))
    {
        $statement = $this->getMock('PDOStatement', array('bindValue', 'execute', 'errorInfo'));
        $statement->expects($this->any())->method('bindValue')->will($this->returnValue(true));
        $statement->expects($this->any())->method('execute')->will($this->returnValue(false));
        $statement->expects($this->any())->method('errorInfo')->will($this->returnValue($message));

        $pdo = $this->getMock('PDO', array('prepare', 'query'), array('sqlite::memory:'));
        $pdo->expects($this->any())->method('prepare')->will($this->returnValue($statement));
        $pdo->expects($this->any())->method('query')->will($this->returnValue(false));

        return $pdo;
    }

    private function getMockOfPDOToFetchFailed()
    {
        $statement = $this->getMock('PDOStatement', array('fetch'));
        $statement->expects($this->any())->method('fetch')->will($this->returnValue(false));
        $pdo = $this->getMock('PDO', array('query'), array('sqlite::memory:'));
        $pdo->expects($this->any())->method('query')->will($this->returnValue($statement));
        return $pdo;
    }

    private function getMockOfPDOWhichReturnIncorrectResponse($response)
    {
        $statement = $this->getMock('PDOStatement', array('fetch'));
        $statement->expects($this->any())->method('fetch')->will($this->returnValue($response));
        $pdo = $this->getMock('PDO', array('query'), array('sqlite::memory:'));
        $pdo->expects($this->any())->method('query')->will($this->returnValue($statement));
        return $pdo;
    }

    /**
     * Get mock of PDO to fail query
     */
    private function getMockOfPDOToFailQuery()
    {
        $pdo = $this->getMock('PDO', array('query'), array('sqlite::memory:'));
        $pdo->expects($this->any())->method('query')->will($this->returnValue(false));
        return $pdo;
    }

    private function setOwnerMode($q4m, $isModeOwner)
    {
        $reflection = new ReflectionProperty('Siny\Q4MBundle\Queue\Q4M', 'isModeOwner');
        $reflection->setAccessible(true);
        $reflection->setValue($q4m, $isModeOwner);
    }
}

class Q4MTestFetchClass {}
