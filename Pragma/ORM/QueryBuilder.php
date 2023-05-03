<?php
namespace Pragma\ORM;

use Pragma\DB\DB;
use Pragma\Exceptions\QueryException;

class QueryBuilder{
	protected $table;

	protected $select = [];
	protected $where = [];
	protected $current_subs = [];
	protected $order = "";
	protected $limit = "";
	protected $limit_start = 0;
	protected $group = "";
	protected $having = "";
	protected $joins = [];
	protected $embedded = [];

	const ARRAYS = 1;
	const OBJECTS = 2;

	const USE_PK = -1;

	protected $db_table_alias = null;
	protected $escape = true;
	protected static $escapeChar = "`";

	//in order to get an instance on which execute the query
	public static function forge($classname = null, $db_table_alias = null, $escape = true){
		if (!is_null($classname)) {
			$object = new $classname;
		} else if(get_called_class() != "Pragma\ORM\QueryBuilder") {
			$object = new static();
		}
		else {
			throw new \Exception("QueryBuilder can't be built without classname");
		}

		$object->db_table_alias = $db_table_alias;
		$object->escape = $escape;

		return $object;
	}

	public function __construct($table) {
		$this->table = $table;

		if(DB::getDB()->getConnector() == DB::CONNECTOR_PGSQL){
			self::$escapeChar = "\"";
		}
	}

	public function unescape() {
		$this->escape = false;
		return $this;
	}

	public function select($columns = ['*']){
		$this->select = is_array($columns) ? $columns : func_get_args();
		return $this;
	}

	public function subwhere($subcallback, $bool = "AND"){
		array_push($this->current_subs, ["subs" => [], "bool" => $bool]);
		$subcallback($this);
		$sub = array_pop($this->current_subs);
		if(empty($this->current_subs)){
			array_push($this->where, $sub);
		}
		else{
			array_push($this->current_subs[count($this->current_subs) - 1]['subs'], $sub );
		}

		return $this;
	}
	public function where($column, $operator, $value, $bool = "AND", $usePDO = true){
		if(empty($this->current_subs)){
			array_push($this->where, ['cond' => [$column, $operator, $value], 'bool' => $bool, 'use_pdo' => $usePDO]);
		}
		else{
			array_push($this->current_subs[count($this->current_subs) - 1]['subs'], ['cond' => [$column, $operator, $value], 'bool' => $bool, 'use_pdo' => $usePDO]);
		}
		return $this;
	}

	public function order($columns, $way = 'ASC'){
		if( ! empty($columns) ){
			$this->order = " ORDER BY " . $columns . " " . strtoupper($way);
		}
		return $this;
	}

	public function group($columns){
		$this->group = " GROUP BY ". $columns;
		return $this;
	}

	public function having($left, $operator, $value){
		// TODO: using PDO value binding on $value would be welcome
		$this->having = " HAVING " . $left . " " . $operator . " " . $value;
		return $this;
	}

	public function limit($limit, $start = 0){
		$this->limit = $limit;
		$this->limit_start = $start;
		return $this;
	}

	public function join($table, $on, $type = 'INNER' ){
		array_push($this->joins, ['table' => $table, 'on' => $on, 'type' => $type]);
		return $this;
	}

	public function includes($relation, $overriding = []){
		array_push($this->embedded, ['rel' => $relation, 'overriding' => $overriding]);
		return $this;
	}

	public function get_arrays($key = null, $multiple = false, $as_array_fallback = true, $openedCallback = null, $debug = false){
		return $this->build_arrays_of(self::ARRAYS, $key, $multiple, $as_array_fallback, null, $openedCallback, $debug);
	}

	public function get_objects($key = self::USE_PK, $multiple = false, $as_array_fallback = true, $allowKeyOnId = true, $rootCallback = null, $openedCallback = null, $debug = false){
		if (!is_null($key) && $key === self::USE_PK) {
			$o = new static();
			$primaryKeys = $o->get_primary_key();
			if (!is_array($primaryKeys)) {
				$key = $primaryKeys;
			} elseif ($allowKeyOnId) {
				$key = 'id';
			} else {
				$key = null;
			}
		}

		return $this->build_arrays_of(self::OBJECTS, $key, $multiple, $as_array_fallback, $rootCallback, $openedCallback, $debug);
	}

