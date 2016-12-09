<?php

namespace Pragma\Tests;

use Pragma\DB\DB;

class DBTest extends \PHPUnit_Extensions_Database_TestCase
{
	protected $pdo;
	protected $db;

	public function __construct()
	{
		switch (DB_CONNECTOR) {
			case 'sqlite':
				$this->db = new DB();
				$this->pdo = $this->db->getPDO();
				$this->pdo->exec('create table "testtable" (
					"id" integer NOT NULL PRIMARY KEY AUTOINCREMENT,
					"value" text NOT NULL
				);');
				break;
			case 'mysql':
				$this->markTestIncomplete('Not implemented yet.');
				break;
		}

	}

	public function getConnection()
	{
		return $this->createDefaultDBConnection($this->pdo, DB_NAME);
	}

	public function getDataSet()
	{
		return new \PHPUnit_Extensions_Database_DataSet_ArrayDataSet(array(
			'testtable' => array(
				array('id' => NULL, 'value' => 'foo'),
				array('id' => NULL, 'value' => 'bar'),
				array('id' => NULL, 'value' => 'baz'),
				array('id' => NULL, 'value' => 'xyz'),
			),
		));
	}

	public function testSingleton()
	{
		$this->assertEquals($this->db, DB::getDB(), 'DB:getDB() must returns DB instance');
	}

	public function testPDOInstance()
	{
		$this->assertInstanceOf('PDO', $this->db->getPDO(), 'DB::getPDO() must returns PDO object');
	}

	public function testQuerySelect()
	{
		$this->assertDataSetsEqual(new \PHPUnit_Extensions_Database_DataSet_ArrayDataSet(array(
			'testtable' => array(
				array('id' => 1, 'value' => 'foo'),
				array('id' => 2, 'value' => 'bar'),
				array('id' => 3, 'value' => 'baz'),
				array('id' => 4, 'value' => 'xyz'),
			),
		)), $this->getConnection()->createDataSet(), 'Pre-Condition: SELECT');

		$this->db->query('SELECT * FROM `testtable`');
		$this->db->query('SELECT * FROM `testtable` WHERE value = :val', array(':val' => array('bar', \PDO::PARAM_STR)));
		$this->db->query('SELECT * FROM `testtable` WHERE value = :val', array(':val' => array('abc', \PDO::PARAM_STR)));

		$this->assertDataSetsEqual(new \PHPUnit_Extensions_Database_DataSet_ArrayDataSet(array(
			'testtable' => array(
				array('id' => 1, 'value' => 'foo'),
				array('id' => 2, 'value' => 'bar'),
				array('id' => 3, 'value' => 'baz'),
				array('id' => 4, 'value' => 'xyz'),
			),
		)), $this->getConnection()->createDataSet(), 'SELECTs misbehave');
	}

	public function testQueryInsert()
	{
		$this->assertDataSetsEqual(new \PHPUnit_Extensions_Database_DataSet_ArrayDataSet(array(
			'testtable' => array(
				array('id' => 1, 'value' => 'foo'),
				array('id' => 2, 'value' => 'bar'),
				array('id' => 3, 'value' => 'baz'),
				array('id' => 4, 'value' => 'xyz'),
			),
		)), $this->getConnection()->createDataSet(), 'Pre-Condition: INSERT');

		$this->db->query('INSERT INTO `testtable` (`id`, `value`) VALUES (:id, :val)', array(
			':id'   => array(NULL,  \PDO::PARAM_INT),
			':val'  => array('abc', \PDO::PARAM_STR),
		));

		$this->assertDataSetsEqual(new \PHPUnit_Extensions_Database_DataSet_ArrayDataSet(array(
			'testtable' => array(
				array('id' => 1, 'value' => 'foo'),
				array('id' => 2, 'value' => 'bar'),
				array('id' => 3, 'value' => 'baz'),
				array('id' => 4, 'value' => 'xyz'),
				array('id' => 5, 'value' => 'abc'),
			),
		)), $this->getConnection()->createDataSet(), 'Insert a new value with null id');

		$this->db->query('INSERT INTO `testtable` (`id`, `value`) VALUES (:id, :val)', array(
			':id'   => array(7,     \PDO::PARAM_INT),
			':val'  => array('def', \PDO::PARAM_STR),
		));

		$this->assertDataSetsEqual(new \PHPUnit_Extensions_Database_DataSet_ArrayDataSet(array(
			'testtable' => array(
				array('id' => 1, 'value' => 'foo'),
				array('id' => 2, 'value' => 'bar'),
				array('id' => 3, 'value' => 'baz'),
				array('id' => 4, 'value' => 'xyz'),
				array('id' => 5, 'value' => 'abc'),
				array('id' => 7, 'value' => 'def'),
			),
		)), $this->getConnection()->createDataSet(), 'Insert a new value with fixed id');
	}

