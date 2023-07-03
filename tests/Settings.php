<?php

namespace Pragma\Tests;

use Pragma\DB\DB;

class Settings
{
	public static function get_auto_increment_syntax($connector){
		switch($connector){
			case DB::CONNECTOR_PGSQL:
				return 'SERIAL';
			case DB::CONNECTOR_MSSQL:
				return 'int IDENTITY(1,1)';
			default:
				return 'int NOT NULL AUTO_INCREMENT';
		}
	}
}