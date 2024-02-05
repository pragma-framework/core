<?php

namespace Pragma\Tests;

use Pragma\DB\DB;
use Pragma\ORM\QueryBuilder;

require_once __DIR__.'/Settings.php';

class QueryBuilderTest extends \PHPUnit\DbUnit\TestCase
{
	protected $pdo;
	protected $db;
	protected $queryBuilder;

	protected $defaultDatas = array('testtable'=>array(),'anothertable'=>array());

	protected static $escapeQuery = "`";

	public function __construct($name = null, array $data = array(), $dataName = '') {
		$this->db = DB::getDB();
		$this->pdo = $this->db->getPDO();

		if($this->db->getConnector() == DB::CONNECTOR_PGSQL){
			self::$escapeQuery = "\"";
		}
		elseif($this->db->getConnector() == DB::CONNECTOR_MSSQL){
			self::$escapeQuery = "";
		}

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
		}elseif($this->db->getConnector() == DB::CONNECTOR_PGSQL || $this->db->getConnector() == DB::CONNECTOR_MSSQL){
			$this->defaultDatas = array(
				'testtable' => array(
					array('id' => 1, 'value' => 'foo'),
					array('id' => 2, 'value' => 'bar'),
					array('id' => 3, 'value' => 'baz'),
					array('id' => 4, 'value' => 'xyz'),
				),
				'anothertable' => array(
					array('id' => 1, 'testtable_id' => '1', 'another_value' => 'aqw'),
					array('id' => 2, 'testtable_id' => '1', 'another_value' => 'zsx'),
					array('id' => 3, 'testtable_id' => '3', 'another_value' => 'edc'),
					array('id' => 4, 'testtable_id' => '4', 'another_value' => 'rfv'),
				),
			);
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
		parent::__construct($name, $data, $dataName);
	}

