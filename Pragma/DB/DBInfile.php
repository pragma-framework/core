<?php
namespace Pragma\DB;

use \PDO;

/**
 * This connector is useful when using "LOAD DATA INFILE" in an SQL query
 * Only PDO parameters are changed for accepting query
 */
class DBInfile extends DB{
	protected static $dbInfile = null;//singleton
	protected $drivers = array(
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		PDO::MYSQL_ATTR_LOCAL_INFILE=>1,
		PDO::ATTR_EMULATE_PREPARES => true,
		PDO::ATTR_PERSISTENT=>false
	);

	//SINGLETON
	public static function getDB(){
		if (!(self::$dbInfile instanceof self)){//see http://fr.wikipedia.org/wiki/Singleton_(patron_de_conception)#PHP_5
			self::$dbInfile = new self();
		}

		return self::$dbInfile;
	}
}