	/*
	* $rootCallback : only for objects -> called just after obtaining an instance of the object (new static()). Useful for skip hooks or other methods before handling data
	* $openedCallback : for both objects and arrays : called after handling data
	*/
	private function build_arrays_of($type, $key = null, $multiple = false, $as_array_fallback = true, $rootCallback = null, $openedCallback = null, $debug = false){
		if( ! in_array($type, [self::ARRAYS, self::OBJECTS])){
			throw new \Exception("Unknown type of data : ".$type);
		}

		if( $type == self::OBJECTS && get_called_class() == "Pragma\ORM\QueryBuilder"){
				throw new \Exception("QueryBuilder can't be used without a classname context, please consider using the forge method before");
		}

		$db = DB::getDB();
		$list = [];

		if($type==self::OBJECTS){
			$alias = is_null($this->db_table_alias) ? $this->table : $this->db_table_alias;
			$this->select = [$alias . '.*']; // force to load all fields to retrieve full object
		}
		else if(empty($this->select) && $as_array_fallback){
			$alias = is_null($this->db_table_alias) ? $this->table : $this->db_table_alias;
			$o = new static();
			$fields = array_keys(array_intersect_key($o->as_array(), $o->describe()));

			$aliased_fields = array_map(function($field) use ($alias) {
				return sprintf('%s.%s', $alias, $field);
			}, $fields);

			$this->select($aliased_fields);
		}

		try {
			$rs = $this->get_resultset($debug);
			while($data = $db->fetchrow($rs)) {
				switch($type){
					case self::ARRAYS:
						$val = $data;
						if(is_callable($openedCallback)) {
							// good to know : I first tried to call the function with call_user_function but it gives the params as values instead of references
							$openedCallback($val);
						}
						break;
					case self::OBJECTS:
						$val = new static();
						if(is_callable($rootCallback)) {
							// good to know : I first tried to call the function with call_user_function but it gives the params as values instead of references
							$rootCallback($val);
						}
						$val = $val->openWithFields($data);
						if(is_callable($openedCallback)) {
							// good to know : I first tried to call the function with call_user_function but it gives the params as values instead of references
							$openedCallback($val);
						}
						break;
				}
				if(is_null($key) || ! isset($data[$key]) ){
					$list[] = $val;
				}
				else{
					if(DB::getDB()->getConnector() == DB::CONNECTOR_PGSQL && defined('ORM_UID_STRATEGY') && ORM_UID_STRATEGY == 'mysql'){
						$data[$key] = trim($data[$key]);
					}
					if( ! $multiple ){
						$list[$data[$key]] = $val;
					}
					else{
						$list[$data[$key]][] = $val;
					}
				}
			}
			$rs->closeCursor();

			if( !empty($list) &&  !empty($this->embedded) ){
				if(empty($o)){
					$o = new static();
				}
				foreach($this->embedded as $i){
					$rel = Relation::get(get_class($o), $i["rel"]);
					if( is_null($rel) ){
						throw new \Exception("Unknown relation ".$i["rel"]);
					}

					$rel->load($list, $type == self::ARRAYS ? 'arrays' : 'objects', isset($i['overriding']) ? $i['overriding'] : []);
				}
			}
			return $list;
		} catch (QueryException $e) {
			switch($e->getCode()){
				case QueryException::EMPTY_IN_VALUE_ERROR:
				default:
					return [];
			}
		}
	}

	public function first($debug = false){
		if(get_called_class() == "Pragma\ORM\QueryBuilder") {
			throw new \Exception("QueryBuilder can't be used without a classname context, please consider using the forge method before");
		}
		$db = DB::getDB();
		//force limit to 1 for optimization
		$this->limit(1);

		$alias = is_null($this->db_table_alias) ? $this->table : $this->db_table_alias;
		$this->select = [$alias. '.*'];

		try{
			$rs = $this->get_resultset($debug);
			$o = null;

			$data = $db->fetchrow($rs);
			$rs->closeCursor();
			if ($data) {
				$o = new static();
				$o = $o->openWithFields($data);

				if( !empty($this->embedded) ){
					foreach($this->embedded as $i){
						$rel = Relation::get(get_class($o), $i["rel"]);
						if( is_null($rel) ){
							throw new \Exception("Unknown relation ".$i["rel"]);
						}
						$o->add_inclusion($i["rel"], $rel->fetch($o, null, isset($i['overriding']) ? $i['overriding'] : []));
					}
				}
			}
			return $o;
		} catch (QueryException $e) {
			switch($e->getCode()){
				case QueryException::EMPTY_IN_VALUE_ERROR:
				default:
					return null;
			}
		}
	}

