<?php

use Pragma\DB\DB;

class DBTest extends PHPUnit_Framework_TestCase
{
	public function testSingleton()
	{
		$db = new DB();

		$this->assertEquals($db, DB::getDB());
	}
}
