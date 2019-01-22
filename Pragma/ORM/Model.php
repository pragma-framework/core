<?php
namespace Pragma\ORM;

use Pragma\DB\DB;
use \PDO;

class Model extends QueryBuilder implements SerializableInterface, \JsonSerializable{
	static protected $table_desc = array();
	//Extra AI > in order to load extra autoincrement after an insert
	static protected $table_extra_ai = array();

	protected $fields = array();
	protected $new = true;
	protected $desc = array();
	protected $inclusions = array();

	//Hooks
	protected $before_save_hooks = [];
	protected $after_save_hooks = [];
	protected $before_delete_hooks = [];
	protected $after_delete_hooks = [];
	protected $after_open_hooks = [];
	protected $after_build_hooks = [];

	protected $changes_detection = false;
	protected $initialized = false;//usefull for sub-traits
	protected $initial_values = [];

	protected $primary_key = 'id'; //mixed - string or array of strings

	protected $default_matchers = null;
	protected $default_loaders = null;

	protected $forced_id_allowed = false;

	//mass assignment
	static protected $mass_allowed = [];

	//Extra AI > in order to load extra autoincrement after an insert
	protected $extra_ai = null;

	public function __construct($tb_name, $pk = null){
		parent::__construct($tb_name);
		$this->fields = $this->describe();
		$error = false;
		if( is_null($pk) || ! is_array($pk) ){
			if( is_null($pk) ){
				$pk = 'id';
			}

			if( array_key_exists($pk, $this->fields)){
				$this->primary_key = $pk;
			}
			else{
				$error = true;
			}
		}
		else{
			foreach($pk as $k){
				if( ! array_key_exists('id', $this->fields) ){
					$error = true;
					break;
				}
			}
			if(!$error){
				$this->primary_key = $pk;
			}
		}

		if($error){
			throw new \Exception("Error getting an instance of ".get_class($this)." - PK Error", 1);

		}
	}

	public function allowForcedId($val = true){
		$this->forced_id_allowed = $val;
		return $this;
	}

	public function __get($attr){
		if(array_key_exists($attr, $this->describe())){
			return $this->fields[$attr];
		}elseif(array_key_exists($attr, $this->inclusions)){
			return $this->inclusions[$attr];
		}
		return null;
	}

	public function __set($attr, $value){
		if(array_key_exists($attr, $this->describe())){
			$this->fields[$attr] = $value;
		}
		return $this;
	}

	public function __isset($attr) {
		if(array_key_exists($attr, $this->describe())){
			return isset($this->fields[$attr]);
		}
		elseif(array_key_exists($attr, $this->inclusions)){
			return isset($this->inclusions[$attr]);
		}
		return false;
	}

	public function is_new(){
		return $this->new;
	}

	public function get_table(){
		return $this->table;
	}

	public function get_primary_key(){
		return $this->primary_key;
	}

	public function open($pk){
		$db = DB::getDB();
		$sql = "SELECT * FROM ".$this->table." WHERE ";
		$params = [];

		if( ! is_array($pk) && ! is_array($this->primary_key) ){
			$sql .= $this->primary_key ." = :pk";
			$params[':pk'] = $pk;
		}
		else if (is_array($pk) && is_array($this->primary_key)){
			$i = 1;
			$mypks = array_flip($this->primary_key);
			foreach($pk as $k => $val){
				if( ! isset($mypks[$k]) ){
					throw new \Exception("Error opening the instance of ".get_class($this)." - unknown PK column", 1);
					break;
				}
				if( $i > 1 ){
					$sql .= " AND ";
				}

				$sql .= " $k = :pk$i ";
				$params[":pk$i"] = $val;
				$i++;
			}
		}
		else{
			throw new \Exception("Error opening the instance of ".get_class($this)." - wrong pk signature", 1);
		}

		$res = $db->query($sql, $params);
		$data = $db->fetchrow($res);
		if ($data) {
			$this->openWithFields($data);
			//don't play after_open_hooks here, it will be played in openWithFields
			return $this;
		}

		return null;
	}