	public function get_resultset($debug = false){
		$counter_params = 1;
		$params = [];

		$e = $this->escape ? self::$escapeChar : "";

		//SELECT
		$query = "SELECT ";
		if(empty($this->select)){
			$query .= " * ";
		}
		else{
			$this->select = array_map(function($k) use ($e) {
				$k = str_replace($e, '', $k);
				if(trim($k) == '*' || strpos(trim($k), ' ') !== false){
					return $k;
				}elseif(strpos(trim($k), '.') !== false){
					$k = explode('.',trim($k));
					if(count($k) == 2){
						if(!(trim($k[1]) == '*' || strpos(trim($k[1]), ' ') !== false)){
							$k[1] = $e.$k[1].$e;
						}
						return $e.$k[0]."$e.".$k[1];
					}else{
						return $k;
					}
					return $k = $e.implode("$e.$e",explode('.',trim($k))).$e;
				}else{
					return $e . $k . $e;
				}
			}, $this->select);

			$query .= implode(", ", $this->select);
		}

		//FROM
		$alias = ! is_null($this->db_table_alias) ? ' '.$this->db_table_alias : '';
		$query .= " FROM $e" . $this->table . $e . $alias;

		//JOINS
		if(!empty($this->joins)){
			foreach($this->joins as $join){
				if( ! is_array($join['on']) ){
					throw new \Exception("Join can't be created, 'on' must be an array");
				}

				if(strpos(trim($join['table']), ' ') !== false){
					$join['table'] = explode(' ',$join['table']);
					if(count($join['table']) == 2){
						$join['table'] = $e.$join['table'][0]."$e AS ".$join['table'][1];
					}elseif(count($join['table']) == 3 && strtolower($join['table'][1]) == 'as'){
						$join['table'] = $e.$join['table'][0]."$e AS ".$join['table'][2];
					}else{
						$join['table'] = implode(' ',$join['table']);
					}
				}elseif(strpos(trim($join['table']), '.') !== false){
					$join['table'] = implode("$e.$e",explode('.',trim($join['table'])));
				}

				$query .= ' ' . $join['type'] . ' JOIN ' . $join['table']. ' ON ';
				if (!is_array($join['on'][0])) {
					$join['on'] = [$join['on']];
				}
				$first = true;
				foreach ($join['on'] as $on) {
					if (! is_array($on)) {
						throw new \Exception("Join can't be created, 'on' must be an array");
					}
					if (strpos(trim($on[0]), '.') !== false) {
						$on[0] = implode("$e.$e", explode('.', trim($on[0])));
					}
					if (strpos(trim($on[2]), '.') !== false) {
						$on[2] = $e.implode("$e.$e", explode('.', trim($on[2]))).$e;
					}
					$query .= ($first ? '' : ' AND ').$e . $on[0] . "$e " . $on[1] . ' ' . $on[2].' ';
					$first = false;
				}
			}
		}

		//WHERE
		if(!empty($this->where)){
			$query .= " WHERE ";
			$first = true;
			foreach($this->where as $cond){
				$this->build_where($cond, $query, $params, $counter_params, $first);
				if($first){
					$first = false;
				}
			}
		}

		//GROUP
		$query .= $this->group;

		//HAVING
		$query .= $this->having;

		//ORDER
		$query .= $this->order;

		//LIMIT
		if(!empty($this->limit)){
			$query .= " LIMIT :pragma_limit_start".(DB::getDB()->getConnector() == DB::CONNECTOR_PGSQL ? " OFFSET" : ",")." :pragma_limit ";
			$params[DB::getDB()->getConnector() == DB::CONNECTOR_PGSQL ? ':pragma_limit' : ':pragma_limit_start'] = [$this->limit_start, \PDO::PARAM_INT];
			$params[DB::getDB()->getConnector() == DB::CONNECTOR_PGSQL ? ':pragma_limit_start' : ':pragma_limit'] = [$this->limit, \PDO::PARAM_INT];
		}

		if($debug){
			echo $query;
			var_dump($params);
		}$debug;

		return DB::getDB()->query($query, $params);
	}

