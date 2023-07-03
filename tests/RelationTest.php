<?php

namespace Pragma\Tests;

use Pragma\DB\DB;

require_once __DIR__.'/TesttableRel.php';
require_once __DIR__.'/TesttableRelLink.php';
require_once __DIR__.'/Settings.php';

class RelationTest extends \PHPUnit\Framework\TestCase
{
	protected $pdo;
	protected $db;

	protected static $escapeQuery = "`";

	function __construct($name = null, array $data = array(), $dataName = '') {
    	$this->db = DB::getDB();
		$this->pdo = $this->db->getPDO();

		if(defined('DB_CONNECTOR') && (DB_CONNECTOR == 'pgsql' || DB_CONNECTOR == 'postgresql')){
			self::$escapeQuery = "\"";
		}
		elseif(defined('DB_CONNECTOR') && DB_CONNECTOR == 'mssql'){
			self::$escapeQuery = "";
		}

		parent::__construct($name, $data, $dataName);
    }

	public function setUp()
	{
		$st = $this->db->query('DROP TABLE IF EXISTS '.self::$escapeQuery.'testtablerel'.self::$escapeQuery.'');
		$st->closeCursor();
		$st = $this->db->query('DROP TABLE IF EXISTS '.self::$escapeQuery.'testtablerellink'.self::$escapeQuery.'');
		$st->closeCursor();

		switch (DB_CONNECTOR) {
			case 'mysql':
			case 'mssql':
			case 'pgsql':
			case 'postgresql':
				$id = ''.self::$escapeQuery.'id'.self::$escapeQuery.' '.Settings::get_auto_increment_syntax($this->db->getConnector()).' PRIMARY KEY';
				$ids = 'int';
				if(defined('ORM_ID_AS_UID') && ORM_ID_AS_UID){
					if(defined('ORM_UID_STRATEGY') && ORM_UID_STRATEGY == 'mysql'){
						$id = ''.self::$escapeQuery.'id'.self::$escapeQuery.' char(36) NOT NULL PRIMARY KEY';
						$ids = 'char(36)';
					}else{
						$id = ''.self::$escapeQuery.'id'.self::$escapeQuery.' char(23) NOT NULL PRIMARY KEY';
						$ids = 'char(23)';
					}
				}
				$st = $this->db->query('CREATE TABLE '.self::$escapeQuery.'testtablerel'.self::$escapeQuery.' (
					'.$id.',
					'.self::$escapeQuery.'value'.self::$escapeQuery.' text    NOT NULL,
					'.self::$escapeQuery.'parent_id'.self::$escapeQuery.' '.$ids.'
				);');
				$st->closeCursor();
				$st = $this->db->query('CREATE TABLE '.self::$escapeQuery.'testtablerellink'.self::$escapeQuery.' (
					'.$id.',
					'.self::$escapeQuery.'rel1_id'.self::$escapeQuery.' '.$ids.',
					'.self::$escapeQuery.'rel2_id'.self::$escapeQuery.' '.$ids.'
				);');
				$st->closeCursor();
				break;
			case 'sqlite':
				$id = ''.self::$escapeQuery.'id'.self::$escapeQuery.' integer NOT NULL PRIMARY KEY AUTOINCREMENT';
				$ids = 'integer';
				if(defined('ORM_ID_AS_UID') && ORM_ID_AS_UID){
					if(defined('ORM_UID_STRATEGY') && ORM_UID_STRATEGY == 'mysql'){
						$id = ''.self::$escapeQuery.'id'.self::$escapeQuery.' varchar(36) NOT NULL PRIMARY KEY';
						$ids = 'varchar(36)';
					}else{
						$id = ''.self::$escapeQuery.'id'.self::$escapeQuery.' varchar(23) NOT NULL PRIMARY KEY';
						$ids = 'varchar(23)';
					}
				}
				$st = $this->db->query('CREATE TABLE  '.self::$escapeQuery.'testtablerel'.self::$escapeQuery.' (
					'.$id.',
					'.self::$escapeQuery.'value'.self::$escapeQuery.' text NOT NULL,
					'.self::$escapeQuery.'parent_id'.self::$escapeQuery.' '.$ids.'
				);');
				$st->closeCursor();
				$st = $this->db->query('CREATE TABLE '.self::$escapeQuery.'testtablerellink'.self::$escapeQuery.' (
					'.$id.',
					'.self::$escapeQuery.'rel1_id'.self::$escapeQuery.' '.$ids.',
					'.self::$escapeQuery.'rel2_id'.self::$escapeQuery.' '.$ids.'
				);');
				$st->closeCursor();
				break;
		}

		parent::setUp();
	}

	public function tearDown(){
		// $st = $this->db->query('DROP TABLE IF EXISTS '.self::$escapeQuery.'testtablerel'.self::$escapeQuery.'');
		// $st->closeCursor();
		// $st = $this->db->query('DROP TABLE IF EXISTS '.self::$escapeQuery.'testtablerellink'.self::$escapeQuery.'');
		// $st->closeCursor();
		parent::tearDown();
	}