	protected function generateUID(){
		if(defined('ORM_UID_STRATEGY') && ORM_UID_STRATEGY == 'mysql'){
			$suid = 'UUID()';
			if(DB_CONNECTOR == 'sqlite'){
				$suid = 'LOWER(HEX(RANDOMBLOB(18)))';
			}elseif(DB_CONNECTOR == 'pgsql' || DB_CONNECTOR == 'postgresql'){
				// $this->db->query('CREATE EXTENSION IF NOT EXISTS "uuid-ossp"');
				$suid = 'uuid_generate_v4()';
				// $suid = 'gen_rANDom_uuid()';
			}elseif(DB_CONNECTOR == 'mssql'){
				$suid = 'NEWID()';
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
		return $this->createDefaultDBConnection($this->pdo, 'public');
	}

	public function getDataSet()
	{
		return $this->createArrayDataSet($this->defaultDatas);
	}

	protected function setUp()
	{
		$this->pdo->exec('DROP TABLE IF EXISTS '.self::$escapeQuery.'testtable'.self::$escapeQuery.'');
		$this->pdo->exec('DROP TABLE IF EXISTS '.self::$escapeQuery.'anothertable'.self::$escapeQuery.'');

		switch (DB_CONNECTOR) {
			case 'mysql':
			case 'mssql':
			case 'pgsql':
			case 'postgresql':
				$id = ''.self::$escapeQuery.'id'.self::$escapeQuery.' '.Settings::get_auto_increment_syntax($this->db->getConnector()).' PRIMARY KEY';
				$key = ''.self::$escapeQuery.'testtable_id'.self::$escapeQuery.'  int     NOT NULL';
				if(defined('ORM_ID_AS_UID') && ORM_ID_AS_UID){
					if(defined('ORM_UID_STRATEGY') && ORM_UID_STRATEGY == 'mysql'){
						$id = ''.self::$escapeQuery.'id'.self::$escapeQuery.' char(36) NOT NULL PRIMARY KEY';
						$key = ''.self::$escapeQuery.'testtable_id'.self::$escapeQuery.' char(36) NOT NULL';
					}else{
						$id = ''.self::$escapeQuery.'id'.self::$escapeQuery.' char(23) NOT NULL PRIMARY KEY';
						$key = ''.self::$escapeQuery.'testtable_id'.self::$escapeQuery.' char(23) NOT NULL';
					}
				}
				$this->pdo->exec('CREATE TABLE '.self::$escapeQuery.'testtable'.self::$escapeQuery.' (
					'.$id.',
					'.self::$escapeQuery.'value'.self::$escapeQuery.' text    NOT NULL
				);
				CREATE TABLE '.self::$escapeQuery.'anothertable'.self::$escapeQuery.' (
					'.$id.',
					'.$key.',
					'.self::$escapeQuery.'another_value'.self::$escapeQuery.' text    NOT NULL
				);');
				break;
			case 'sqlite':
				$id = ''.self::$escapeQuery.'id'.self::$escapeQuery.' integer NOT NULL PRIMARY KEY AUTOINCREMENT';
				$key = ''.self::$escapeQuery.'testtable_id'.self::$escapeQuery.'  integer NOT NULL';
				if(defined('ORM_ID_AS_UID') && ORM_ID_AS_UID){
					if(defined('ORM_UID_STRATEGY') && ORM_UID_STRATEGY == 'mysql'){
						$id = ''.self::$escapeQuery.'id'.self::$escapeQuery.' varchar(36) NOT NULL PRIMARY KEY';
						$key = ''.self::$escapeQuery.'testtable_id'.self::$escapeQuery.' varchar(36) NOT NULL';
					}else{
						$id = ''.self::$escapeQuery.'id'.self::$escapeQuery.' varchar(23) NOT NULL PRIMARY KEY';
						$key = ''.self::$escapeQuery.'testtable_id'.self::$escapeQuery.' varchar(23) NOT NULL';
					}
				}
				$this->pdo->exec('CREATE TABLE '.self::$escapeQuery.'testtable'.self::$escapeQuery.' (
					'.$id.',
					'.self::$escapeQuery.'value'.self::$escapeQuery.' text NOT NULL
				);
				CREATE TABLE '.self::$escapeQuery.'anothertable'.self::$escapeQuery.' (
					'.$id.',
					'.$key.',
					'.self::$escapeQuery.'another_value'.self::$escapeQuery.' text    NOT NULL
				);');
				break;
		}

		$this->queryBuilder = new QueryBuilder('testtable');
		parent::setUp();
	}
	public function tearDown(){
		$this->pdo->exec('DROP TABLE IF EXISTS '.self::$escapeQuery.'testtable'.self::$escapeQuery.'');
		$this->pdo->exec('DROP TABLE IF EXISTS '.self::$escapeQuery.'anothertable'.self::$escapeQuery.'');
		parent::tearDown();
	}

	public static function tearDownAfterClass(){
		$db = DB::getDB();
		$pdo = $db->getPDO();
		$pdo->exec('DROP TABLE IF EXISTS '.self::$escapeQuery.'testtable'.self::$escapeQuery.'');
		$pdo->exec('DROP TABLE IF EXISTS '.self::$escapeQuery.'anothertable'.self::$escapeQuery.'');
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

		$this->assertEquals('foo_table', \PHPUnit\Framework\Assert::readAttribute($queryBuilder, 'table'));

		$queryBuilder = new QueryBuilder('testtable');

		$this->assertEquals('testtable', \PHPUnit\Framework\Assert::readAttribute($queryBuilder, 'table'));
	}

	public function testSelect()
	{
		$this->queryBuilder->select();

		$this->assertEquals(['*'], \PHPUnit\Framework\Assert::readAttribute($this->queryBuilder, 'select'));

		$this->queryBuilder->select('id', 'value');

		$this->assertEquals(['id', 'value'], \PHPUnit\Framework\Assert::readAttribute($this->queryBuilder, 'select'));

		$this->queryBuilder->select(['id', 'value']);

		$this->assertEquals(['id', 'value'], \PHPUnit\Framework\Assert::readAttribute($this->queryBuilder, 'select'));
	}

	public function testSubwhere()
	{
		$this->queryBuilder->subwhere(function($queryBuilder) {
			$queryBuilder->where('id', '=', $this->defaultDatas['testtable'][1]['id']);
		});

		$this->assertEquals([[
			'subs' => [['cond' => ['id', '=', $this->defaultDatas['testtable'][1]['id']], 'bool' => 'AND', 'use_pdo' => true]],
			'bool' => 'AND'
		]], \PHPUnit\Framework\Assert::readAttribute($this->queryBuilder, 'where'));

		$this->queryBuilder->subwhere(function($queryBuilder) {
			$queryBuilder->where('foo', 'bar', 'baz', 'boo');
		}, 'booz');

		$this->assertEquals([[
			'subs' => [['cond' => ['id', '=', $this->defaultDatas['testtable'][1]['id']], 'bool' => 'AND', 'use_pdo' => true]],
			'bool' => 'AND'
		],
		[
			'subs' => [['cond' => ['foo', 'bar', 'baz'], 'bool' => 'boo', 'use_pdo' => true]],
			'bool' => 'booz'
		]], \PHPUnit\Framework\Assert::readAttribute($this->queryBuilder, 'where'));

		$this->queryBuilder->subwhere(function($queryBuilder) {
			$queryBuilder->where('value', '=', 'xyz', 'AND');
			$queryBuilder->where('id', '>', $this->defaultDatas['testtable'][1]['id'], 'OR');
		}, 'or');

		$this->assertEquals([[
			'subs' => [['cond' => ['id', '=', $this->defaultDatas['testtable'][1]['id']], 'bool' => 'AND', 'use_pdo' => true]],
			'bool' => 'AND'
		],
		[
			'subs' => [['cond' => ['foo', 'bar', 'baz'], 'bool' => 'boo', 'use_pdo' => true]],
			'bool' => 'booz'
		],
		[
			'subs' => [
				['cond' => ['value', '=', 'xyz'], 'bool' => 'AND', 'use_pdo' => true],
				['cond' => ['id', '>', $this->defaultDatas['testtable'][1]['id']], 'bool' => 'OR', 'use_pdo' => true]
			],
			'bool' => 'or'
		]], \PHPUnit\Framework\Assert::readAttribute($this->queryBuilder, 'where'));
	}

	public function testWhere()
	{
		$this->queryBuilder->where('value', '=', 'foo');

		$this->assertEquals([
			[ 'cond' => ['value', '=', 'foo'], 'bool' => 'AND', 'use_pdo' => true],
		], \PHPUnit\Framework\Assert::readAttribute($this->queryBuilder, 'where'));

		$this->queryBuilder->where('bar', 'baz', 'foo', 'boo');

		$this->assertEquals([
			[ 'cond' => ['value',   '=',    'foo'], 'bool' => 'AND', 'use_pdo' => true],
			[ 'cond' => ['bar',     'baz',  'foo'], 'bool' => 'boo', 'use_pdo' => true],
		], \PHPUnit\Framework\Assert::readAttribute($this->queryBuilder, 'where'));

		$this->queryBuilder->where('id', '>', $this->defaultDatas['testtable'][2]['id'], 'or');

		$this->assertEquals([
			[ 'cond' => ['value',   '=',    'foo'], 'bool' => 'AND', 'use_pdo' => true],
			[ 'cond' => ['bar',     'baz',  'foo'], 'bool' => 'boo', 'use_pdo' => true],
			[ 'cond' => ['id',      '>',    $this->defaultDatas['testtable'][2]['id']],   'bool' => 'or', 'use_pdo' => true],
		], \PHPUnit\Framework\Assert::readAttribute($this->queryBuilder, 'where'));
	}

	public function testOrder()
	{
		$this->queryBuilder->order('id');

		$this->assertEquals(' ORDER BY id ASC', \PHPUnit\Framework\Assert::readAttribute($this->queryBuilder, 'order'));

		$this->queryBuilder->order('foo', 'bar');

		$this->assertEquals(' ORDER BY foo BAR', \PHPUnit\Framework\Assert::readAttribute($this->queryBuilder, 'order'));

		$this->queryBuilder->order('value', 'desc');

		$this->assertEquals(' ORDER BY value DESC', \PHPUnit\Framework\Assert::readAttribute($this->queryBuilder, 'order'));

	}

	public function testGroup()
	{
		$this->queryBuilder->group('id');

		$this->assertEquals(' GROUP BY id', \PHPUnit\Framework\Assert::readAttribute($this->queryBuilder, 'group'));

		$this->queryBuilder->group('foo');

		$this->assertEquals(' GROUP BY foo', \PHPUnit\Framework\Assert::readAttribute($this->queryBuilder, 'group'));

		$this->queryBuilder->group('value');

		$this->assertEquals(' GROUP BY value', \PHPUnit\Framework\Assert::readAttribute($this->queryBuilder, 'group'));
	}

	public function testHaving()
	{
		$this->queryBuilder->having('id', '>', $this->defaultDatas['testtable'][1]['id']);

		$this->assertEquals(' HAVING id > '.$this->defaultDatas['testtable'][1]['id'], \PHPUnit\Framework\Assert::readAttribute($this->queryBuilder, 'having'));

		$this->queryBuilder->having('foo', 'bar', 'baz');

		$this->assertEquals(' HAVING foo bar baz', \PHPUnit\Framework\Assert::readAttribute($this->queryBuilder, 'having'));

		$this->queryBuilder->having('value', '=', $this->defaultDatas['testtable'][1]['id']);

		$this->assertEquals(' HAVING value = '.$this->defaultDatas['testtable'][1]['id'], \PHPUnit\Framework\Assert::readAttribute($this->queryBuilder, 'having'));
	}

	public function testLimit()
	{
		$this->queryBuilder->limit('3');

		$this->assertEquals('3', \PHPUnit\Framework\Assert::readAttribute($this->queryBuilder, 'limit'));
		$this->assertEquals('0', \PHPUnit\Framework\Assert::readAttribute($this->queryBuilder, 'limit_start'));

		$this->queryBuilder->limit('foo', 'bar');

		$this->assertEquals('foo', \PHPUnit\Framework\Assert::readAttribute($this->queryBuilder, 'limit'));
		$this->assertEquals('bar', \PHPUnit\Framework\Assert::readAttribute($this->queryBuilder, 'limit_start'));

		$this->queryBuilder->limit(2, 5);

		$this->assertEquals('2', \PHPUnit\Framework\Assert::readAttribute($this->queryBuilder, 'limit'));
		$this->assertEquals('5', \PHPUnit\Framework\Assert::readAttribute($this->queryBuilder, 'limit_start'));
	}

	public function testJoin()
	{
		$this->queryBuilder->join('anothertable', ['anothertable.testtable_id', '=', 'testtable.id']);

		$this->assertEquals([
			[ 'table' => 'anothertable', 'on' => ['anothertable.testtable_id', '=', 'testtable.id'], 'type' => 'INNER'],
		], \PHPUnit\Framework\Assert::readAttribute($this->queryBuilder, 'joins'));

		$this->queryBuilder->join('foo', ['bar', 'baz', 'abc'], 'def');

		$this->assertEquals([
			[ 'table' => 'anothertable', 'on' => ['anothertable.testtable_id', '=', 'testtable.id'], 'type' => 'INNER'],
			[ 'table' => 'foo', 'on' => ['bar', 'baz', 'abc'], 'type' => 'def'],
		], \PHPUnit\Framework\Assert::readAttribute($this->queryBuilder, 'joins'));

		$this->queryBuilder->join('anothertable', ['testtable.id', '=', 'anothertable.testtable_id'], 'left');

		$this->assertEquals([
			[ 'table' => 'anothertable', 'on' => ['anothertable.testtable_id', '=', 'testtable.id'], 'type' => 'INNER'],
			[ 'table' => 'foo', 'on' => ['bar', 'baz', 'abc'], 'type' => 'def'],
			[ 'table' => 'anothertable', 'on' => ['testtable.id', '=', 'anothertable.testtable_id'], 'type' => 'left'],
		], \PHPUnit\Framework\Assert::readAttribute($this->queryBuilder, 'joins'));
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
