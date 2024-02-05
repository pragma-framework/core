<?php
namespace Pragma\Tests;

use Pragma\ORM\Model;

class TesttableHook extends Model{
    protected static $tester;

    protected static $count = 0;
    public function __construct(){
        parent::__construct('testtablehook');

        $this->pushHook('before_save', 'before_save');
        $this->pushHook('after_save', 'after_save');
        $this->pushHook('before_delete', 'before_delete');
        $this->pushHook('after_delete', 'after_delete');
        $this->pushHook('after_open', 'after_open');
        $this->pushHook('after_build', 'after_build');
    }

    public static function setTester($tester){
        self::$tester = $tester;
    }

    public static function initCount(){
        self::$count = 0;
    }

    public static function build($data = array(), $bypass_ma = false){
        self::$count++;
        self::$tester->assertEquals(1, self::$count, 'Build');
        return parent::build($data, $bypass_ma);
    }

    protected function after_build(){
        self::$count++;
        self::$tester->assertEquals(2, self::$count, 'After build hook');
    }

    protected function before_save(){
        self::$count++;
        self::$tester->assertEquals(3, self::$count, 'Before save hook');
    }

    protected function after_save(){
        self::$count++;
        self::$tester->assertEquals(4, self::$count, 'After save hook');
    }

    protected function after_open(){
        self::$count++;
        self::$tester->assertEquals(1, self::$count, 'After open hook');
    }

    protected function before_delete(){
        self::$count++;
        self::$tester->assertEquals(2, self::$count, 'Before delete hook');
    }

    protected function after_delete(){
        self::$count++;
        self::$tester->assertEquals(3, self::$count, 'After delete hook');
    }
}