	public static function tearDownAfterClass(){
		$db = DB::getDB();
		$st = $db->query('DROP TABLE IF EXISTS '.self::$escapeQuery.'testtablerel'.self::$escapeQuery.'');
		$st->closeCursor();
		$st = $db->query('DROP TABLE IF EXISTS '.self::$escapeQuery.'testtablerellink'.self::$escapeQuery.'');
		$st->closeCursor();
		parent::tearDownAfterClass();
	}

	public function testRelations(){
		new TesttableRel();
		new TesttableRelLink();
		$cRel = 'Pragma\\Tests\\TesttableRel';
		$cRelLink = 'Pragma\\Tests\\TesttableRelLink';
		$relations = \Pragma\ORM\Relation::getAll();

		$this->assertNotEmpty($relations[$cRel]);

		$this->assertNotEmpty($relations[$cRel]['children']);
		$this->assertEquals('has_many', $relations[$cRel]['children']->get_type());
		$this->assertEquals($cRel, $relations[$cRel]['children']->get_class_on());
		$this->assertEquals($cRel, $relations[$cRel]['children']->get_class_to());
		unset($relations[$cRel]['children']);

		$this->assertNotEmpty($relations[$cRel]['parent']);
		$this->assertEquals('belongs_to', $relations[$cRel]['parent']->get_type());
		$this->assertEquals($cRel, $relations[$cRel]['parent']->get_class_on());
		$this->assertEquals($cRel, $relations[$cRel]['parent']->get_class_to());
		unset($relations[$cRel]['parent']);

		$this->assertNotEmpty($relations[$cRel]['sub1']);
		$this->assertEquals('has_many_through', $relations[$cRel]['sub1']->get_type());
		$this->assertEquals($cRel, $relations[$cRel]['sub1']->get_class_on());
		$this->assertEquals($cRel, $relations[$cRel]['sub1']->get_class_to());
		$subRel = $relations[$cRel]['sub1']->get_sub_relation();
		$this->assertNotEmpty($subRel['through']);
		$this->assertEquals($cRelLink, $subRel['through']);
		unset($relations[$cRel]['sub1']);

		$this->assertNotEmpty($relations[$cRel]['sub2']);
		$this->assertEquals('has_many_through', $relations[$cRel]['sub2']->get_type());
		$this->assertEquals($cRel, $relations[$cRel]['sub2']->get_class_on());
		$this->assertEquals($cRel, $relations[$cRel]['sub2']->get_class_to());
		$subRel = $relations[$cRel]['sub2']->get_sub_relation();
		$this->assertNotEmpty($subRel['through']);
		$this->assertEquals($cRelLink, $subRel['through']);
		unset($relations[$cRel]['sub2']);

		$this->assertEmpty($relations[$cRel]);

		$this->assertNotEmpty($relations[$cRelLink]);

		$this->assertNotEmpty($relations[$cRelLink]['rel1']);
		$this->assertEquals('belongs_to', $relations[$cRelLink]['rel1']->get_type());
		$this->assertEquals($cRelLink, $relations[$cRelLink]['rel1']->get_class_on());
		$this->assertEquals($cRel, $relations[$cRelLink]['rel1']->get_class_to());
		unset($relations[$cRelLink]['rel1']);

		$this->assertNotEmpty($relations[$cRelLink]['rel2']);
		$this->assertEquals('belongs_to', $relations[$cRelLink]['rel2']->get_type());
		$this->assertEquals($cRelLink, $relations[$cRelLink]['rel2']->get_class_on());
		$this->assertEquals($cRel, $relations[$cRelLink]['rel2']->get_class_to());
		unset($relations[$cRelLink]['rel2']);

		$this->assertEmpty($relations[$cRelLink]);
	}

	public function testCreateChild(){
		$o = TesttableRel::build([
			'value' => 'abc',
		])->save();
		$o = TesttableRel::find($o->id); // To have the DB default value setted
		$this->assertFalse($o->is_new(), 'Parent object created');
		$o2 = TesttableRel::build([
			'value' => 'def',
			'parent_id' => $o->id,
		])->save();
		$this->assertFalse($o->is_new(), 'Child object created');
		$this->assertEquals($o->id, $o2->parent_id, 'Check parent id');
		$this->assertEquals($o, $o2->rel('parent'), 'Check parent object');

		$this->assertEquals([TesttableRel::find($o2->id)], $o->rel('children'), 'Check children objects');
	}

	public function testCreateLink(){
		$o = TesttableRel::build([
			'value' => 'abc',
		])->save();
		$o = TesttableRel::find($o->id); // To have the DB default value setted
		$this->assertFalse($o->is_new(), 'Object 1 created');
		$o2 = TesttableRel::build([
			'value' => 'def',
		])->save();
		$o2 = TesttableRel::find($o->id); // To have the DB default value setted
		$this->assertFalse($o->is_new(), 'Object 2 created');
		$link = TesttableRelLink::build([
			'rel1_id' => $o->id,
			'rel2_id' => $o2->id,
		])->save();
		$this->assertFalse($link->is_new(), 'Link created');

		$this->assertEquals($o, $link->rel('rel1'), 'Check first object');
		$this->assertEquals($o2, $link->rel('rel2'), 'Check second object');

		$this->assertEquals([$link->rel('rel1')], $o2->rel('sub1'));
		$this->assertEquals([$link->rel('rel2')], $o->rel('sub2'));
	}
}
