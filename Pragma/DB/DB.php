<?php
namespace Pragma\DB;

use \PDO;

class DB{
	private $pdo;
	private $st = null;
	private static $db = null;//singletor

	public function __construct(){
		try{
			$this->pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME, DB_USER, DB_PASSWORD, array(
				PDO::MYSQL_ATTR_INIT_COMMAND => 'SET names utf8',
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
				PDO::ATTR_EMULATE_PREPARES => false,
				));
		}
		catch(\Exception $e){
			die("An error occurred while connecting to database");
		}
	}

	//SINGLETON
	public static function getDB(){
		if (!(self::$db instanceof self)){//see http://fr.wikipedia.org/wiki/Singleton_(patron_de_conception)#PHP_5
			self::$db = new self();
		}

		return self::$db;
	}

	//returns $pdo
	public function getPDO(){
		return $this->pdo;
	}

	//execute a SQL query with params
	public function query($q, $params = array()){
		try{
			if(!empty($this->pdo)){
				$this->st = $this->pdo->prepare($q);
				if(!empty($params)){
					$keys = array_keys($params);
					$qmark = false;//qmark = question mark
					if(is_int($keys[0])){//mode '?'
						$qmark = true;
					}

					foreach($params as $p => $val){
						if($qmark){
							//$p is a numeric - 0, 1, 2, ...
							$p += 1;//bindValue starts to 1
						}
						if(is_array($val) && count($val) == 2){//if called with PDO::PARAM_STR or PDO::PARAM_INT
							$this->st->bindValue($p, $val[0], $val[1]);
						}
						else{
							$this->st->bindValue($p, $val);
						}
					}
				}

				$this->st->execute();
				return $this->st;
			}
			else return null;
		}
		catch(\Exception $e){
			var_dump($e);
			return null;
		}
	}

	public function numrows($statement = null){
		if( is_null($statement) && ! is_null($this->st)){
			$statement = $this->st;
		}
		return ! is_null($statement) ? $statement->rowCount() : null;
	}

	public function fetchrow($statement = null, $mode = PDO::FETCH_ASSOC){
		if( is_null($statement) && ! is_null($this->st)){
			$statement = $this->st;
		}

		return ! is_null($statement) ? $statement->fetch($mode) : null;
	}

	public function getLastId(){
		return $this->pdo->lastInsertId();//lastInsertId give the last autoincrement id from the DB
	}
}
