<?php
namespace Pragma\ORM;

use Pragma\DB\DB;
use \PDO;

class Model extends QueryBuilder implements SerializableInterface{
	static protected $table_desc = array();

	protected $fields = array();
	protected $new = true;
	protected $desc = array();

	public function __construct($tb_name){
		parent::__construct($tb_name);

		$this->fields = $this->describe();
	}

	public function __get($attr){
		if(array_key_exists($attr, $this->describe())){
			return $this->fields[$attr];
		}
		return null;
	}

	public function __set($attr, $value){
		if(array_key_exists($attr, $this->describe())){
			$this->fields[$attr] = $value;
		}
	}

	public function __isset($attr) {
		if(array_key_exists($attr, $this->describe())){
			return (false === empty($this->fields[$attr]));
		}
		return null;
	}

	public function is_new(){
		return $this->new;
	}

	public function get_table(){
		return $this->table;
	}

	public function open($id){
		$db = DB::getDB();
		$res = $db->query("SELECT * FROM ".$this->table." WHERE id = :id", array(
							':id' => array($id, PDO::PARAM_INT)
							));

		//it must return only one row
		$data = $db->fetchrow($res);
		if ($data) {
			$this->openWithFields($data);
			return $this;
		}

		return null;
	}

	public static function find($id){
		return static::forge()->where('id', '=', $id)->first();
	}

	public function openWithFields($data, $whitelist = null){
		if( ! empty($data) && isset($data['id']) ){

			//whitelist allows to get the description on an object and check if data is correct
			//the idea is to optimize by doing the describe only once
			if( ! is_null($whitelist) ){
				foreach($data as $f => $val){
					if( ! array_key_exists($f, $whitelist) ){
						unset($data[$f]);
					}
				}
			}

			$this->fields = $data;
			$this->new = false;

			return $this;
		}

		return null;
	}

	public function delete(){
		if( ! $this->new && ! is_null($this->id) && $this->id > 0){
			$db = DB::getDB();
			$db->query('DELETE FROM '.$this->table.' WHERE id = :id',
				array(':id' => array($this->id, PDO::PARAM_INT)));
		}
	}

	public static function all($idkey = true){
		return static::forge()->get_objects($idkey);
	}

	public static function build($data = array()){
		$obj = new static();
		$obj->fields = $obj->describe();

		$obj->fields = array_merge($obj->fields, $data);

		return $obj;
	}

	public function merge($data){
		$this->fields = array_merge($this->fields, $data);
		return $this;
	}


	public function save(){
		$db = DB::getDB();

		if($this->new){//INSERT
			$sql = 'INSERT INTO `'.$this->table.'` (';
			$first = true;
			foreach($this->describe() as $col => $default){
				if(!$first) $sql .= ', ';
				else $first = false;
				$sql .= '`'.$col.'`';
			}
			$sql .= ') VALUES (';

			$values = array();
			$first = true;
			foreach($this->describe() as $col => $default){
				if(!$first) $sql .= ', ';
				else $first = false;
				$sql .= ':'.$col;
				$values[':'.$col] = array_key_exists($col, $this->fields) ? $this->$col : '';
			}
			if(isset($values[':id'])){
				$values[':id'] = null;
			}

			$sql .= ")";

			$res = $db->query($sql, $values);
			$this->id = $db->getLastId();
			$this->new = false;
		}
		else{//UPDATE
			$sql = 'UPDATE `'.$this->table.'` SET ';
			$first = true;
			$values = array();
			foreach($this->describe() as $col => $default){
				if($col != 'id'){//the id is not updatable
					if(!$first) $sql .= ', ';
					else $first = false;
					$sql .= '`'.$col.'` = :'.$col;
					$values[':'.$col] = array_key_exists($col, $this->fields) ? $this->$col : '';
				}
			}

			$sql .= ' WHERE id = :id';
			$values[':id'] = $this->id;

			$db->query($sql, $values);
		}

		return $this;
	}

	public function toJSON(){
		return json_encode($this->fields);
	}

	public function as_array(){
		return $this->fields;
	}


	protected function describe() {
		$db = DB::getDB();

		if (empty(self::$table_desc[$this->table])) {
			foreach ($db->describe($this->table) as $data) {
				if ($data['default'] === null && !$data['null']) {
					self::$table_desc[$this->table][$data['field']] = '';
				} else {
					self::$table_desc[$this->table][$data['field']] = $data['default'];
				}
			}
		}

		return self::$table_desc[$this->table];
	}
}
