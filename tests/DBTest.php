<?php

namespace Pragma\Tests;

use Pragma\DB\DB;

class DBTest extends \PHPUnit_Extensions_Database_TestCase
{
	protected $pdo;
	protected $db;

	protected $defaultDatas = array();

	protected static $escapeQuery = "`";

	public function __construct()
	{
		$this->db = DB::getDB();
		$this->pdo = $this->db->getPDO();

		if($this->db->getConnector() == DB::CONNECTOR_PGSQL){
			self::$escapeQuery = "\"";
		}

		if(defined('ORM_ID_AS_UID') && ORM_ID_AS_UID){
			if(defined('ORM_UID_STRATEGY') && ORM_UID_STRATEGY == 'mysql'){
				$values = array('foo', 'bar', 'baz', 'xyz');
				$suid = 'UUID()';
				if(DB_CONNECTOR == 'sqlite'){
					$suid = 'LOWER(HEX(RANDOMBLOB(18)))';
				}elseif(DB_CONNECTOR == 'pgsql' || DB_CONNECTOR == 'postgresql'){
					$suid = 'gen_random_uuid()';
				}
				foreach($values as $v){
					$uuidRS = $this->db->query('SELECT '.$suid.' as uuid');
					$uuidRes = $this->db->fetchrow($uuidRS);
					$this->defaultDatas[] = array('id' => $uuidRes['uuid'], 'value' => $v, 'other' => NULL, 'third' => 4);
				}
			}else{
				$this->defaultDatas = array(
					array('id' => uniqid('',true), 'value' => 'foo', 'other' => NULL, 'third' => 4),
					array('id' => uniqid('',true), 'value' => 'bar', 'other' => NULL, 'third' => 4),
					array('id' => uniqid('',true), 'value' => 'baz', 'other' => NULL, 'third' => 4),
					array('id' => uniqid('',true), 'value' => 'xyz', 'other' => NULL, 'third' => 4),
				);
			}
			$this->defaultDatas = self::sortArrayValuesById($this->defaultDatas);
		}else{
			$this->defaultDatas = array(
				array('id' => 1, 'value' => 'foo', 'other' => NULL, 'third' => 4),
				array('id' => 2, 'value' => 'bar', 'other' => NULL, 'third' => 4),
				array('id' => 3, 'value' => 'baz', 'other' => NULL, 'third' => 4),
				array('id' => 4, 'value' => 'xyz', 'other' => NULL, 'third' => 4),
			);
		}
	}

