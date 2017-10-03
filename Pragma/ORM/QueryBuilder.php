<?php
namespace Pragma\ORM;

use Pragma\DB\DB;

class QueryBuilder{
	protected $table;

	protected $select = [];
	protected $where = [];
	protected $current_subs = [];
	protected $order = "";
	protected $limit = "";
	protected $group = "";
	protected $having = "";
	protected $joins = [];
	protected $inclusions = [];

	//in order to get an instance on which execute the query
	public static function forge($classname = null){
		if (!is_null($classname)) {
			$object = new $classname;
		} else {
			$object = new static();
		}

		return $object;
	}

	public function __construct($table){
		$this->table = $table;
	}

	public function select($columns = ['*']){
		$this->select = is_array($columns) ? $columns : func_get_args();
		return $this;
	}

	public function subwhere($subcallback, $bool = "and"){
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
	public function where($column, $operator, $value, $bool = "and"){
		if(empty($this->current_subs)){
			array_push($this->where, ['cond' => [$column, $operator, $value], 'bool' => $bool]);
		}
		else{
			array_push($this->current_subs[count($this->current_subs) - 1]['subs'], ['cond' => [$column, $operator, $value], 'bool' => $bool]);
		}
		return $this;
	}

	public function order($columns, $way = 'asc'){
		if( ! empty($columns) ){
			$this->order = " ORDER BY " . $columns . " " . $way;
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
		$this->limit = " LIMIT ". $start . ', ' . $limit;
		return $this;
	}

	public function join($table, $on, $type = 'inner' ){
		array_push($this->joins, ['table' => $table, 'on' => $on, 'type' => $type]);
		return $this;
	}

	public function includes($relation, $overriding = null){
		array_push($this->inclusions, ['rel' => $relation, 'overriding' => $overriding]);
		return $this;
	}

	public function get_arrays($key = null, $multiple = false, $as_array_fallback = true, $debug = false){
		$db = DB::getDB();
		$o = new static();
		$list = [];

		if(empty($this->select) && $as_array_fallback){
			$this->select(array_keys(array_intersect_key($o->as_array(), $o->describe())));
		}

		$rs = $this->get_resultset($debug, true);

		while($data = $db->fetchrow($rs)){
			if(is_null($key) || ! isset($data[$key]) ){
				$list[] = $data;
			}
			else{
				if( ! $multiple ){
					$list[$data[$key]] = $data;
				}
				else{
					$list[$data[$key]][] = $data;
				}
			}
		}

		if( !empty($list) &&  !empty($this->inclusions) ){
			foreach($this->inclusions as $i){
				$rel = Relation::get(get_class($o), $i["rel"]);
				if( is_null($rel) ){
					throw new \Exception("Unknown relation ".$i["rel"]);
				}

				$rel->load($list, 'arrays', is_null($i['overriding']) ? [] : $i['overriding']);
			}
		}

		return $list;
	}

	public function get_objects($idkey = true, $allowKeyOnId = true, $debug = false){
		$db = DB::getDB();
		$list = [];

		$this->select = [$this->table . '.*']; // force to load all fields to retrieve full object

		$rs = $this->get_resultset($debug);

		while($data = $db->fetchrow($rs)){
			$o = new static();
			$o = $o->openWithFields($data);
			if($idkey){
				$primaryKeys = $o->get_primary_key();
				if(is_array($primaryKeys)){
					// We assumed that the objects using pragma will have as primary key "id"
					if(array_key_exists('id', $primaryKeys) && isset($data['id']) && $allowKeyOnId){
						$list[$data['id']] = $o
					}else{
						$list[] = $o;
					}
				}elseif(isset($data[$primaryKeys])){
					$list[$data[$primaryKeys]] = $o;
				}else{
					$list[] = $o;
				}
			}
			else{
				$list[] = $o;
			}
		}

		if( !empty($list) && !empty($this->inclusions) ){
			foreach($this->inclusions as $i){
				$rel = Relation::get(get_class($o), $i['rel']);
				if( is_null($rel) ){
					throw new \Exception("Unknown relation ".$i['rel']);
				}
				$rel->load($list, 'objects', is_null($i['overriding']) ? [] : $i['overriding']);
			}
		}
		return $list;
	}

	public function first($debug = false){
		$db = DB::getDB();
		//force limit to 1 for optimization
		$this->limit(1);

		$this->select = [$this->table . '.*']; // force to load all fields to retrieve full object

		$rs = $this->get_resultset($debug);
		$o = null;

		$data = $db->fetchrow($rs);

		if ($data) {
			$o = new static();
			$o = $o->openWithFields($data);

			if( !empty($this->inclusions) ){
				foreach($this->inclusions as $i){
					$rel = Relation::get(get_class($o), $i["rel"]);
					if( is_null($rel) ){
						throw new \Exception("Unknown relation ".$i["rel"]);
					}
					$o->add_inclusion($i["rel"], $rel->fetch($o, null, is_null($i['overriding']) ? [] : $i['overriding']));
				}
			}
		}

		return $o;
	}

	private function get_resultset($debug = false){
		$counter_params = 1;
		$params = [];

		//SELECT
		$query = "SELECT ";
		if(empty($this->select)){
			$query .= " * ";
		}
		else{
			$this->select = array_map(function($k){
				if(trim($k) == '*' || strpos(trim($k), ' ') !== false){
					return $k;
				}elseif(strpos(trim($k), '.') !== false){
					$k = explode('.',trim($k));
					if(count($k) == 2){
						if(!(trim($k[1]) == '*' || strpos(trim($k[1]), ' ') !== false)){
							$k[1] = "`".$k[1]."`";
						}
						return "`".$k[0]."`.".$k[1];
					}else{
						return $k;
					}
					return $k = "`".implode("`.`",explode('.',trim($k)))."`";
				}else{
					return "`" . $k . "`";
				}
			}, $this->select);
			$query .= implode(", ", $this->select);
		}

		//FROM
		$query .= " FROM `" . $this->table . "`";

		//JOINS
		if(!empty($this->joins)){
			foreach($this->joins as $join){
				if( ! is_array($join['on']) ){
					throw new \Exception("Join can't be created, 'on' must be an array");
				}

				if(strpos(trim($join['table']), ' ') !== false){
					$join['table'] = explode(' ',$join['table']);
					if(count($join['table']) == 2){
						$join['table'] = "`".$join['table'][0]."` ".$join['table'][1];
					}else{
						$join['table'] = implode(' ',$join['table']);
					}
				}elseif(strpos(trim($join['table']), '.') !== false){
					$join['table'] = implode("`.`",explode('.',trim($join['table'])));
				}

				$query .= ' ' . $join['type'] . ' JOIN ' . $join['table']. ' ON ';
				if(strpos(trim($join['on'][0]), '.') !== false){
					$join['on'][0] = implode("`.`",explode('.',trim($join['on'][0])));
				}
				if(strpos(trim($join['on'][2]), '.') !== false){
					$join['on'][2] = "`".implode("`.`",explode('.',trim($join['on'][2])))."`";
				}
				$query .= '`' . $join['on'][0] . '` ' . $join['on'][1] . ' ' . $join['on'][2];
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
		$query .= $this->limit;

		if($debug){
			echo $query;
			var_dump($params);
		}$debug;

		return DB::getDB()->query($query, $params);
	}

	private function build_where($cond, &$query, &$params, &$counter_params, $first = true){
		if(isset($cond['cond'])){// We are on a real WHERE clause
			if( ! $first ){
				$query .= " ".$cond['bool']." ";
			}

			$pattern = $cond['cond'];
			switch(strtolower($pattern[1])){
				default:
					$query .= '`'.implode("`.`",explode('.',trim($pattern[0]))) . '` ' . $pattern[1] . ' :param'.$counter_params.' ';
					$params[':param'.$counter_params] = $pattern[2];
					$counter_params++;
					break;
				case 'in':
				case 'not in':
					if(is_array($pattern[2])){
						if(!empty($pattern[2])){
							$subparams = [];
							foreach($pattern[2] as $val){
								$subparams[':param'.$counter_params] = $val;
								$counter_params++;
							}
							$query .= '`'.implode("`.`",explode('.',trim($pattern[0]))) . '` ' . $pattern[1] . ' ('.implode(',',array_keys($subparams)) .') ';
							$params = array_merge($params, $subparams);
						}
						else{
							throw new \Exception("Tryin to do IN/NOT IN whereas value is empty");
						}
					}
					else{
						throw new \Exception("Trying to do IN/NOT IN whereas value is not an array");
					}
					break;
				case 'between':
					if(is_array($pattern[2])){
						if(!empty($pattern[2])){
							$current_counter = $counter_params;

							$query .= '`'.implode("`.`",explode('.',trim($pattern[0]))) . '` BETWEEN :param'.$current_counter.' AND :param'.($current_counter+1).' ';
							$params[':param'.$current_counter] = $pattern[2][0];
							$params[':param'.($current_counter+1)] = $pattern[2][1];

							$counter_params += 2;
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
						$query .= '`'.implode("`.`",explode('.',trim($pattern[0]))).'` '.strtoupper($pattern[1]).' '.strtoupper($pattern[2]);
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
				$query .= " ".$cond['bool']." ";
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
