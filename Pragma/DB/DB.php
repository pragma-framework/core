<?php
namespace Pragma\DB;

use \PDO;
use Pragma\Exceptions\DBException;

class DB{
	const CONNECTOR_MYSQL   = 1;
	const CONNECTOR_SQLITE  = 2;
	const CONNECTOR_PGSQL   = 3;
	const CONNECTOR_MSSQL		= 4;

	/* Split resultset - rotation modes */
	const ROT_ROW_TABLE_FIELD = 1; // Rows > Tables > Fields
	const ROT_TABLE_ROW_FIELD = 2; // Tables > Rows > Fields
	const ROT_TABLE_FIELD_ROW = 3; // Tables > Fields > Rows

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
				case 'pgsql':
				case 'postgresql':
					$this->connector = self::CONNECTOR_PGSQL;
					$this->pdo = new PDO('pgsql:host='.DB_HOST.';dbname='.DB_NAME, DB_USER, DB_PASSWORD, $this->drivers);
					break;
				case 'mssql':
					$this->connector = self::CONNECTOR_MSSQL;
					$this->pdo = new PDO('sqlsrv:Server='.DB_HOST.';TrustServerCertificate=1;Database='.DB_NAME, DB_USER, DB_PASSWORD, $this->drivers);
					break;
			}
		}
		catch(\Exception $e){
			error_log($e->getMessage());
			die("An error occurred while connecting to database");
		}
	}

	//SINGLETON
	public static function getDB($force = true){
		if (!(self::$db instanceof self) || $force){//see http://fr.wikipedia.org/wiki/Singleton_(patron_de_conception)#PHP_5
			self::$db = new self();
		}

		return self::$db;
	}

	// Useful in context of an infinite script (while MQTT) to refresh the connection and avoid errors like "Server gone away"
	public static function refreshConnection() {
		return static::getDB(true);
	}

	//returns $pdo
	public function getPDO(){
		return $this->pdo;
	}

	//execute a SQL query with params
	public function query($q, $params = array()){
		try{
			unset($this->st);
			$this->st = null;
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
						if(is_array($val) && count($val) == 2){//if called with PDO::PARAM_STR or PDO::PARAM_INT, PDO::PARAM_BOOL
							$this->st->bindValue($p, $val[0], $val[1]);
						}
						else{
							if(! is_bool($val)) { //fix fatal error with some php versions
								$this->st->bindValue($p, $val);
							}
							else {
								$this->st->bindValue($p, $val, PDO::PARAM_INT);//cast
							}
						}
					}
				}

				$this->st->execute();
				return $this->st;
			}
			else{
				throw new DBException('PDO attribute is undefined');
			}
		}
		catch(\Exception $e){
			throw new DBException($e->getMessage(), (int)$e->getCode());
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

	public function numfields($statement = null)
	{
		if (is_null($statement) && !is_null($this->st)) {
			$statement = $this->st;
		}

		return !is_null($statement) ? $statement->columnCount() : null;
	}

	public function split_resultset($statement = null, $rotation = self::ROT_ROW_TABLE_FIELD)
	{
		if (is_null($statement) && !is_null($this->st)) {
			$statement = $this->st;
		}

		if (is_null($statement)) {
			return null;
		}

		$refs = [];
		$fieldsnum = $this->numfields($statement);
		for ($i = 0; $i < $fieldsnum; ++$i) {
			$colMeta = $statement->getColumnMeta($i);
			$table = $colMeta['table'];
			$field = $colMeta['name'];

			$refs[$i] = [
				'table' => !empty($table) ? $table : '',
				'col'   => $field,
			];
		}

		$row = 0;
		$composite = [];
		while ($fields = $this->fetchrow($statement, PDO::FETCH_NUM)) {
			for ($i = 0; $i < $fieldsnum; ++$i) {
				switch($rotation) {
					default:
					case self::ROT_ROW_TABLE_FIELD:
						$composite[$row][$refs[$i]['table']][$refs[$i]['col']] = $fields[$i];
						break;
					case self::ROT_TABLE_ROW_FIELD:
						$composite[$refs[$i]['table']][$row][$refs[$i]['col']] = $fields[$i];
						break;
					case self::ROT_TABLE_FIELD_ROW:
						$composite[$refs[$i]['table']][$refs[$i]['col']][$row] = $fields[$i];
						break;
				}
			}

			++$row;
		}
		$statement->closeCursor();

		return $composite;
	}

	public function getLastId($name = 'id'){
		if($this->connector == self::CONNECTOR_MSSQL){
			$name = null;
		}
		return trim($this->pdo->lastInsertId($name));//lastInsertId give the last autoincrement id from the DB
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
						'extra'		=> $data['Extra'],
						'key'		=> $data['Key'],
					];
				}
				$res->closeCursor();
				break;
			case self::CONNECTOR_SQLITE:
				$res = $this->query('PRAGMA table_info('.$tablename.')');

				while ($data = $this->fetchrow($res)) {
					$description[] = [
						'field'     => $data['name'],
						'default'   => current(str_getcsv($data['dflt_value'], ",", "'")),
						'null'      => !$data['notnull'],
						'extra'		=> '',
						'key'		=> '',
					];
				}
				$res->closeCursor();
				break;
			case self::CONNECTOR_PGSQL:
				$res = $this->query("SELECT column_name, data_type, column_default, is_nullable
					FROM information_schema.COLUMNS 
					WHERE TABLE_NAME = '".$tablename."'");
				while ($data = $this->fetchrow($res)) {
					$description[] = [
						'field'     => $data['column_name'],
						'default'   => $this->pgDefaultToPhpValue($data['column_default'], $data['data_type']),
						'null'      => $data['is_nullable'] != 'NO',
						'extra'		=> '',
						'key'		=> '',
					];
				}
				$res->closeCursor();
				break;
			case self::CONNECTOR_MSSQL:
				$res = $this->query('sp_columns '.$tablename);
				
				while ($data = $this->fetchrow($res)) {
					$description[] = [
						'field'     => $data['COLUMN_NAME'],
						'default'   => str_replace(')', '', str_replace('(', '', $data['COLUMN_DEF'])),
						'null'      => $data['NULLABLE'] ? true : false,
						'extra'		=> '',
						'key'		=> '',
					];
				}

				$res->closeCursor();
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

	public function getConnector(){
		return $this->connector;
	}

	private function pgDefaultToPhpValue($defaultValue, $dataType)
	{
		if ($defaultValue === null) {
			return null;
		}

		$value = trim($defaultValue);

		/*
		* Expressions PostgreSQL courantes
		*/
		$expressionPatterns = [

			// Dates / heures
			'/^now\(\)$/i',
			'/^current_timestamp$/i',
			'/^current_date$/i',
			'/^current_time$/i',
			'/^localtimestamp$/i',
			'/^localtime$/i',

			// Séquences
			'/^nextval\(/i',

			// UUID
			'/^gen_random_uuid\(\)$/i',
			'/^uuid_generate_v4\(\)$/i',

			// Fonctions diverses
			'/^clock_timestamp\(\)$/i',
			'/^transaction_timestamp\(\)$/i',
			'/^statement_timestamp\(\)$/i',

			// Expressions SQL
			'/^\(.+\)$/'
		];

		foreach ($expressionPatterns as $pattern) {
			if (preg_match($pattern, $value)) {
				return $value;
			}
		}

		/*
		* Suppression des quotes SQL
		*/
		$isQuotedString =
			strlen($value) >= 2 &&
			$value[0] === "'" &&
			$value[strlen($value) - 1] === "'";

		if ($isQuotedString) {
			$value = substr($value, 1, -1);
			$value = str_replace("''", "'", $value);
		}

		/*
		* Conversion selon le type PostgreSQL
		*/
		switch ($dataType) {
			// Entiers
			case 'smallint':
			case 'integer':
			case 'bigint':
				return (int)$value;
			// Décimaux
			case 'numeric':
			case 'decimal':
			case 'real':
			case 'double precision':
				return (float)$value;
			// Booléens
			case 'boolean':
				if(in_array(strtolower($value), ['true', 't', '1'])){
					return true;
				}

				return false;
			// JSON
			case 'json':
			case 'jsonb':
				return json_decode($value, true);
			// Tableaux PostgreSQL simples
			case 'ARRAY':
				$array = trim($value, '{}');

				return $array === ''
						? []
						: explode(',', $array);
			default:
				return $value;
		}
	}
}