	protected function generateUID(){
		if(defined('ORM_UID_STRATEGY') && ORM_UID_STRATEGY == 'mysql'){
			$suid = 'UUID()';
			if(DB_CONNECTOR == 'sqlite'){
				$suid = 'LOWER(HEX(RANDOMBLOB(18)))';
			}elseif(DB_CONNECTOR == 'pgsql' || DB_CONNECTOR == 'postgresql'){
				$suid = 'gen_random_uuid()';
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
		$testtable = $this->defaultDatas;
		// if(defined('ORM_ID_AS_UID') && ORM_ID_AS_UID){
		// }elseif($this->db->getConnector() == DB::CONNECTOR_PGSQL){
		// 	foreach($testtable as &$t){
		// 		unset($t['id']);
		// 	}
		// }else{
		// 	foreach($testtable as &$t){
		// 		$t['id'] = null;
		// 	}
		// }
		return new \PHPUnit_Extensions_Database_DataSet_ArrayDataSet(array('testtable' => $testtable));
	}

	public function setUp()
	{
		$this->pdo->exec('DROP TABLE IF EXISTS '.self::$escapeQuery.'testtable'.self::$escapeQuery.'');

		switch (DB_CONNECTOR) {
			case 'mysql':
			case 'pgsql':
			case 'postgresql':
				$id = ''.self::$escapeQuery.'id'.self::$escapeQuery.' '.($this->db->getConnector()==DB::CONNECTOR_PGSQL?'SERIAL':'int NOT NULL AUTO_INCREMENT').' PRIMARY KEY';
				if(defined('ORM_ID_AS_UID') && ORM_ID_AS_UID){
					if(defined('ORM_UID_STRATEGY') && ORM_UID_STRATEGY == 'mysql'){
						$id = ''.self::$escapeQuery.'id'.self::$escapeQuery.' char(36) NOT NULL PRIMARY KEY';
					}else{
						$id = ''.self::$escapeQuery.'id'.self::$escapeQuery.' char(23) NOT NULL PRIMARY KEY';
					}
				}
				$this->pdo->exec('CREATE TABLE '.self::$escapeQuery.'testtable'.self::$escapeQuery.' (
					'.$id.',
					'.self::$escapeQuery.'value'.self::$escapeQuery.' text    NOT NULL,
					'.self::$escapeQuery.'other'.self::$escapeQuery.' text    NULL,
					'.self::$escapeQuery.'third'.self::$escapeQuery.' int     NULL DEFAULT 4
				);');
				break;
			case 'sqlite':
				$id = ''.self::$escapeQuery.'id'.self::$escapeQuery.' integer NOT NULL PRIMARY KEY AUTOINCREMENT';
				if(defined('ORM_ID_AS_UID') && ORM_ID_AS_UID){
					if(defined('ORM_UID_STRATEGY') && ORM_UID_STRATEGY == 'mysql'){
						$id = ''.self::$escapeQuery.'id'.self::$escapeQuery.' varchar(36) NOT NULL PRIMARY KEY';
					}else{
						$id = ''.self::$escapeQuery.'id'.self::$escapeQuery.' varchar(23) NOT NULL PRIMARY KEY';
					}
				}
				$this->pdo->exec('CREATE TABLE  '.self::$escapeQuery.'testtable'.self::$escapeQuery.' (
					'.$id.',
					'.self::$escapeQuery.'value'.self::$escapeQuery.' text NOT NULL,
					'.self::$escapeQuery.'other'.self::$escapeQuery.' text NULL,
					'.self::$escapeQuery.'third'.self::$escapeQuery.' int  NULL DEFAULT 4
				);');
				break;
		}

		parent::setUp();
	}

	public static function tearDownAfterClass(){
		$db = DB::getDB();
		$pdo = $db->getPDO();
		$pdo->exec('DROP TABLE IF EXISTS '.self::$escapeQuery.'testtable'.self::$escapeQuery.'');
		parent::tearDownAfterClass();
	}

	/* Test functions */
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
		$this->assertDataSetsEqual($this->getDataSet(), $this->getConnection()->createDataSet(), 'Pre-Condition: SELECT');

		$this->db->query('SELECT * FROM '.self::$escapeQuery.'testtable'.self::$escapeQuery.' ORDER BY id');
		$this->db->query('SELECT * FROM '.self::$escapeQuery.'testtable'.self::$escapeQuery.' WHERE value = :val', array(':val' => array('bar', \PDO::PARAM_STR)));
		$this->db->query('SELECT * FROM '.self::$escapeQuery.'testtable'.self::$escapeQuery.' WHERE value = :val', array(':val' => array('abc', \PDO::PARAM_STR)));

		$this->assertDataSetsEqual($this->getDataSet(), $this->getConnection()->createDataSet(), 'SELECTs misbehave');
	}

	public function testQueryInsert()
	{
		$this->assertDataSetsEqual($this->getDataSet(), $this->getConnection()->createDataSet(), 'Pre-Condition: INSERT');

		$testtable = $this->defaultDatas;
		if(defined('ORM_ID_AS_UID') && ORM_ID_AS_UID){
			// $uid = $this->generateUID();
			// $testtable[] = array('id' => $uid, 'value' => 'abc', 'other' => NULL, 'third' => 4);
			// $this->db->query('INSERT INTO '.self::$escapeQuery.'testtable'.self::$escapeQuery.' ('.self::$escapeQuery.'id'.self::$escapeQuery.', '.self::$escapeQuery.'value'.self::$escapeQuery.') VALUES (:id, :val)', array(
			// 	':id'   => array($uid,  \PDO::PARAM_STR),
			// 	':val'  => array('abc', \PDO::PARAM_STR),
			// ));
			$this->markTestSkipped('UUID can\'t be generated with NULL value');
		}else{
			$testtable[] = array('id' => 5, 'value' => 'abc', 'other' => NULL, 'third' => 4);
			$this->db->query('INSERT INTO '.self::$escapeQuery.'testtable'.self::$escapeQuery.' ('.self::$escapeQuery.'id'.self::$escapeQuery.', '.self::$escapeQuery.'value'.self::$escapeQuery.') VALUES (:id, :val)', array(
				':id'   => array(NULL,  \PDO::PARAM_INT),
				':val'  => array('abc', \PDO::PARAM_STR),
			));
			$this->assertDataSetsEqual(new \PHPUnit_Extensions_Database_DataSet_ArrayDataSet(array(
				'testtable' => $testtable,
			)), $this->getConnection()->createDataSet(), 'Insert a new value with null id');
		}

		if(defined('ORM_ID_AS_UID') && ORM_ID_AS_UID){
			$uid = $this->generateUID();
			$testtable[] = array('id' => $uid, 'value' => 'def', 'other' => NULL, 'third' => 4);
			$this->db->query('INSERT INTO '.self::$escapeQuery.'testtable'.self::$escapeQuery.' ('.self::$escapeQuery.'id'.self::$escapeQuery.', '.self::$escapeQuery.'value'.self::$escapeQuery.') VALUES (:id, :val)', array(
				':id'   => array($uid,  \PDO::PARAM_STR),
				':val'  => array('def', \PDO::PARAM_STR),
			));
		}else{
			$testtable[] = array('id' => 7, 'value' => 'def', 'other' => NULL, 'third' => 4);
			$this->db->query('INSERT INTO '.self::$escapeQuery.'testtable'.self::$escapeQuery.' ('.self::$escapeQuery.'id'.self::$escapeQuery.', '.self::$escapeQuery.'value'.self::$escapeQuery.') VALUES (:id, :val)', array(
				':id'   => array(7,  \PDO::PARAM_INT),
				':val'  => array('def', \PDO::PARAM_STR),
			));
		}

		$this->assertDataSetsEqual(new \PHPUnit_Extensions_Database_DataSet_ArrayDataSet(array(
			'testtable' => $testtable,
		)), $this->getConnection()->createDataSet(), 'Insert a new value with fixed id');
	}

	public function testQueryUpdate()
	{
		$this->assertDataSetsEqual(new \PHPUnit_Extensions_Database_DataSet_ArrayDataSet(array(
			'testtable' => $this->defaultDatas,
		)), $this->getConnection()->createDataSet(), 'Pre-Condition: UPDATE');

		$testtable = $this->defaultDatas;
		if(defined('ORM_ID_AS_UID') && ORM_ID_AS_UID){
			$this->db->query('UPDATE '.self::$escapeQuery.'testtable'.self::$escapeQuery.'  SET '.self::$escapeQuery.'value'.self::$escapeQuery.' = :val WHERE '.self::$escapeQuery.'id'.self::$escapeQuery.' = :id', array(
				':id'   => array($testtable[2]['id'], 	\PDO::PARAM_STR),
				':val'  => array('abc', 				\PDO::PARAM_STR),
			));
		}else{
			$this->db->query('UPDATE '.self::$escapeQuery.'testtable'.self::$escapeQuery.'  SET '.self::$escapeQuery.'value'.self::$escapeQuery.' = :val WHERE '.self::$escapeQuery.'id'.self::$escapeQuery.' = :id', array(
				':id'   => array(3,     \PDO::PARAM_INT), // $testtable[2]['id']
				':val'  => array('abc', \PDO::PARAM_STR),
			));
		}
		$testtable[2]['value'] = 'abc';

		$this->assertDataSetsEqual(new \PHPUnit_Extensions_Database_DataSet_ArrayDataSet(array(
			'testtable' => $testtable,
		)), $this->getConnection()->createDataSet(), 'Update value by id');

		if(defined('ORM_ID_AS_UID') && ORM_ID_AS_UID){
			$uid = $this->generateUID();
			$this->db->query('UPDATE '.self::$escapeQuery.'testtable'.self::$escapeQuery.'  SET '.self::$escapeQuery.'id'.self::$escapeQuery.' = :newid WHERE '.self::$escapeQuery.'id'.self::$escapeQuery.' = :oldid', array(
				':oldid' => array($testtable[1]['id'], \PDO::PARAM_STR),
				':newid' => array($uid, \PDO::PARAM_STR),
			));
			$testtable[1]['id'] = $uid;
		}else{
			$this->db->query('UPDATE '.self::$escapeQuery.'testtable'.self::$escapeQuery.'  SET '.self::$escapeQuery.'id'.self::$escapeQuery.' = :newid WHERE '.self::$escapeQuery.'id'.self::$escapeQuery.' = :oldid', array(
				':oldid' => array(2, \PDO::PARAM_INT),
				':newid' => array(6, \PDO::PARAM_INT),
			));
			$testtable[1]['id'] = 6;
		}

		self::sortArrayValuesById($testtable);
		$this->assertDataSetsEqual(new \PHPUnit_Extensions_Database_DataSet_ArrayDataSet(array(
			'testtable' => $testtable,
		)), $this->getConnection()->createDataSet(), 'Update id by id');
	}

	public function testQueryDelete()
	{
		$this->assertDataSetsEqual(new \PHPUnit_Extensions_Database_DataSet_ArrayDataSet(array(
			'testtable' => $this->defaultDatas,
		)), $this->getConnection()->createDataSet(), 'Pre-Condition: DELETE');

		$testtable = $this->defaultDatas;
		if(defined('ORM_ID_AS_UID') && ORM_ID_AS_UID){
			$this->db->query('DELETE FROM '.self::$escapeQuery.'testtable'.self::$escapeQuery.' WHERE '.self::$escapeQuery.'id'.self::$escapeQuery.' = :id', array(
				':id'   => array($testtable[0]['id'], \PDO::PARAM_STR),
			));
		}else{
			$this->db->query('DELETE FROM '.self::$escapeQuery.'testtable'.self::$escapeQuery.' WHERE '.self::$escapeQuery.'id'.self::$escapeQuery.' = :id', array(
				':id'   => array(1, \PDO::PARAM_INT), // $testtable[0]['id']
			));
		}
		unset($testtable[0]);

		self::sortArrayValuesById($testtable);
		$this->assertDataSetsEqual(new \PHPUnit_Extensions_Database_DataSet_ArrayDataSet(array(
			'testtable' => $testtable,
		)), $this->getConnection()->createDataSet(), 'Delete by id');

		if(defined('ORM_ID_AS_UID') && ORM_ID_AS_UID){
			$this->db->query('DELETE FROM '.self::$escapeQuery.'testtable'.self::$escapeQuery.' WHERE '.self::$escapeQuery.'id'.self::$escapeQuery.' IN (:id1, :id2)', array(
				':id2' => array($testtable[0]['id'], \PDO::PARAM_STR),
				':id1' => array($testtable[2]['id'], \PDO::PARAM_STR),
			));
		}else{
			$this->db->query('DELETE FROM '.self::$escapeQuery.'testtable'.self::$escapeQuery.' WHERE '.self::$escapeQuery.'id'.self::$escapeQuery.' IN (:id1, :id2)', array(
				':id2' => array(2, \PDO::PARAM_INT), // $testtable[0]['id']
				':id1' => array(4, \PDO::PARAM_INT), // $testtable[2]['id']
			));
		}
		unset($testtable[0],$testtable[2]);
		self::sortArrayValuesById($testtable);

		$this->assertDataSetsEqual(new \PHPUnit_Extensions_Database_DataSet_ArrayDataSet(array(
			'testtable' => $testtable,
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
		if(defined('ORM_ID_AS_UID') && ORM_ID_AS_UID){
			$uid = $this->generateUID();

			$res = $this->db->query('INSERT INTO '.self::$escapeQuery.'testtable'.self::$escapeQuery.' ('.self::$escapeQuery.'id'.self::$escapeQuery.', '.self::$escapeQuery.'value'.self::$escapeQuery.') VALUES (:id, :val)', array(
				':id'   => array($uid,  \PDO::PARAM_STR),
				':val'  => array('abc', \PDO::PARAM_STR),
			));
		}elseif($this->db->getConnector() == DB::CONNECTOR_PGSQL){
			$res = $this->db->query('INSERT INTO '.self::$escapeQuery.'testtable'.self::$escapeQuery.' ('.self::$escapeQuery.'id'.self::$escapeQuery.', '.self::$escapeQuery.'value'.self::$escapeQuery.') VALUES (DEFAULT, :val)', array(
				':val'  => array('abc', \PDO::PARAM_STR),
			));
		}else{
			$res = $this->db->query('INSERT INTO '.self::$escapeQuery.'testtable'.self::$escapeQuery.' ('.self::$escapeQuery.'id'.self::$escapeQuery.', '.self::$escapeQuery.'value'.self::$escapeQuery.') VALUES (:id, :val)', array(
				':id'   => array(NULL,  \PDO::PARAM_INT),
				':val'  => array('abc', \PDO::PARAM_STR),
			));
		}

		$this->assertEquals(1, $this->db->numrows(),        'numrows after adding 1 element - implicit statement parameter');
		$this->assertEquals(1, $this->db->numrows($res),    'numrows after adding 1 element - explicit statement parameter');

		if(defined('ORM_ID_AS_UID') && ORM_ID_AS_UID){
			$uid = $this->generateUID();
			$uid2 = $this->generateUID();
			$res = $this->db->query('INSERT INTO '.self::$escapeQuery.'testtable'.self::$escapeQuery.' ('.self::$escapeQuery.'id'.self::$escapeQuery.', '.self::$escapeQuery.'value'.self::$escapeQuery.') VALUES (:id1, :val1), (:id2, :val2)', array(
				':id1'   => array($uid,  \PDO::PARAM_STR),
				':val1'  => array('def', \PDO::PARAM_STR),
				':id2'   => array($uid2,  \PDO::PARAM_STR),
				':val2'  => array('ijk', \PDO::PARAM_STR),
			));
		}elseif($this->db->getConnector() == DB::CONNECTOR_PGSQL){
			$res = $this->db->query('INSERT INTO '.self::$escapeQuery.'testtable'.self::$escapeQuery.' ('.self::$escapeQuery.'id'.self::$escapeQuery.', '.self::$escapeQuery.'value'.self::$escapeQuery.') VALUES (DEFAULT, :val1), (DEFAULT, :val2)', array(
				':val1'  => array('def', \PDO::PARAM_STR),
				':val2'  => array('ijk', \PDO::PARAM_STR),
			));
		}else{
			$res = $this->db->query('INSERT INTO '.self::$escapeQuery.'testtable'.self::$escapeQuery.' ('.self::$escapeQuery.'id'.self::$escapeQuery.', '.self::$escapeQuery.'value'.self::$escapeQuery.') VALUES (:id1, :val1), (:id2, :val2)', array(
				':id1'   => array(NULL,  \PDO::PARAM_INT),
				':val1'  => array('def', \PDO::PARAM_STR),
				':id2'   => array(NULL,  \PDO::PARAM_INT),
				':val2'  => array('ijk', \PDO::PARAM_STR),
			));
		}

		$this->assertEquals(2, $this->db->numrows(),        'numrows after adding 2 elements - implicit statement parameter');
		$this->assertEquals(2, $this->db->numrows($res),    'numrows after adding 2 elements - explicit statement parameter');
	}

	public function testNumrowsUpdate()
	{
		$this->assertDataSetsEqual(new \PHPUnit_Extensions_Database_DataSet_ArrayDataSet(array(
			'testtable' => $this->defaultDatas,
		)), $this->getConnection()->createDataSet(), 'Pre-Condition: UPDATE');

		if(defined('ORM_ID_AS_UID') && ORM_ID_AS_UID){
			$res = $this->db->query('UPDATE '.self::$escapeQuery.'testtable'.self::$escapeQuery.' SET value = :val WHERE id = :id', array(
				':id'   => array($this->defaultDatas[0]['id'],     \PDO::PARAM_STR),
				':val'  => array('abc', \PDO::PARAM_STR),
			));
		}else{
			$res = $this->db->query('UPDATE '.self::$escapeQuery.'testtable'.self::$escapeQuery.' SET value = :val WHERE id = :id', array(
				':id'   => array($this->defaultDatas[0]['id'],     \PDO::PARAM_INT),
				':val'  => array('abc', \PDO::PARAM_STR),
			));
		}

		$this->assertEquals(1, $this->db->numrows(),        'numrows after updating 1 element - implicit statement parameter');
		$this->assertEquals(1, $this->db->numrows($res),    'numrows after updating 1 element - explicit statement parameter');

		if(defined('ORM_ID_AS_UID') && ORM_ID_AS_UID){
			$res = $this->db->query('UPDATE '.self::$escapeQuery.'testtable'.self::$escapeQuery.' SET value = :val WHERE id IN (:id1, :id2)', array(
				':id1'  => array($this->defaultDatas[2]['id'],     \PDO::PARAM_STR),
				':id2'  => array($this->defaultDatas[3]['id'],     \PDO::PARAM_STR),
				':val'  => array('def', \PDO::PARAM_STR),
			));
		}else{
			$res = $this->db->query('UPDATE '.self::$escapeQuery.'testtable'.self::$escapeQuery.' SET value = :val WHERE id IN (:id1, :id2)', array(
				':id1'  => array($this->defaultDatas[2]['id'],     \PDO::PARAM_INT),
				':id2'  => array($this->defaultDatas[3]['id'],     \PDO::PARAM_INT),
				':val'  => array('def', \PDO::PARAM_STR),
			));
		}

		$this->assertEquals(2, $this->db->numrows(),        'numrows after updating 2 elements - implicit statement parameter');
		$this->assertEquals(2, $this->db->numrows($res),    'numrows after updating 2 elements - explicit statement parameter');
	}

	public function testNumrowsDelete()
	{
		$this->assertDataSetsEqual(new \PHPUnit_Extensions_Database_DataSet_ArrayDataSet(array(
			'testtable' => $this->defaultDatas,
		)), $this->getConnection()->createDataSet(), 'Pre-Condition: DELETE');

		if(defined('ORM_ID_AS_UID') && ORM_ID_AS_UID){
			$res = $this->db->query('DELETE FROM '.self::$escapeQuery.'testtable'.self::$escapeQuery.' WHERE id = :id', array(
				':id'   => array($this->defaultDatas[0]['id'],  \PDO::PARAM_STR),
			));
		}else{
			$res = $this->db->query('DELETE FROM '.self::$escapeQuery.'testtable'.self::$escapeQuery.' WHERE id = :id', array(
				':id'   => array($this->defaultDatas[0]['id'],  \PDO::PARAM_INT),
			));
		}

		$this->assertEquals(1, $this->db->numrows(),        'numrows after deleting 1 element - implicit statement parameter');
		$this->assertEquals(1, $this->db->numrows($res),    'numrows after deleting 1 element - explicit statement parameter');

		if(defined('ORM_ID_AS_UID') && ORM_ID_AS_UID){
			$res = $this->db->query('DELETE FROM '.self::$escapeQuery.'testtable'.self::$escapeQuery.' WHERE id IN (:id1, :id2)', array(
				':id1'  => array($this->defaultDatas[2]['id'],  \PDO::PARAM_STR),
				':id2'  => array($this->defaultDatas[3]['id'],  \PDO::PARAM_STR),
			));
		}else{
			$res = $this->db->query('DELETE FROM '.self::$escapeQuery.'testtable'.self::$escapeQuery.' WHERE id IN (:id1, :id2)', array(
				':id1'  => array($this->defaultDatas[2]['id'],  \PDO::PARAM_INT),
				':id2'  => array($this->defaultDatas[3]['id'],  \PDO::PARAM_INT),
			));
		}

		$this->assertEquals(2, $this->db->numrows(),        'numrows after deleting 2 elements - implicit statement parameter');
		$this->assertEquals(2, $this->db->numrows($res),    'numrows after deleting 2 elements - explicit statement parameter');
	}

	public function testFetchrow()
	{
		$this->assertDataSetsEqual(new \PHPUnit_Extensions_Database_DataSet_ArrayDataSet(array(
			'testtable' => $this->defaultDatas,
		)), $this->getConnection()->createDataSet(), 'Pre-Condition: dataset');

		$this->db->query('SELECT * FROM '.self::$escapeQuery.'testtable'.self::$escapeQuery.' ORDER BY id');

		$this->assertEquals($this->defaultDatas[0], $this->db->fetchrow(), 'fetchrow first result after selecting all elements - implicit statement parameter');

		$this->assertEquals($this->defaultDatas[1], $this->db->fetchrow(), 'fetchrow second result after selecting all elements - implicit statement parameter');

		$this->assertEquals($this->defaultDatas[2], $this->db->fetchrow(), 'fetchrow third result after selecting all elements - implicit statement parameter');

		$this->assertEquals($this->defaultDatas[3], $this->db->fetchrow(), 'fetchrow fourth result after selecting all elements - implicit statement parameter');

		$this->assertFalse($this->db->fetchrow(), 'fetchrow one more result after selecting all elements - implicit statement parameter');

		$res = $this->db->query('SELECT * FROM '.self::$escapeQuery.'testtable'.self::$escapeQuery.' ORDER BY id');

		$this->assertEquals($this->defaultDatas[0], $this->db->fetchrow($res), 'fetchrow first result after selecting all elements - explicit statement parameter');

		$this->assertEquals($this->defaultDatas[1], $this->db->fetchrow($res), 'fetchrow second result after selecting all elements - explicit statement parameter');

		$this->assertEquals($this->defaultDatas[2], $this->db->fetchrow($res), 'fetchrow third result after selecting all elements - explicit statement parameter');

		$this->assertEquals($this->defaultDatas[3], $this->db->fetchrow($res), 'fetchrow fourth result after selecting all elements - explicit statement parameter');

		$this->assertFalse($this->db->fetchrow($res), 'fetchrow one more result after selecting all elements - explicit statement parameter');

		$this->db->query('SELECT * FROM '.self::$escapeQuery.'testtable'.self::$escapeQuery.' ORDER BY id LIMIT 1, 2');

		$this->assertEquals($this->defaultDatas[1], $this->db->fetchrow(), 'fetchrow first result after selecting limited (1, 2) elements - implicit statement parameter');

		$this->assertEquals($this->defaultDatas[2], $this->db->fetchrow(), 'fetchrow second result after selecting limited (1, 2) elements - implicit statement parameter');

		$this->assertFalse($this->db->fetchrow(), 'fetchrow one more result after selecting limited (1, 2) elements - implicit statement parameter');

		$res = $this->db->query('SELECT * FROM '.self::$escapeQuery.'testtable'.self::$escapeQuery.' ORDER BY id LIMIT 1, 2');

		$this->assertEquals($this->defaultDatas[1], $this->db->fetchrow($res), 'fetchrow first result after selecting limited (1, 2) elements - explicit statement parameter');

		$this->assertEquals($this->defaultDatas[2], $this->db->fetchrow($res), 'fetchrow second result after selecting limited (1, 2) elements - explicit statement parameter');

		$this->assertFalse($this->db->fetchrow($res), 'fetchrow one more result after selecting limited (1, 2) elements - explicit statement parameter');
	}

	public function testGetLastId()
	{
		if(defined('ORM_ID_AS_UID') && ORM_ID_AS_UID){
			// getLastId don't work with uid
			$this->markTestSkipped('PDOStatement::lastInsertId() does not return proper value with UUID');
			return;
		}else{
			$this->assertDataSetsEqual(new \PHPUnit_Extensions_Database_DataSet_ArrayDataSet(array(
				'testtable' => $this->defaultDatas,
			)), $this->getConnection()->createDataSet(), 'Pre-Condition: INSERT');

			if($this->db->getConnector() == DB::CONNECTOR_PGSQL){
				$this->db->query('INSERT INTO '.self::$escapeQuery.'testtable'.self::$escapeQuery.' ('.self::$escapeQuery.'value'.self::$escapeQuery.') VALUES (:val)', array(
					':val'  => array('abc', \PDO::PARAM_STR),
				));
			}else{
				$this->db->query('INSERT INTO '.self::$escapeQuery.'testtable'.self::$escapeQuery.' ('.self::$escapeQuery.'id'.self::$escapeQuery.', '.self::$escapeQuery.'value'.self::$escapeQuery.') VALUES (:id, :val)', array(
					':id'   => array(NULL,  \PDO::PARAM_INT),
					':val'  => array('abc', \PDO::PARAM_STR),
				));
			}

			$this->assertEquals(5, $this->db->getLastId(), 'last ID after inserting auto increment ID element');

			$this->db->query('INSERT INTO '.self::$escapeQuery.'testtable'.self::$escapeQuery.' ('.self::$escapeQuery.'id'.self::$escapeQuery.', '.self::$escapeQuery.'value'.self::$escapeQuery.') VALUES (:id, :val)', array(
				':id'   => array(7,     \PDO::PARAM_INT),
				':val'  => array('def', \PDO::PARAM_STR),
			));

			$this->assertEquals(7, $this->db->getLastId(), 'last ID after inserting fixed ID element');

			if($this->db->getConnector() == DB::CONNECTOR_PGSQL){
				$this->db->query('INSERT INTO '.self::$escapeQuery.'testtable'.self::$escapeQuery.' ('.self::$escapeQuery.'value'.self::$escapeQuery.') VALUES (:val)', array(
					':val'  => array('ghi', \PDO::PARAM_STR),
				));
			}else{
				$this->db->query('INSERT INTO '.self::$escapeQuery.'testtable'.self::$escapeQuery.' ('.self::$escapeQuery.'id'.self::$escapeQuery.', '.self::$escapeQuery.'value'.self::$escapeQuery.') VALUES (:id, :val)', array(
					':id'   => array(NULL,     \PDO::PARAM_INT),
					':val'  => array('ghi', \PDO::PARAM_STR),
				));
			}

			if($this->db->getConnector() == DB::CONNECTOR_PGSQL){
				$this->db->query('INSERT INTO '.self::$escapeQuery.'testtable'.self::$escapeQuery.' ('.self::$escapeQuery.'value'.self::$escapeQuery.') VALUES (:val)', array(
					':val'  => array('jkl', \PDO::PARAM_STR),
				));
			}else{
				$this->db->query('INSERT INTO '.self::$escapeQuery.'testtable'.self::$escapeQuery.' ('.self::$escapeQuery.'id'.self::$escapeQuery.', '.self::$escapeQuery.'value'.self::$escapeQuery.') VALUES (:id, :val)', array(
					':id'   => array(NULL,     \PDO::PARAM_INT),
					':val'  => array('jkl', \PDO::PARAM_STR),
				));
			}

			$this->assertEquals(9, $this->db->getLastId(), 'last ID after inserting fixed ID element');
		}
	}

	public function testDescribe()
	{
		$this->assertEquals(array(
			array(
				'field'     => 'id',
				'default'   => '',
				'null'      =>  '',
			),
			array(
				'field'     => 'value',
				'default'   => '',
				'null'      =>  '',
			),
			array(
				'field'     => 'other',
				'default'   => '',
				'null'      =>  true,
			),
			array(
				'field'     => 'third',
				'default'   => '4',
				'null'      =>  true,
			),
		), $this->db->describe('testtable'));
		die();
	}
}
