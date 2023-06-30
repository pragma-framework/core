<?php

namespace Pragma\Tests;

use Pragma\DB\DB;

require_once __DIR__.'/TesttableHook.php';
require_once __DIR__.'/Settings.php';

class HookTest extends \PHPUnit\Framework\TestCase
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
		elseif(defined('DB_CONNECTOR') && (DB_CONNECTOR == 'mssql')){
			self::$escapeQuery = "";
		}

		parent::__construct($name, $data, $dataName);
    }

	public function setUp()
	{
		$st = $this->db->query('DROP TABLE IF EXISTS '.self::$escapeQuery.'testtablehook'.self::$escapeQuery.'');
		$st->closeCursor();

		switch (DB_CONNECTOR) {
			case 'mysql':
			case 'mssql':
			case 'pgsql':
			case 'postgresql':
				$id = ''.self::$escapeQuery.'id'.self::$escapeQuery.' '.Settings::get_auto_increment_syntax($this->db->getConnector()).' PRIMARY KEY';
				if(defined('ORM_ID_AS_UID') && ORM_ID_AS_UID){
					if(defined('ORM_UID_STRATEGY') && ORM_UID_STRATEGY == 'mysql'){
						$id = ''.self::$escapeQuery.'id'.self::$escapeQuery.' char(36) NOT NULL PRIMARY KEY';
					}else{
						$id = ''.self::$escapeQuery.'id'.self::$escapeQuery.' char(23) NOT NULL PRIMARY KEY';
					}
				}
				$st = $this->db->query('CREATE TABLE '.self::$escapeQuery.'testtablehook'.self::$escapeQuery.' (
					'.$id.',
					'.self::$escapeQuery.'value'.self::$escapeQuery.' text    NOT NULL
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
				$st = $this->db->query('CREATE TABLE  '.self::$escapeQuery.'testtablehook'.self::$escapeQuery.' (
					'.$id.',
					'.self::$escapeQuery.'value'.self::$escapeQuery.' text NOT NULL
				);');
				$st->closeCursor();
				break;
		}

		TesttableHook::setTester($this);

		parent::setUp();
	}

	public function tearDown()
	{
		$this->obj = null;
		// $st = $this->db->query('TRUNCATE '.self::$escapeQuery.'testtablehook'.self::$escapeQuery.'');
		// $st->closeCursor();
		parent::tearDown();
	}

	public static function tearDownAfterClass(){
		$db = DB::getDB();
		$pdo = $db->getPDO();
		$st = $db->query('DROP TABLE IF EXISTS '.self::$escapeQuery.'testtablehook'.self::$escapeQuery.'');
		$st->closeCursor();
		parent::tearDownAfterClass();
	}

    public function testHooksBuild(){
		try{
			return TesttableHook::build(['value' => 'abc']);
		} catch(\Exception $e){
			error_log($e->getMessage());
		}
    }

    /**
	 * @depends testHooksBuild
	 */
    public function testHooksSave(TesttableHook $o){
        $o->save();
    }

    public function testHooksOpen(){
        TesttableHook::initCount();
        $o = TesttableHook::build(['value' => 'abc'])->save();
        $id = $o->id;
        TesttableHook::initCount();
        $o = new TesttableHook();
        $o->open($id);
        return $o;
    }

    /**
	 * @depends testHooksOpen
	 */
    public function testHooksDelete(TesttableHook $o){
        $o->delete();
    }
}
