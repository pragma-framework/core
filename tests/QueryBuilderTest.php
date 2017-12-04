<?php

namespace Pragma\Tests;

use Pragma\DB\DB;
use Pragma\ORM\QueryBuilder;

class QueryBuilderTest extends \PHPUnit_Extensions_Database_TestCase
{
	protected $pdo;
	protected $db;
	protected $queryBuilder;

	protected $defaultDatas = array('testtable'=>array(),'anothertable'=>array());

	public function __construct()
	{
		$this->db = DB::getDB();
		$this->pdo = $this->db->getPDO();

		if(defined('ORM_ID_AS_UID') && ORM_ID_AS_UID){
			$values = array('foo', 'bar', 'baz', 'xyz');
			foreach($values as $v){
				$this->defaultDatas['testtable'][] = array('id' => $this->generateUID(), 'value' => $v);
			}

			$this->defaultDatas['anothertable'][] = array('id' => $this->generateUID(), 'testtable_id' => $this->defaultDatas['testtable'][0]['id'], 'another_value' => 'aqw');
			$this->defaultDatas['anothertable'][] = array('id' => $this->generateUID(), 'testtable_id' => $this->defaultDatas['testtable'][0]['id'], 'another_value' => 'zsx');
			$this->defaultDatas['anothertable'][] = array('id' => $this->generateUID(), 'testtable_id' => $this->defaultDatas['testtable'][2]['id'], 'another_value' => 'edc');
			$this->defaultDatas['anothertable'][] = array('id' => $this->generateUID(), 'testtable_id' => $this->defaultDatas['testtable'][3]['id'], 'another_value' => 'rfv');

			$this->defaultDatas['testtable'] = self::sortArrayValuesById($this->defaultDatas['testtable']);
			$this->defaultDatas['anothertable'] = self::sortArrayValuesById($this->defaultDatas['anothertable']);
		}else{
			$this->defaultDatas = array(
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
			);
		}
	}

	protected function generateUID(){
		if(defined('ORM_UID_STRATEGY') && ORM_UID_STRATEGY == 'mysql'){
			$suid = 'UUID()';
			if(DB_CONNECTOR == 'sqlite'){
				$suid = 'LOWER(HEX(RANDOMBLOB(18)))';
			}
			$uuidRS = $this->db->query('SELECT '.$suid.' as uuid');
			$uuidRes = $this->db->fetchrow($uuidRS);
			$uid = $uuidRes['uuid'];
		}else{
			$uid = uniqid('',true);
		}
		return $uid;
	}

	protected static function sortArrayValuesById(&$values = array()){
		if(defined('ORM_ID_AS_UID') && ORM_ID_AS_UID){
			usort($values,function($a, $b){
				return strcmp($a['id'], $b['id']);
			});
		}else{
			usort($values,function($a, $b){
				return $a['id'] > $b['id'];
			});
		}
		return $values;
	}

	public function getConnection()
	{
		return $this->createDefaultDBConnection($this->pdo, DB_NAME);
	}

	public function getDataSet()
	{
		return new \PHPUnit_Extensions_Database_DataSet_ArrayDataSet($this->defaultDatas);
	}

	protected function setUp()
	{
		$this->pdo->exec('DROP TABLE IF EXISTS `testtable`');
		$this->pdo->exec('DROP TABLE IF EXISTS `anothertable`');

		switch (DB_CONNECTOR) {
			case 'mysql':
				$id = '`id` int NOT NULL AUTO_INCREMENT PRIMARY KEY';
				$key = '`testtable_id`  int     NOT NULL';
				if(defined('ORM_ID_AS_UID') && ORM_ID_AS_UID){
					if(defined('ORM_UID_STRATEGY') && ORM_UID_STRATEGY == 'mysql'){
						$id = '`id` char(36) NOT NULL PRIMARY KEY';
						$key = '`testtable_id` char(36) NOT NULL';
					}else{
						$id = '`id` char(23) NOT NULL PRIMARY KEY';
						$key = '`testtable_id` char(23) NOT NULL';
					}
				}
				$this->pdo->exec('CREATE TABLE `testtable` (
					'.$id.',
					`value` text    NOT NULL
				);
				CREATE TABLE `anothertable` (
					'.$id.',
					'.$key.',
					`another_value` text    NOT NULL
				);');
				break;
			case 'sqlite':
				$id = '`id` integer NOT NULL PRIMARY KEY AUTOINCREMENT';
				$key = '`testtable_id`  integer NOT NULL';
				if(defined('ORM_ID_AS_UID') && ORM_ID_AS_UID){
					if(defined('ORM_UID_STRATEGY') && ORM_UID_STRATEGY == 'mysql'){
						$id = '`id` varchar(36) NOT NULL PRIMARY KEY';
						$key = '`testtable_id` varchar(36) NOT NULL';
					}else{
						$id = '`id` varchar(23) NOT NULL PRIMARY KEY';
						$key = '`testtable_id` varchar(23) NOT NULL';
					}
				}
				$this->pdo->exec('CREATE TABLE `testtable` (
					'.$id.',
					`value` text NOT NULL
				);
				CREATE TABLE `anothertable` (
					'.$id.',
					'.$key.',
					`another_value` text    NOT NULL
				);');
				break;
		}

		$this->queryBuilder = new QueryBuilder('testtable');
		parent::setUp();
	}

