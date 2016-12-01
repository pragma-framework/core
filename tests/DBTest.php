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
}