	public function testQueryUpdate()
	{
		$this->assertDataSetsEqual(new \PHPUnit_Extensions_Database_DataSet_ArrayDataSet(array(
			'testtable' => array(
				array('id' => 1, 'value' => 'foo'),
				array('id' => 2, 'value' => 'bar'),
				array('id' => 3, 'value' => 'baz'),
				array('id' => 4, 'value' => 'xyz'),
			),
		)), $this->getConnection()->createDataSet(), 'Pre-Condition: UPDATE');

		$this->db->query('UPDATE `testtable`  SET `value` = :val WHERE `id` = :id', array(
			':id'   => array(3,     \PDO::PARAM_INT),
			':val'  => array('abc', \PDO::PARAM_STR),
		));

		$this->assertDataSetsEqual(new \PHPUnit_Extensions_Database_DataSet_ArrayDataSet(array(
			'testtable' => array(
				array('id' => 1, 'value' => 'foo'),
				array('id' => 2, 'value' => 'bar'),
				array('id' => 3, 'value' => 'abc'),
				array('id' => 4, 'value' => 'xyz'),
			),
		)), $this->getConnection()->createDataSet(), 'Update value by id');

		$this->db->query('UPDATE `testtable`  SET `id` = :newid WHERE `id` = :oldid', array(
			':oldid' => array(2, \PDO::PARAM_INT),
			':newid' => array(6, \PDO::PARAM_INT),
		));

		$this->assertDataSetsEqual(new \PHPUnit_Extensions_Database_DataSet_ArrayDataSet(array(
			'testtable' => array(
				array('id' => 1, 'value' => 'foo'),
				array('id' => 3, 'value' => 'abc'),
				array('id' => 4, 'value' => 'xyz'),
				array('id' => 6, 'value' => 'bar'),
			),
		)), $this->getConnection()->createDataSet(), 'Update id by id');
	}

	public function testQueryDelete()
	{
		$this->assertDataSetsEqual(new \PHPUnit_Extensions_Database_DataSet_ArrayDataSet(array(
			'testtable' => array(
				array('id' => 1, 'value' => 'foo'),
				array('id' => 2, 'value' => 'bar'),
				array('id' => 3, 'value' => 'baz'),
				array('id' => 4, 'value' => 'xyz'),
			),
		)), $this->getConnection()->createDataSet(), 'Pre-Condition: DELETE');

		$this->db->query('DELETE FROM `testtable` WHERE `id` = :id', array(
			':id'   => array(1, \PDO::PARAM_INT),
		));

		$this->assertDataSetsEqual(new \PHPUnit_Extensions_Database_DataSet_ArrayDataSet(array(
			'testtable' => array(
				array('id' => 2, 'value' => 'bar'),
				array('id' => 3, 'value' => 'baz'),
				array('id' => 4, 'value' => 'xyz'),
			),
		)), $this->getConnection()->createDataSet(), 'Delete by id');

		$this->db->query('DELETE FROM `testtable` WHERE `id` IN (:id1, :id2)', array(
			':id2' => array(2, \PDO::PARAM_INT),
			':id1' => array(4, \PDO::PARAM_INT),
		));

		$this->assertDataSetsEqual(new \PHPUnit_Extensions_Database_DataSet_ArrayDataSet(array(
			'testtable' => array(
				array('id' => 3, 'value' => 'baz'),
			),
		)), $this->getConnection()->createDataSet(), 'Delete with multiple id');
	}