	public static function find($pk){

		$o = new static();

		if( ! is_array($pk) && ! is_array($o->primary_key)){
			$obj = static::forge()->where($o->primary_key, '=', $pk)->first();
		}
		else if (is_array($pk) && is_array($o->primary_key)){
			$qb = static::forge();
			$mypks = array_flip($o->primary_key);
			foreach($pk as $k => $val){
				if( ! isset($mypks[$k]) ){
					throw new \Exception("Error opening the instance of ".get_class($o)." - unknown PK column", 1);
					break;
				}
				$qb->where($k, '=', $val);
			}

			$obj = $qb->first();
		}
		else{
			throw new \Exception("Error opening the instance of ".get_class($o)." - wrong pk signature", 1);
		}

		//don't play after_open_hooks here, it will be played in the $qb->first via openWithFields
		return $obj;
	}

	public function openWithFields($data, $whitelist = null){
		if( ! empty($data) ){
			// Check if all primary keys are in $data else we can't correctly save/update object
			if(is_array($this->primary_key)){
				foreach($this->primary_key as $k){
					if(!isset($data[$k])){
						return null;
					}
				}
			}elseif(!isset($data[$this->primary_key])){
				return null;
			}

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
			$this->playHooks($this->after_open_hooks);
			//changes detection initializator
			if( $this->isChangesDetection() ) {
				$this->initChangesDetection(true);//create a copy of the fields of the object in this->initial_values
			}

			return $this;
		}

		return null;
	}

	public function delete(){
		$this->playHooks($this->before_delete_hooks);
		if( ! $this->new ){
			$db = DB::getDB();
			$sql = 'DELETE FROM `'.$this->table.'` WHERE ';
			$params = [];
			if( ! is_array($this->primary_key) ){
				$sql .= '`'.$this->primary_key.'` = :pk';
				$params[":pk"] = $this->fields[$this->primary_key];
			}
			else{
				$i = 1;
				foreach($this->primary_key as $pk){
					if( $i > 1 ){
						$sql .= ' AND ';
					}
					$sql .= '`' . $pk . '`' . (is_null($this->fields[$pk]) ? " IS NULL" : " = :pk$i ");
					if( ! is_null($this->fields[$pk]) ){
						$params[":pk$i"] = $this->fields[$pk];
					}
					$i++;
				}
			}

			$db->query($sql, $params);
		}
		$this->playHooks($this->after_delete_hooks);
	}

	public static function all($idkey = true){
		return static::forge()->get_objects($idkey ? self::USE_PK : null);
	}

	//$bypass_ma = bypass_mass_assignment_control : the developper knows what he's doing
	public static function build($data = array(), $bypass_ma = false){
		$obj = new static();
		$obj->fields = $obj->describe();

		$obj->merge($data, $bypass_ma);
		$obj->playHooks($obj->after_build_hooks);
		return $obj;
	}

	public function merge($data, $bypass_ma = false){
		if(!$bypass_ma && isset(self::$mass_allowed[get_class($this)])){
			$data = array_intersect_key($data, self::$mass_allowed[get_class($this)]);
		}

		$this->fields = array_intersect_key($data + $this->fields, $this->fields);

		return $this;
	}


