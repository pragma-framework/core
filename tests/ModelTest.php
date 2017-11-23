<?php

namespace Pragma\Tests;

use Pragma\DB\DB;

require_once __DIR__.'/Testtable.php';

class ModelTest extends \PHPUnit_Framework_TestCase
{
	protected $pdo;
	protected $db;
	protected $obj;

	function __construct($name = null, array $data = array(), $dataName = '') {
    	$this->db = DB::getDB();
		$this->pdo = $this->db->getPDO();
		parent::__construct($name, $data, $dataName);
    }

	public function setUp()
	{
		$this->pdo->exec('DROP TABLE IF EXISTS `testtable`');

		switch (DB_CONNECTOR) {
			case 'mysql':
				$id = '`id` int NOT NULL AUTO_INCREMENT PRIMARY KEY';
				if(defined('ORM_ID_AS_UID') && ORM_ID_AS_UID){
					if(defined('ORM_UID_STRATEGY') && ORM_UID_STRATEGY == 'mysql'){
						$id = '`id` char(36) NOT NULL PRIMARY KEY';
					}else{
						$id = '`id` char(23) NOT NULL PRIMARY KEY';
					}
				}
				$this->pdo->exec('CREATE TABLE `testtable` (
					'.$id.',
					`value` text    NOT NULL,
					`other` text    NULL,
					`third` int     NULL DEFAULT 4
				);');
				break;
			case 'sqlite':
				$id = '`id` integer NOT NULL PRIMARY KEY AUTOINCREMENT';
				if(defined('ORM_ID_AS_UID') && ORM_ID_AS_UID){
					if(defined('ORM_UID_STRATEGY') && ORM_UID_STRATEGY == 'mysql'){
						$id = '`id` varchar(36) NOT NULL PRIMARY KEY';
					}else{
						$id = '`id` varchar(23) NOT NULL PRIMARY KEY';
					}
				}
				$this->pdo->exec('CREATE TABLE  `testtable` (
					'.$id.',
					`value` text NOT NULL,
					`other` text NULL,
					`third` int  NULL DEFAULT 4
				);');
				break;
		}

		$this->obj = new Testtable();
		$this->obj->value = 'abc';
		$this->obj->save();

		parent::setUp();
	}

	public function tearDown()
	{
		if(!empty($this->obj)){
			$this->obj->delete();
		}
		parent::tearDown();
	}

	public static function tearDownAfterClass(){
		$db = DB::getDB();
		$pdo = $db->getPDO();
		$pdo->exec('DROP TABLE IF EXISTS `testtable`');
		parent::tearDownAfterClass();
	}

	public function testOpen(){
		$o = new Testtable();
		$o->open($this->obj->id);
		$this->assertEquals($o, $this->obj, 'Function open');
	}

	public function testFind(){
		$this->assertEquals(Testtable::find($this->obj->id), $this->obj, 'Function find by id');
	}

	public function testOpenWithFields(){
		$o = new Testtable();
		$o->openWithFields($this->obj->as_array());
		$this->assertEquals($o, $this->obj, 'Function openWithFields');
	}

	public function testAll(){
		$this->assertEquals(Testtable::all(), [$this->obj->id => $this->obj], 'Function all with key index');
		$this->assertEquals(Testtable::all(false), [$this->obj], 'Function all without key index');
	}
}
