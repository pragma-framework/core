<?php

namespace Pragma\Tests;

use Pragma\DB\DB;
use Pragma\ORM\QueryBuilder;

class QueryBuilderTest extends \PHPUnit_Extensions_Database_TestCase
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

	public function testForge()
	{
		// XXX: QueryBuilder should be abstract, no?
		$this->markTestSkipped('Can\'t test QueryBuilder::forge without inheritance.');
	}

	public function testConstruct()
	{
		$queryBuilder = new QueryBuilder('foo_table');

		$this->assertEquals('foo_table', \PHPUnit_Framework_Assert::readAttribute($queryBuilder, 'table'));

		$queryBuilder = new QueryBuilder('testtable');

		$this->assertEquals('testtable', \PHPUnit_Framework_Assert::readAttribute($queryBuilder, 'table'));

		return $queryBuilder;
	}

	/**
	 * @depends testConstruct
	 */
	public function testSelect(QueryBuilder $queryBuilder)
	{
		$queryBuilder->select();

		$this->assertEquals(['*'], \PHPUnit_Framework_Assert::readAttribute($queryBuilder, 'select'));

		$queryBuilder->select('id', 'value');

		$this->assertEquals(['id', 'value'], \PHPUnit_Framework_Assert::readAttribute($queryBuilder, 'select'));

		$queryBuilder->select(['id', 'value']);

		$this->assertEquals(['id', 'value'], \PHPUnit_Framework_Assert::readAttribute($queryBuilder, 'select'));

		return $queryBuilder;
	}

	public function testSubwhere()
	{
		$this->markTestIncomplete('Not implemented yet - Not sure how this should be tested.');
	}

	public function testWhere()
	{
		$this->markTestIncomplete('Not implemented yet.');
	}

	/**
	 * @depends testSelect
	 */
	public function testOrder(QueryBuilder $queryBuilder)
	{
		$queryBuilder->order('id');

		$this->assertEquals(' ORDER BY id asc', \PHPUnit_Framework_Assert::readAttribute($queryBuilder, 'order'));

		$queryBuilder->order('foo', 'bar');

		$this->assertEquals(' ORDER BY foo bar', \PHPUnit_Framework_Assert::readAttribute($queryBuilder, 'order'));

		$queryBuilder->order('value', 'desc');

		$this->assertEquals(' ORDER BY value desc', \PHPUnit_Framework_Assert::readAttribute($queryBuilder, 'order'));

		return $queryBuilder;
	}
}