	private function build_where($cond, &$query, &$params, &$counter_params, $first = true){
		if(isset($cond['cond'])){// We are on a real WHERE clause
			if( ! $first ){
				$query .= " ".strtoupper($cond['bool'])." ";
			}

			$e = $this->escape ? self::$escapeChar : "";

			$pattern = $cond['cond'];
			switch(strtolower($pattern[1])){
				default:
					if (array_key_exists('use_pdo', $cond) && !$cond['use_pdo']) {
						$query .= $e.implode("$e.$e", explode('.', trim($pattern[0]))) . "$e " . $pattern[1] . ' '.$pattern[2].' ';
					} else {
						$query .= $e.implode("$e.$e",explode('.',trim($pattern[0]))) . "$e " . $pattern[1] . ' :param'.$counter_params.' ';
						$params[':param'.$counter_params] = $pattern[2];
						$counter_params++;
					}
					break;
				case 'in':
				case 'not in':
					if(is_array($pattern[2])){
						if(!empty($pattern[2])){
							if (array_key_exists('use_pdo', $cond) && !$cond['use_pdo']) {
								$query .= $e.implode("$e.$e", explode('.', trim($pattern[0]))) . "$e " . strtoupper($pattern[1]) . ' ('.implode(', ', $pattern[2]) .') ';
							} else {
								$subparams = [];
								foreach($pattern[2] as $val){
									$subparams[':param'.$counter_params] = $val;
									$counter_params++;
								}
								$query .= $e.implode("$e.$e",explode('.',trim($pattern[0]))) . "$e " . strtoupper($pattern[1]) . ' ('.implode(', ',array_keys($subparams)) .') ';
								$params = array_merge($params, $subparams);
							}
						}
						else{
							throw new QueryException(QueryException::EMPTY_IN_VALUE_MSG, QueryException::EMPTY_IN_VALUE_ERROR);
						}
					}
					else{
						throw new \Exception("Trying to do IN/NOT IN whereas value is not an array");
					}
					break;
				case 'between':
					if(is_array($pattern[2])){
						if(!empty($pattern[2])){
							if (array_key_exists('use_pdo', $cond) && !$cond['use_pdo']) {
								$query .= $e.implode("$e.$e", explode('.', trim($pattern[0]))) . $e.' BETWEEN '.$pattern[2][0].' AND '.$pattern[2][1].' ';
							} else {
								$current_counter = $counter_params;

								$query .= $e.implode("$e.$e",explode('.',trim($pattern[0]))) . $e.' BETWEEN :param'.$current_counter.' AND :param'.($current_counter+1).' ';
								$params[':param'.$current_counter] = $pattern[2][0];
								$params[':param'.($current_counter+1)] = $pattern[2][1];

								$counter_params += 2;
							}
						}
						else{
							throw new \Exception("Trying to do BETWEEN whereas value is empty");
						}
					}
					else{
						throw new \Exception("Trying to do BETWEEN whereas value is not an array");
					}
					break;
				case 'is':
				case 'is not':
					if (in_array(strtolower($pattern[2]), ['true', 'false', 'unknown', 'null'])) {
						$query .= $e.implode("$e.$e",explode('.',trim($pattern[0])))."$e ".strtoupper($pattern[1]).' '.strtoupper($pattern[2]);
					} else {
						throw new \Exception("Trying to do IS/IS NOT whereas value is not TRUE, FALSE, UNKNOW or NULL");
					}
					break;
			}

			if( $first ) {
				$first = false;
			}
		}
		else if(isset($cond['subs'])){//sub conditions
			if( ! $first ){
				$query .= " ".strtoupper($cond['bool'])." ";
			}

			$query .= ' ( ';
			$first = true;
			foreach($cond['subs'] as $sub){
				$this->build_where($sub, $query, $params, $counter_params, $first);
				if($first){
					$first = false;
				}
			}
			$query .= ' ) ';

			if( $first ) {
				$first = false;
			}
		}
		return $query;
	}
}