	public static function tearDownAfterClass(){
		$db = DB::getDB();
		$pdo = $db->getPDO();
		$pdo->exec('DROP TABLE IF EXISTS `testtable`');
		$pdo->exec('DROP TABLE IF EXISTS `anothertable`');
		parent::tearDownAfterClass();
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
			$queryBuilder->where('id', '=', $this->defaultDatas['testtable'][1]['id']);
		});

		$this->assertEquals([[
			'subs' => [['cond' => ['id', '=', $this->defaultDatas['testtable'][1]['id']], 'bool' => 'and']],
			'bool' => 'and'
		]], \PHPUnit_Framework_Assert::readAttribute($this->queryBuilder, 'where'));

		$this->queryBuilder->subwhere(function($queryBuilder) {
			$queryBuilder->where('foo', 'bar', 'baz', 'boo');
		}, 'booz');

		$this->assertEquals([[
			'subs' => [['cond' => ['id', '=', $this->defaultDatas['testtable'][1]['id']], 'bool' => 'and']],
			'bool' => 'and'
		],
		[
			'subs' => [['cond' => ['foo', 'bar', 'baz'], 'bool' => 'boo']],
			'bool' => 'booz'
		]], \PHPUnit_Framework_Assert::readAttribute($this->queryBuilder, 'where'));

		$this->queryBuilder->subwhere(function($queryBuilder) {
			$queryBuilder->where('value', '=', 'xyz', 'AND');
			$queryBuilder->where('id', '>', $this->defaultDatas['testtable'][1]['id'], 'OR');
		}, 'or');

		$this->assertEquals([[
			'subs' => [['cond' => ['id', '=', $this->defaultDatas['testtable'][1]['id']], 'bool' => 'and']],
			'bool' => 'and'
		],
		[
			'subs' => [['cond' => ['foo', 'bar', 'baz'], 'bool' => 'boo']],
			'bool' => 'booz'
		],
		[
			'subs' => [
				['cond' => ['value', '=', 'xyz'], 'bool' => 'AND'],
				['cond' => ['id', '>', $this->defaultDatas['testtable'][1]['id']], 'bool' => 'OR']
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

		$this->queryBuilder->where('id', '>', $this->defaultDatas['testtable'][2]['id'], 'or');

		$this->assertEquals([
			[ 'cond' => ['value',   '=',    'foo'], 'bool' => 'and'],
			[ 'cond' => ['bar',     'baz',  'foo'], 'bool' => 'boo'],
			[ 'cond' => ['id',      '>',    $this->defaultDatas['testtable'][2]['id']],   'bool' => 'or'],
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
		$this->queryBuilder->having('id', '>', $this->defaultDatas['testtable'][1]['id']);

		$this->assertEquals(' HAVING id > '.$this->defaultDatas['testtable'][1]['id'], \PHPUnit_Framework_Assert::readAttribute($this->queryBuilder, 'having'));

		$this->queryBuilder->having('foo', 'bar', 'baz');

		$this->assertEquals(' HAVING foo bar baz', \PHPUnit_Framework_Assert::readAttribute($this->queryBuilder, 'having'));

		$this->queryBuilder->having('value', '=', $this->defaultDatas['testtable'][1]['id']);

		$this->assertEquals(' HAVING value = '.$this->defaultDatas['testtable'][1]['id'], \PHPUnit_Framework_Assert::readAttribute($this->queryBuilder, 'having'));
	}

	public function testLimit()
	{
		$this->queryBuilder->limit('3');

		$this->assertEquals('3', \PHPUnit_Framework_Assert::readAttribute($this->queryBuilder, 'limit'));
		$this->assertEquals('0', \PHPUnit_Framework_Assert::readAttribute($this->queryBuilder, 'limit_start'));

		$this->queryBuilder->limit('foo', 'bar');

		$this->assertEquals('foo', \PHPUnit_Framework_Assert::readAttribute($this->queryBuilder, 'limit'));
		$this->assertEquals('bar', \PHPUnit_Framework_Assert::readAttribute($this->queryBuilder, 'limit_start'));

		$this->queryBuilder->limit(2, 5);

		$this->assertEquals('2', \PHPUnit_Framework_Assert::readAttribute($this->queryBuilder, 'limit'));
		$this->assertEquals('5', \PHPUnit_Framework_Assert::readAttribute($this->queryBuilder, 'limit_start'));
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
		$testtable = $this->defaultDatas['testtable'];
		if(defined('ORM_ID_AS_UID') && ORM_ID_AS_UID){
			// do nothing
		}else{
			foreach($testtable as $i => &$v){
				$v['id'] = $i+1;
			}
		}
		$this->assertEquals($testtable, $this->queryBuilder->get_arrays(null,false,false));
	}

	public function testSelectGetArrays()
	{
		$this->queryBuilder->select(['*']);

		$testtable = $this->defaultDatas['testtable'];
		if(defined('ORM_ID_AS_UID') && ORM_ID_AS_UID){
			// do nothing
		}else{
			$testtable[0]['id'] = 1;
			$testtable[1]['id'] = 2;
			$testtable[2]['id'] = 3;
			$testtable[3]['id'] = 4;
		}
		$this->assertEquals($testtable, $this->queryBuilder->get_arrays());

		$this->queryBuilder->select(['value']);

		$values = array();
		foreach($testtable as $v){
			$values[] = array('value' => $v['value']);
		}

		$this->assertEquals($values, $this->queryBuilder->get_arrays());

		$this->queryBuilder->select(['id']);

		$ids = array();
		foreach($testtable as $v){
			$ids[] = array('id' => $v['id']);
		}

		$this->assertEquals($ids, $this->queryBuilder->get_arrays());

		$this->queryBuilder->select();

		$this->assertEquals($testtable, $this->queryBuilder->get_arrays());
	}
}