	public function testNumrowsSelect()
	{
		if (DB_CONNECTOR == 'sqlite') {
			$this->markTestSkipped('PDOStatement::rowCount() does not return proper value with SELECT and SQLite');
			return;
		}
	}

	public function testNumrowsInsert()
	{
		$this->assertNull($this->db->numrows(), 'No query - numrows should return null');

		$res = $this->db->query('INSERT INTO `testtable` (`id`, `value`) VALUES (:id, :val)', array(
			':id'   => array(NULL,  \PDO::PARAM_INT),
			':val'  => array('abc', \PDO::PARAM_STR),
		));

		$this->assertEquals(1, $this->db->numrows(),        'numrows after adding 1 element - implicit statement parameter');
		$this->assertEquals(1, $this->db->numrows($res),    'numrows after adding 1 element - explicit statement parameter');

		$res = $this->db->query('INSERT INTO `testtable` (`id`, `value`) VALUES (:id1, :val1), (:id2, :val2)', array(
			':id1'   => array(NULL,  \PDO::PARAM_INT),
			':val1'  => array('def', \PDO::PARAM_STR),
			':id2'   => array(NULL,  \PDO::PARAM_INT),
			':val2'  => array('ijk', \PDO::PARAM_STR),
		));

		$this->assertEquals(2, $this->db->numrows(),        'numrows after adding 2 elements - implicit statement parameter');
		$this->assertEquals(2, $this->db->numrows($res),    'numrows after adding 2 elements - explicit statement parameter');
	}

	public function testNumrowsUpdate()
	{
		$this->assertNull($this->db->numrows(), 'No query, numrows should return null');

		$this->assertDataSetsEqual(new \PHPUnit_Extensions_Database_DataSet_ArrayDataSet(array(
			'testtable' => array(
				array('id' => 1, 'value' => 'foo'),
				array('id' => 2, 'value' => 'bar'),
				array('id' => 3, 'value' => 'baz'),
				array('id' => 4, 'value' => 'xyz'),
			),
		)), $this->getConnection()->createDataSet(), 'Pre-Condition: UPDATE');

		$res = $this->db->query('UPDATE `testtable` SET value = :val WHERE id = :id', array(
			':id'   => array(1,     \PDO::PARAM_INT),
			':val'  => array('abc', \PDO::PARAM_STR),
		));

		$this->assertEquals(1, $this->db->numrows(),        'numrows after updating 1 element - implicit statement parameter');
		$this->assertEquals(1, $this->db->numrows($res),    'numrows after updating 1 element - explicit statement parameter');

		$res = $this->db->query('UPDATE `testtable` SET value = :val WHERE id IN (:id1, :id2)', array(
			':id1'  => array(3,     \PDO::PARAM_INT),
			':id2'  => array(4,     \PDO::PARAM_INT),
			':val'  => array('def', \PDO::PARAM_STR),
		));

		$this->assertEquals(2, $this->db->numrows(),        'numrows after updating 2 elements - implicit statement parameter');
		$this->assertEquals(2, $this->db->numrows($res),    'numrows after updating 2 elements - explicit statement parameter');
	}

	public function testNumrowsDelete()
	{
		$this->assertNull($this->db->numrows(), 'No query - numrows should return null');

		$this->assertDataSetsEqual(new \PHPUnit_Extensions_Database_DataSet_ArrayDataSet(array(
			'testtable' => array(
				array('id' => 1, 'value' => 'foo'),
				array('id' => 2, 'value' => 'bar'),
				array('id' => 3, 'value' => 'baz'),
				array('id' => 4, 'value' => 'xyz'),
			),
		)), $this->getConnection()->createDataSet(), 'Pre-Condition: DELETE');

		$res = $this->db->query('DELETE FROM `testtable` WHERE id = :id', array(
			':id'   => array(1,  \PDO::PARAM_INT),
		));

		$this->assertEquals(1, $this->db->numrows(),        'numrows after deleting 1 element - implicit statement parameter');
		$this->assertEquals(1, $this->db->numrows($res),    'numrows after deleting 1 element - explicit statement parameter');

		$res = $this->db->query('DELETE FROM `testtable` WHERE id IN (:id1, :id2)', array(
			':id1'  => array(3,  \PDO::PARAM_INT),
			':id2'  => array(4,  \PDO::PARAM_INT),
		));

		$this->assertEquals(2, $this->db->numrows(),        'numrows after deleting 2 elements - implicit statement parameter');
		$this->assertEquals(2, $this->db->numrows($res),    'numrows after deleting 2 elements - explicit statement parameter');
	}

