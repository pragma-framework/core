<?php

namespace Pragma\Tests;

use Pragma\DB\DB;

require_once __DIR__.'/Testtable.php';

class ModelTest extends \PHPUnit\Framework\TestCase
{
	protected $pdo;
	protected $db;
	protected $obj;

	protected static $escapeQuery = "`";

	function __construct($name = null, array $data = array(), $dataName = '') {
    	$this->db = DB::getDB();
		$this->pdo = $this->db->getPDO();

		if(defined('DB_CONNECTOR') && (DB_CONNECTOR == 'pgsql' || DB_CONNECTOR == 'postgresql')){
			self::$escapeQuery = "\"";
		}

		parent::__construct($name, $data, $dataName);
	}

	public function setUp()
	{
		$st = $this->db->query('DROP TABLE IF EXISTS '.self::$escapeQuery.'testtable'.self::$escapeQuery.'');
		$st->closeCursor();

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
				$st = $this->db->query('CREATE TABLE '.self::$escapeQuery.'testtable'.self::$escapeQuery.' (
					'.$id.',
					'.self::$escapeQuery.'value'.self::$escapeQuery.' text    NOT NULL,
					'.self::$escapeQuery.'other'.self::$escapeQuery.' text    NULL,
					'.self::$escapeQuery.'third'.self::$escapeQuery.' int     NULL DEFAULT 4
				);');
				$st->closeCursor();
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
				$st = $this->db->query('CREATE TABLE  '.self::$escapeQuery.'testtable'.self::$escapeQuery.' (
					'.$id.',
					'.self::$escapeQuery.'value'.self::$escapeQuery.' text NOT NULL,
					'.self::$escapeQuery.'other'.self::$escapeQuery.' text NULL,
					'.self::$escapeQuery.'third'.self::$escapeQuery.' int  NULL DEFAULT 4
				);');
				$st->closeCursor();
				break;
		}

		$this->obj = new Testtable();
		$this->obj->value = 'abc';
		$this->obj->save();

		parent::setUp();
	}

	public function tearDown()
	{
		$this->obj = null;
		// $st = $this->db->query('TRUNCATE '.self::$escapeQuery.'testtable'.self::$escapeQuery.'');
		// $st->closeCursor();
		parent::tearDown();
	}

	public static function tearDownAfterClass(){
		$db = DB::getDB();
		$st = $db->query('DROP TABLE IF EXISTS '.self::$escapeQuery.'testtable'.self::$escapeQuery.'');
		$st->closeCursor();
		parent::tearDownAfterClass();
	}

	public function testOpen(){
		$o = new Testtable();
		$o->open($this->obj->id);
		$this->assertEquals($this->obj, $o, 'Function open');
	}

	public function testFind(){
		$this->assertEquals($this->obj, Testtable::find($this->obj->id), 'Function find by id');
	}

	public function testOpenWithFields(){
		$o = new Testtable();
		$o->openWithFields($this->obj->as_array());
		$this->assertEquals($o, $this->obj, 'Function openWithFields');
	}

	public function testAll(){
		$this->assertEquals([$this->obj->id => $this->obj], Testtable::all(), 'Function all with key index');
		$this->assertEquals([$this->obj], Testtable::all(false), 'Function all without key index');
	}

	public function testForceId(){
		if(defined('ORM_ID_AS_UID') && ORM_ID_AS_UID){
			$this->markTestSkipped('Id already force with UID');
		}else{
			$o = Testtable::build([
				'id' => 3,
				'value' => 'def',
			])->allowForcedId(true)->save();
			$this->assertEquals(3, $o->id, 'Forced id to 3');
			$o = Testtable::build([
				'id' => null,
				'value' => 'ghi',
			])->save();
			$this->assertEquals(4, $o->id, 'Auto-increment id to 4');
			$o = Testtable::build([
				'id' => 2,
				'value' => 'jkl',
			])->allowForcedId(true)->save();
			$this->assertEquals(2, $o->id, 'Forced id to 2');
			$o = Testtable::build([
				'id' => null,
				'value' => 'mno',
			])->save();
			$this->assertEquals(5, $o->id, 'Auto-increment id to 5');
		}
	}
	public function testDescribe(){
		$this->assertEquals([
			'id' => '',
			'value' => '',
			'other' => null,
			'third' => 4,
		], $this->obj->describe(), 'Describe object');
	}
	public function testSerialize(){
		$this->assertEquals(json_encode([
			'id' => $this->obj->id,
			'value' => $this->obj->value,
			'other' => $this->obj->other,
			'third' => $this->obj->third,
		]), json_encode($this->obj), 'Serialize object');
	}
	public function testChangeDetection(){
		$this->obj->enableChangesDetection(true);
		$this->assertFalse($this->obj->changed(), 'Object hasn\'t changes');
		$this->assertEquals([], $this->obj->changes(), 'Object hasn\'t changes');

		$this->obj->other = 'other';
		$this->assertTrue($this->obj->changed(), 'Object has changes');
		$this->assertEquals([
			'other' => ['before' => null, 'after' => 'other']
		], $this->obj->changes(), 'Object has changes');

		/*
		$this->obj->disableChangesDetection();
		$this->assertFalse($this->obj->changed(), 'Object hasn\'t changes');
		$this->assertEquals([], $this->obj->changes(), 'Object hasn\'t changes');

		$this->obj->initChangesDetection();
		$this->obj->other = 'other';
		$this->assertFalse(@$this->obj->changed(), 'Object has changes but we don\'t track it');
		$this->assertEquals([], @$this->obj->changes(), 'Object has changes but we don\'t track it');
		*/
	}
	public function testAttrsAllowed(){
		$this->obj->attrs_allowed(['value'], true);
		$o = Testtable::build([
			'value' => 'def',
			'third' => 5,
		])->save();
		$this->assertEquals(4, $o->third, 'Can\'t assign third field');

		$this->obj->attrs_allowed(['third'], true);
		$this->obj->merge([
			'value' => 'ghi',
			'third' => 5,
		])->save();
		$this->assertEquals(5, $this->obj->third, 'Can assign third field');
		$this->assertEquals('abc', $this->obj->value, 'Can\'t assign value field');
	}
}
