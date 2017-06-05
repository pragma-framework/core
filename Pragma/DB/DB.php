<?php
namespace Pragma\DB;

use \PDO;

class DB{
	const CONNECTOR_MYSQL   = 1;
	const CONNECTOR_SQLITE  = 2;

	protected $pdo;
	protected $st = null;
	protected static $db = null;//singleton
	protected $connector = null;
	protected $drivers = array(
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_EMULATE_PREPARES => false,
	);

	public function __construct(){
		try{
			if( ! defined('DB_CONNECTOR') ){
				define('DB_CONNECTOR', 'mysql');
			}
			switch (DB_CONNECTOR) {
				default:
				case 'mysql':
					$this->connector = self::CONNECTOR_MYSQL;
					if(defined("PDO::MYSQL_ATTR_INIT_COMMAND")){
						$this->drivers[PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET names utf8';
					}
					$this->pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8', DB_USER, DB_PASSWORD, $this->drivers);
					break;
				case 'sqlite':
					$this->connector = self::CONNECTOR_SQLITE;
					$this->pdo = new PDO('sqlite:'.DB_NAME, null, null, $this->drivers);

					break;
			}
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

	public function describe($tablename) {
		$description = array();

		switch ($this->connector) {
			case self::CONNECTOR_MYSQL:
				$res = $this->query('DESC '.$tablename);

				while ($data = $this->fetchrow($res)) {
					$description[] = [
						'field'     => $data['Field'],
						'default'   => $data['Default'],
						'null'      => $data['Null'] != 'NO',
					];
				}
				break;
			case self::CONNECTOR_SQLITE:
				$res = $this->query('PRAGMA table_info('.$tablename.')');

				while ($data = $this->fetchrow($res)) {
					$description[] = [
						'field'     => $data['name'],
						'default'   => current(str_getcsv($data['dflt_value'], ",", "'")),
						'null'      => !$data['notnull'],
					];
				}
				break;
		}

		return $description;
	}

	public static function getPDOParamsFor($tab, &$params){
		if(is_array($tab)){
			if(!empty($tab)){
				$subparams = [];
				$counter_params = count($params) + 1;
				foreach($tab as $val){
					$subparams[':param'.$counter_params] = $val;
					$counter_params++;
				}
				$params = array_merge($params, $subparams);
				return implode(',',array_keys($subparams));
			}
			else{
				throw new \Exception("getPDOParamsFor : Tryin to get PDO Params on an empty array");
			}
		}
		else{
				throw new \Exception("getPDOParamsFor : Params should be an array");
			}
	}
}
