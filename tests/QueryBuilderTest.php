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
		$this->db = DB::getDB();
		$this->pdo = $this->db->getPDO();
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
			'anothertable' => array(
				array('id' => NULL, 'testtable_id' => '1', 'another_value' => 'aqw'),
				array('id' => NULL, 'testtable_id' => '1', 'another_value' => 'zsx'),
				array('id' => NULL, 'testtable_id' => '3', 'another_value' => 'edc'),
				array('id' => NULL, 'testtable_id' => '4', 'another_value' => 'rfv'),
			),
		));
	}

	protected function setUp()
	{
		$this->pdo->exec('DROP TABLE IF EXISTS `testtable`');
		$this->pdo->exec('DROP TABLE IF EXISTS `anothertable`');

		switch (DB_CONNECTOR) {
			case 'mysql':
				$this->pdo->exec('CREATE TABLE `testtable` (
					`id`    int     NOT NULL AUTO_INCREMENT PRIMARY KEY,
					`value` text    NOT NULL
				);
				CREATE TABLE `anothertable` (
					`id`            int     NOT NULL AUTO_INCREMENT PRIMARY KEY,
					`testtable_id`  int     NOT NULL,
					`another_value` text    NOT NULL
				);');
				break;
			case 'sqlite':
				$this->pdo->exec('CREATE TABLE `testtable` (
					`id` integer NOT NULL PRIMARY KEY AUTOINCREMENT,
					`value` text NOT NULL
				);
				CREATE TABLE `anothertable` (
					`id`            integer NOT NULL PRIMARY KEY AUTOINCREMENT,
					`testtable_id`  integer NOT NULL,
					`another_value` text    NOT NULL
				);');
				break;
		}

		$this->queryBuilder = new QueryBuilder('testtable');
		parent::setUp();
	}

	/* Test functions */
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
		$this->queryBuilder->subwhere(function($queryBuilder) {
			$queryBuilder->where('id', '=', '2');
		});

		$this->assertEquals([[
			'subs' => [['cond' => ['id', '=', '2'], 'bool' => 'and']],
			'bool' => 'and'
		]], \PHPUnit_Framework_Assert::readAttribute($this->queryBuilder, 'where'));

		$this->queryBuilder->subwhere(function($queryBuilder) {
			$queryBuilder->where('foo', 'bar', 'baz', 'boo');
		}, 'booz');

		$this->assertEquals([[
			'subs' => [['cond' => ['id', '=', '2'], 'bool' => 'and']],
			'bool' => 'and'
		],
		[
			'subs' => [['cond' => ['foo', 'bar', 'baz'], 'bool' => 'boo']],
			'bool' => 'booz'
		]], \PHPUnit_Framework_Assert::readAttribute($this->queryBuilder, 'where'));

		$this->queryBuilder->subwhere(function($queryBuilder) {
			$queryBuilder->where('value', '=', 'xyz', 'AND');
			$queryBuilder->where('id', '>', '2', 'OR');
		}, 'or');

		$this->assertEquals([[
			'subs' => [['cond' => ['id', '=', '2'], 'bool' => 'and']],
			'bool' => 'and'
		],
		[
			'subs' => [['cond' => ['foo', 'bar', 'baz'], 'bool' => 'boo']],
			'bool' => 'booz'
		],
		[
			'subs' => [
				['cond' => ['value', '=', 'xyz'], 'bool' => 'AND'],
				['cond' => ['id', '>', '2'], 'bool' => 'OR']
			],
			'bool' => 'or'
		]], \PHPUnit_Framework_Assert::readAttribute($this->queryBuilder, 'where'));
	}

	public function testWhere()
	{
		$this->queryBuilder->where('value', '=', 'foo');

		$this->assertEquals([
			[ 'cond' => ['value', '=', 'foo'], 'bool' => 'and'],
		], \PHPUnit_Framework_Assert::readAttribute($this->queryBuilder, 'where'));

		$this->queryBuilder->where('bar', 'baz', 'foo', 'boo');

		$this->assertEquals([
			[ 'cond' => ['value',   '=',    'foo'], 'bool' => 'and'],
			[ 'cond' => ['bar',     'baz',  'foo'], 'bool' => 'boo'],
		], \PHPUnit_Framework_Assert::readAttribute($this->queryBuilder, 'where'));

		$this->queryBuilder->where('id', '>', '3', 'or');

		$this->assertEquals([
			[ 'cond' => ['value',   '=',    'foo'], 'bool' => 'and'],
			[ 'cond' => ['bar',     'baz',  'foo'], 'bool' => 'boo'],
			[ 'cond' => ['id',      '>',    '3'],   'bool' => 'or'],
		], \PHPUnit_Framework_Assert::readAttribute($this->queryBuilder, 'where'));
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

	public function testJoin()
	{
		$this->queryBuilder->join('anothertable', ['anothertable.testtable_id', '=', 'testtable.id']);

		$this->assertEquals([
			[ 'table' => 'anothertable', 'on' => ['anothertable.testtable_id', '=', 'testtable.id'], 'type' => 'inner'],
		], \PHPUnit_Framework_Assert::readAttribute($this->queryBuilder, 'joins'));

		$this->queryBuilder->join('foo', ['bar', 'baz', 'abc'], 'def');

		$this->assertEquals([
			[ 'table' => 'anothertable', 'on' => ['anothertable.testtable_id', '=', 'testtable.id'], 'type' => 'inner'],
			[ 'table' => 'foo', 'on' => ['bar', 'baz', 'abc'], 'type' => 'def'],
		], \PHPUnit_Framework_Assert::readAttribute($this->queryBuilder, 'joins'));

		$this->queryBuilder->join('anothertable', ['testtable.id', '=', 'anothertable.testtable_id'], 'left');

		$this->assertEquals([
			[ 'table' => 'anothertable', 'on' => ['anothertable.testtable_id', '=', 'testtable.id'], 'type' => 'inner'],
			[ 'table' => 'foo', 'on' => ['bar', 'baz', 'abc'], 'type' => 'def'],
			[ 'table' => 'anothertable', 'on' => ['testtable.id', '=', 'anothertable.testtable_id'], 'type' => 'left'],
		], \PHPUnit_Framework_Assert::readAttribute($this->queryBuilder, 'joins'));
	}

	public function testBareGetArrays()
	{
		$this->assertEquals(array(
			array('id' => 1, 'value' => 'foo'),
			array('id' => 2, 'value' => 'bar'),
			array('id' => 3, 'value' => 'baz'),
			array('id' => 4, 'value' => 'xyz'),
		), $this->queryBuilder->get_arrays());
	}

	public function testSelectGetArrays()
	{
		$this->queryBuilder->select(['*']);

		$this->assertEquals(array(
			array('id' => 1, 'value' => 'foo'),
			array('id' => 2, 'value' => 'bar'),
			array('id' => 3, 'value' => 'baz'),
			array('id' => 4, 'value' => 'xyz'),
		), $this->queryBuilder->get_arrays());

		$this->queryBuilder->select(['value']);

		$this->assertEquals(array(
			array('value' => 'foo'),
			array('value' => 'bar'),
			array('value' => 'baz'),
			array('value' => 'xyz'),
		), $this->queryBuilder->get_arrays());

		$this->queryBuilder->select(['id']);

		$this->assertEquals(array(
			array('id' => 1),
			array('id' => 2),
			array('id' => 3),
			array('id' => 4),
		), $this->queryBuilder->get_arrays());
	}
}
