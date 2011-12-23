<?php
/**
 * This file is a part of Siny\Q4MBundle package.
*
* (c) Shinichiro Yuki <edy@siny.jp>
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace Siny\Q4MBundle\Tests\API;

use Siny\Q4MBundle\Queue\Q4M;
use \PDO;
use \PDOException;

class Q4MTest extends \PHPUnit_Extensions_Database_TestCase
{
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
        $this->q4m = new Q4M(self::$pdo);
    }

    public function tearDown()
    {
        parent::tearDown();
        $this->forceAbort();
    }

    /**
     * Get a PDO object
     */
    public function testGetPDO()
    {
        $this->assertSame(self::$pdo, $this->q4m->getPDO(), "A PDO will retuen when invoking getPDO().");
    }

    /**
     * Is mode owner in the case of default
     */
    public function testIsModeOwnerInTheCaseOfDefault()
    {
        $this->assertFalse($this->q4m->isModeOwner(), "current mode is NON-OWNER");
    }

    /**
     * Self object will return when invoking wait on single table
     */
    public function testSelfObjectWillReturnWhenInvokingWaitOnSingleTable()
    {
        $this->assertSame(
            $this->q4m,
            $this->q4m->waitOnSingleTable($this->getTableName()),
            "`queue_wait()` function end unsuccessfully");
    }

    /**
     * Mode will turn owner when invoking wait on single table
     */
    public function testModeWillTurnOwnerWhenInvokingWaitOnSingleTable()
    {
        $this->q4m->waitOnSingleTable($this->getTableName());

        $this->assertTrue($this->q4m->isModeOwner(), "current mode is OWNER");
    }

    /**
     * Self object will return self object when invoking enqueue
     */
    public function testSelfObjectWillReturnWhenInvokingEnqueue()
    {
        $this->assertSame(
            $this->q4m,
            $this->q4m->enqueue($this->getTableName(), $this->getFixtureRow(0)),
            "Q4M object not returned.");
    }

    /**
     * Values will insert at specified table when invoking enqueue
     */
    public function testValuesWillInsertAtSpecifiedTableWhenInvokingEnqueue()
    {
        $count = $this->getRowCount();
        $this->q4m->enqueue($this->getTableName(), $this->getFixtureRow(0));

        $this->assertSame(
            $count + 1,
            $this->getRowCount(),
            "Did not insert new value.");
    }

    /**
     * Queueing values will insert at last row when invoking Enqueue
     */
    public function testQueueingValuesWillInsertAtLastRowWhenInvokingEnqueue()
    {
        $this->q4m->enqueue($this->getTableName(), $this->getFixtureRow(0));

        $this->assertSame(
            $this->getFixtureRow(0),
            $this->getMySQLRow($this->getRowCount() - 1),
            "Failed to enqueue.");
    }

    /**
     * Dequeue
     */
    public function testDequeue()
    {
        $this->q4m->waitOnSingleTable($this->getTableName());
        $this->assertSame($this->getFixtureRow(0), $this->q4m->dequeue(), "Failed to dequeue.");
    }

    /**
     * End
     */
    public function testEnd()
    {
        $this->q4m->waitOnSingleTable($this->getTableName());
        $this->assertSame($this->q4m, $this->q4m->end());
        $this->assertSame(3, $this->getRowCount(), "a row wasn't consumed.");
        $this->assertFalse($this->q4m->isModeOwner(), "current mode is NON-OWNER");
    }

    /**
     * Abort
     */
    public function testAbort()
    {
        $this->q4m->waitOnSingleTable($this->getTableName());
        $this->assertSame($this->q4m, $this->q4m->abort());
        $this->assertFalse($this->q4m->isModeOwner(), "current mode is NON-OWNER");
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
}
