<?php

namespace Pragma\Tests;

use Pragma\DB\DB;
use Pragma\ORM\QueryBuilder;

class QueryBuilderTest extends \PHPUnit_Extensions_Database_TestCase
{
	protected $pdo;
	protected $db;
	protected $queryBuilder;

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

	protected function setUp()
	{
		$this->queryBuilder = new QueryBuilder('testtable');
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
	}

	public function testSelect()
	{
		$this->queryBuilder->select();

		$this->assertEquals(['*'], \PHPUnit_Framework_Assert::readAttribute($this->queryBuilder, 'select'));

		$this->queryBuilder->select('id', 'value');

		$this->assertEquals(['id', 'value'], \PHPUnit_Framework_Assert::readAttribute($this->queryBuilder, 'select'));

		$this->queryBuilder->select(['id', 'value']);

		$this->assertEquals(['id', 'value'], \PHPUnit_Framework_Assert::readAttribute($this->queryBuilder, 'select'));
	}

	public function testSubwhere()
	{
		$this->markTestIncomplete('Not implemented yet - Not sure how this should be tested.');
	}

	public function testWhere()
	{
		$this->markTestIncomplete('Not implemented yet.');
	}

	public function testOrder()
	{
		$this->queryBuilder->order('id');

		$this->assertEquals(' ORDER BY id asc', \PHPUnit_Framework_Assert::readAttribute($this->queryBuilder, 'order'));

		$this->queryBuilder->order('foo', 'bar');

		$this->assertEquals(' ORDER BY foo bar', \PHPUnit_Framework_Assert::readAttribute($this->queryBuilder, 'order'));

		$this->queryBuilder->order('value', 'desc');

		$this->assertEquals(' ORDER BY value desc', \PHPUnit_Framework_Assert::readAttribute($this->queryBuilder, 'order'));

	}

	public function testGroup()
	{
		$this->queryBuilder->group('id');

		$this->assertEquals(' GROUP BY id', \PHPUnit_Framework_Assert::readAttribute($this->queryBuilder, 'group'));

		$this->queryBuilder->group('foo');

		$this->assertEquals(' GROUP BY foo', \PHPUnit_Framework_Assert::readAttribute($this->queryBuilder, 'group'));

		$this->queryBuilder->group('value');

		$this->assertEquals(' GROUP BY value', \PHPUnit_Framework_Assert::readAttribute($this->queryBuilder, 'group'));
	}

	public function testHaving()
	{
		$this->queryBuilder->having('id', '>', 2);

		$this->assertEquals(' HAVING id > 2', \PHPUnit_Framework_Assert::readAttribute($this->queryBuilder, 'having'));

		$this->queryBuilder->having('foo', 'bar', 'baz');

		$this->assertEquals(' HAVING foo bar baz', \PHPUnit_Framework_Assert::readAttribute($this->queryBuilder, 'having'));

		$this->queryBuilder->having('value', '=', 2);

		$this->assertEquals(' HAVING value = 2', \PHPUnit_Framework_Assert::readAttribute($this->queryBuilder, 'having'));
	}

	public function testLimit()
	{
		$this->queryBuilder->limit('3');

		$this->assertEquals(' LIMIT 0, 3', \PHPUnit_Framework_Assert::readAttribute($this->queryBuilder, 'limit'));

		$this->queryBuilder->limit('foo', 'bar');

		$this->assertEquals(' LIMIT bar, foo', \PHPUnit_Framework_Assert::readAttribute($this->queryBuilder, 'limit'));

		$this->queryBuilder->limit(2, 5);

		$this->assertEquals(' LIMIT 5, 2', \PHPUnit_Framework_Assert::readAttribute($this->queryBuilder, 'limit'));
	}
}