	public function testFetchrow()
	{
		$this->assertNull($this->db->fetchrow(), 'No query - fetchrow should return null');

		$this->assertDataSetsEqual(new \PHPUnit_Extensions_Database_DataSet_ArrayDataSet(array(
			'testtable' => array(
				array('id' => 1, 'value' => 'foo'),
				array('id' => 2, 'value' => 'bar'),
				array('id' => 3, 'value' => 'baz'),
				array('id' => 4, 'value' => 'xyz'),
			),
		)), $this->getConnection()->createDataSet(), 'Pre-Condition: dataset');

		$this->db->query('SELECT * FROM `testtable`');

		$this->assertEquals(array(
			'id'    => '1',
			'value' => 'foo',
		), $this->db->fetchrow(), 'fetchrow first result after selecting all elements - implicit statement parameter');

		$this->assertEquals(array(
			'id'    => '2',
			'value' => 'bar',
		), $this->db->fetchrow(), 'fetchrow second result after selecting all elements - implicit statement parameter');

		$res = $this->db->query('SELECT * FROM `testtable`');

		$this->assertEquals(array(
			'id'    => '1',
			'value' => 'foo',
		), $this->db->fetchrow($res), 'fetchrow first result after selecting all elements - explicit statement parameter');

		$this->assertEquals(array(
			'id'    => '2',
			'value' => 'bar',
		), $this->db->fetchrow($res), 'fetchrow second result after selecting all elements - explicit statement parameter');

		$this->db->query('SELECT * FROM `testtable` LIMIT 1, 2');

		$this->assertEquals(array(
			'id'    => '2',
			'value' => 'bar',
		), $this->db->fetchrow(), 'fetchrow first result after selecting limited (1, 2) elements - implicit statement parameter');

		$this->assertEquals(array(
			'id'    => '3',
			'value' => 'baz',
		), $this->db->fetchrow(), 'fetchrow second result after selecting limited (1, 2) elements - implicit statement parameter');

		$res = $this->db->query('SELECT * FROM `testtable` LIMIT 1, 2');

		$this->assertEquals(array(
			'id'    => '2',
			'value' => 'bar',
		), $this->db->fetchrow($res), 'fetchrow first result after selecting limited (1, 2) elements - explicit statement parameter');

		$this->assertEquals(array(
			'id'    => '3',
			'value' => 'baz',
		), $this->db->fetchrow($res), 'fetchrow second result after selecting limited (1, 2) elements - explicit statement parameter');
	}

	public function testGetLastId()
	{
		$this->assertDataSetsEqual(new \PHPUnit_Extensions_Database_DataSet_ArrayDataSet(array(
			'testtable' => array(
				array('id' => 1, 'value' => 'foo'),
				array('id' => 2, 'value' => 'bar'),
				array('id' => 3, 'value' => 'baz'),
				array('id' => 4, 'value' => 'xyz'),
			),
		)), $this->getConnection()->createDataSet(), 'Pre-Condition: INSERT');

		$this->db->query('INSERT INTO `testtable` (`id`, `value`) VALUES (:id, :val)', array(
			':id'   => array(NULL,  \PDO::PARAM_INT),
			':val'  => array('abc', \PDO::PARAM_STR),
		));

		$this->assertEquals(5, $this->db->getLastId(), 'last ID after inserting auto increment ID element');

		$this->db->query('INSERT INTO `testtable` (`id`, `value`) VALUES (:id, :val)', array(
			':id'   => array(7,     \PDO::PARAM_INT),
			':val'  => array('def', \PDO::PARAM_STR),
		));

		$this->assertEquals(7, $this->db->getLastId(), 'last ID after inserting fixed ID element');

		// TODO: test on inserting two elements?
	}

	public function testDescribe()
	{
		// TODO: test DB::describe() method
		$this->markTestIncomplete('Not implemented yet!');
	}
}