	public function save(){
		$this->playHooks($this->before_save_hooks);
		$db = DB::getDB();

		if( is_array($this->primary_key) ) {
			$pks = array_flip($this->primary_key);
		}
		else{
			$pks = [$this->primary_key => $this->primary_key];
		}

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
			$strategy = 'ai';//autoincrement
			foreach($this->describe() as $col => $default){
				if(!$first) $sql .= ', ';
				else $first = false;

				if( ! $this->forced_id_allowed && ( ( ! is_array($this->primary_key) && $col == $this->primary_key ) || ( is_array($this->primary_key) && $col == 'id' && isset($pks['id'])) ) ){
					if( defined('ORM_ID_AS_UID') && ORM_ID_AS_UID ){
						if( ! defined('ORM_UID_STRATEGY')){
							$strategy = 'php';
						}
						else{
							switch(ORM_UID_STRATEGY){
								default:
									$strategy = 'php';
									break;
								case 'mysql':
									$strategy = defined('DB_CONNECTOR') && DB_CONNECTOR == 'mysql' ? 'mysql' : 'php';
									break;
								case 'laravel-uuid':
									$strategy = ORM_UID_STRATEGY;
									break;
							}
						}
					}

					switch($strategy){
						case 'ai':
							$sql .= ':'.$col;
							$values[":$col"] = null;
							break;
						case 'php':
							$sql .= ':'.$col;
							$values[":$col"] = $this->$col = uniqid('', true);
							break;
						case 'laravel-uuid':
							$sql .= ':'.$col;
							$values[":$col"] = $this->$col = \Webpatser\Uuid\Uuid::generate(4)->string;
							break;
						case 'mysql':
							$suid = 'UUID()';
							if(DB_CONNECTOR == 'sqlite'){
								$suid = 'LOWER(HEX(RANDOMBLOB(18)))';
							}
							$uuidRS = $db->query('SELECT '.$suid.' as uuid');//PDO doesn't return the uuid whith lastInsertId
							$uuidRes = $db->fetchrow($uuidRS);
							$this->$col = $uuidRes['uuid'];
							$sql .= ':'.$col;
							$values[":$col"] = $this->id;
							break;
					}
				}
				else{
					$sql .= ':'.$col;
					$values[':'.$col] = array_key_exists($col, $this->fields) ? $this->$col : '';
				}
			}

			$sql .= ")";

			$res = $db->query($sql, $values);

			if( ! $this->forced_id_allowed && $strategy == 'ai'){
				if( ! is_array($this->primary_key) ){
					$this->fields[$this->primary_key] = $db->getLastId();
				}
				else if( isset($pks['id']) ) {
					$this->id = $db->getLastId();
				}
			}

			if( ! empty(self::$table_extra_ai[$this->table])) {
				$this->{self::$table_extra_ai[$this->table]} = $db->getLastId();
			}

			$this->new = false;
		}
		else{//UPDATE
			$sql = 'UPDATE `'.$this->table.'` SET ';
			$first = true;
			$values = array();
			foreach($this->describe() as $col => $default){
				if( ! isset($pks[$col]) ){//the primary key members are not updatable
					if(!$first) $sql .= ', ';
					else $first = false;
					$sql .= '`'.$col.'` = :'.$col;
					$values[':'.$col] = array_key_exists($col, $this->fields) ? $this->$col : '';
				}
			}

			$i = 1;
			$sql .= ' WHERE ';
			foreach($pks as $pk => $lambda){
				if($i > 1){
					$sql .= ' AND ';
				}
				$sql .= " `$pk` = :$pk";
				$values[":$pk"] = $this->fields[$pk];
				$i++;
			}

			$db->query($sql, $values);
		}
		$this->playHooks($this->after_save_hooks);
		//changes detection re-initializator
		if( $this->isChangesDetection() ) {
			$this->initChangesDetection(true);//force the initial copy to be reset
		}
		return $this;
	}

	public function toJSON(){
		return json_encode($this->as_array());
	}

	public function as_array(){
		$inclusions = [];
		if( ! empty($this->inclusions) ){
			foreach($this->inclusions as $name => $obj){
				if(is_array($obj)){
					$inclusions[$name] = [];
					foreach($obj as $o){
						if($o instanceof self){
							$inclusions[$name][] = $o->as_array();
						}else{
							$inclusions[$name][] = $o;
						}
					}
				}elseif(!empty($obj)){
					if($obj instanceof self){
						$inclusions[$name] = $obj->as_array();
					}else{
						$inclusions[$name] = $obj;
					}
				}
			}
		}
		return array_merge($this->fields, $inclusions);
	}

	public function add_inclusion($name, $value){
		$this->inclusions[$name] = $value;
	}

	public function clean_inclusions($name = null){
		if(!is_null($name)){
			if(isset($this->inclusions[$name])){
				unset($this->inclusions[$name]);
			}
		}
		else{
			$this->inclusions = [];
		}
		return $this;
	}

	public function describe() {
		$db = DB::getDB();

		if (empty(self::$table_desc[$this->table])) {
			foreach ($db->describe($this->table) as $data) {
				if ($data['default'] === null && !$data['null']) {
					self::$table_desc[$this->table][$data['field']] = '';
				} else {
					self::$table_desc[$this->table][$data['field']] = $data['default'];
				}

				if($data['extra'] == 'auto_increment' && $data['key'] != 'PRI') {
					self::$table_extra_ai[$this->table] = $data['field'];
				}
			}
		}


		return self::$table_desc[$this->table];
	}

	protected function pushHook($type, $hook){
		$hooks = null;
		switch($type){
			case 'before_save':
				$hooks = &$this->before_save_hooks;
				break;
			case 'after_save':
				$hooks = &$this->after_save_hooks;
				break;
			case 'before_delete':
				$hooks = &$this->before_delete_hooks;
				break;
			case 'after_delete':
				$hooks = &$this->after_delete_hooks;
				break;
			case 'after_open':
				$hooks = &$this->after_open_hooks;
				break;
			case 'after_build':
				$hooks = &$this->after_build_hooks;
				break;
		}

		$key = md5(json_encode($hook));
		if(!is_null($hooks) && !isset($hooks[$key])){
			$hooks[$key] = $hook;
		}
	}

	protected function playHooks($hooks){
		if(!empty($hooks)){
			$count = count($hooks);
			$i = 0;
			foreach($hooks as $callback){
				$i++;
				if (is_string($callback) && method_exists($this, $callback)) {
				    // Specific case if $callback is a local method name
					call_user_func([$this, $callback], $i == $count, $this);
				} elseif (is_callable($callback)) {
					call_user_func($callback, $i == $count, $this);
				}
			}
		}
	}

	protected function add_relation($type, $classto, $name, $custom = []){
		if( is_null($name) ){
			throw new \Exception('The name of the relation ['.$type.'] should not be empty');
		}

		if(array_key_exists($name, $this->describe())){
			throw new \Exception("The name of the relation $name should not be the same as a field attribute");
		}

		if( ! Relation::is_stored(get_class($this), $name) && ! Relation::is_in_progress(get_class($this), $name)){
			Relation::store_in_progress(get_class($this), $name); // We store work in progress
			// Use dynamique primary key for default col_to & col_on
			switch($type){
				case 'belongs_to':
					if(empty($custom['col_to'])){
						$o = new $classto();
						$primaryKeys = $o->get_primary_key();
						if(is_array($primaryKeys)){
							if(in_array('id', $primaryKeys) !== false){
								$custom['col_to'] = 'id';
							}
						}else{
							$custom['col_to'] = $primaryKeys;
						}
					}
					break;
				case 'has_one':
				case 'has_many':
					if(empty($custom['col_on'])){
						$primaryKeys = $this->get_primary_key();
						if(is_array($primaryKeys)){
							if(in_array('id', $primaryKeys) !== false){
								$custom['col_on'] = 'id';
							}
						}else{
							$custom['col_on'] = $primaryKeys;
						}
					}
					break;
				case 'has_many_through':
					if(empty($custom['col_on'])){
						$primaryKeys = $this->get_primary_key();
						if(is_array($primaryKeys)){
							if(in_array('id', $primaryKeys) !== false){
								$custom['col_on'] = 'id';
							}
						}else{
							$custom['col_on'] = $primaryKeys;
						}
					}

					if(empty($custom['col_to'])){
						$o = new $classto();
						$primaryKeys = $o->get_primary_key();
						if(is_array($primaryKeys)){
							if(in_array('id', $primaryKeys) !== false){
								$custom['col_to'] = 'id';
							}
						}else{
							$custom['col_to'] = $primaryKeys;
						}
					}
					break;
			}

			if( empty($custom['matchers']) && ! empty($this->default_matchers) ){
				$custom['matchers'] = $this->default_matchers;
			}

			if( empty($custom['loaders']) && ! empty($this->default_loaders) ){
				$custom['loaders'] = $this->default_loaders;
			}
			Relation::build($type, $name, get_class($this), $classto, $custom);
		}
	}

	protected function drop_relation($name) {
		if( !	Relation::drop(get_class($this), $name) ) {
			throw new \Exception("The relation called $name doesn't exist");
		}
	}

	public function belongs_to($classto, $name, $custom = []) {
		$this->add_relation('belongs_to', $classto, $name, $custom);
	}

	public function has_one($classto, $name, $custom = []) {
		$this->add_relation('has_one', $classto, $name, $custom);
	}

	public function has_many($classto, $name, $custom = []) {
		$this->add_relation('has_many', $classto, $name, $custom);
	}

	public function has_many_through($classto, $name, $custom = []) {
		$this->add_relation('has_many_through', $classto, $name, $custom);
	}

	public function rel($name, $order = null, $reload = false, $overriding = []){
		$rel = Relation::get(get_class($this), $name);
		if( is_null($rel) ){
			throw new \Exception("Unknown relation $name");
		}
		if( !array_key_exists($name, $this->inclusions) || $reload){
			$obj = $rel->fetch($this, $order, $overriding);
			$this->add_inclusion($name, $obj);
			return $obj;
		}
		else return $this->inclusions[$name];
	}

	public function set_default_matchers($default){
		$this->default_matchers = $default;
	}

	public function set_default_loaders($default){
		$this->default_loaders = $default;
	}

	//mass assignment
	public function attrs_allowed($attrs, $force = false){
		if(!empty($attrs) && (!isset(self::$mass_allowed[get_class($this)]) || $force) ){
			self::$mass_allowed[get_class($this)] = [];
			foreach($attrs as $a){
				if(array_key_exists($a, $this->describe())){
					self::$mass_allowed[get_class($this)][$a] = $a;
				}
			}
		}
		return $this;
	}

	public function jsonSerialize(){
		return $this->as_array();
	}

	//$startIntialization allows you to init the values even after a previous opening
	//example : $u = \App\Models\User::forge()->first()->enableChangesDetection(true);
	public function enableChangesDetection($startInitialization = false) {
		$this->changes_detection = true;
		if( $startInitialization ) {
			$this->initChangesDetection();
		}
		return $this;
	}

	public function disableChangesDetection() {
		$this->changes_detection = false;
		$this->inital_values = [];//reset the inital_values
		return $this;
	}

	protected function isChangesDetection() {
		return $this->changes_detection;
	}

	public function initChangesDetection($force = false) {
		if(! $this->initialized || $force){
			$this->initial_values = $this->fields;
			$this->initialized = true;
		}
	}

	//$blacklist should be indexed with the fields' names
	public function changed($blacklist = []) {
		$changed = false;
		foreach($this->fields as $k => $v) {
			if( ! isset($blacklist[$k]) && array_key_exists($k, $this->initial_values ) &&
				$v != $this->initial_values[$k]
				){
				$changed = true;
			break;
			}
		}
		return $changed;
	}

	//$blacklist should be indexed with the fields' names
	public function changes($blacklist = []) {
		$changes = [];
		foreach($this->fields as $k => $v) {
			if( ! isset($blacklist[$k]) && array_key_exists($k, $this->initial_values ) &&
				$v != $this->initial_values[$k]
				){
				$changes[$k] = [
				'before' => $this->initial_values[$k],
				'after' => $v
				];
			}
		}
		return $changes;
	}

}
