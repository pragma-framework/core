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

	public function includes($relations){
		if( ! is_array($relations) ){
			$relations = [$relations];
		}

		foreach($relations as $relation){
			array_push($this->inclusions, $relation);
		}
		return $this;
	}

	public function get_arrays($key = null, $multiple = false, $debug = false){
		$db = DB::getDB();
		$list = [];
		$rs = $this->get_resultset($debug);

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

		if( !empty($this->inclusions) ){
			$o = new static();
			foreach($this->inclusions as $i){
				$rel = Relation::get(get_class($o), $i);
				if( is_null($rel) ){
					throw new \Exception("Unknown relation $i");
				}

				$rel->load($list, 'arrays');
			}
		}

		return $list;
	}

	public function get_objects($idkey = true, $debug = false){
		$db = DB::getDB();
		$list = [];
		$rs = $this->get_resultset($debug);

		while($data = $db->fetchrow($rs)){
			$o = new static();
			$o = $o->openWithFields($data);
			if($idkey && isset($data['id'])){
				$list[$data['id']] = $o;
			}
			else{
				$list[] = $o;
			}
		}

		if( !empty($this->inclusions) ){
			foreach($this->inclusions as $i){
				$rel = Relation::get(get_class($o), $i);
				if( is_null($rel) ){
					throw new \Exception("Unknown relation $i");
				}
				$rel->load($list, 'objects');
			}
		}
		return $list;
	}

	public function first($debug = false){
		$db = DB::getDB();
		//force limit to 1 for optimization
		$this->limit(1);
		$rs = $this->get_resultset($debug);
		$o = null;

		$data = $db->fetchrow($rs);
		if ($data) {
			$o = new static();
			$o = $o->openWithFields($data);
		}

		if( !empty($this->inclusions) ){
			foreach($this->inclusions as $i){
				$rel = Relation::get(get_class($o), $i);
				if( is_null($rel) ){
					throw new \Exception("Unknown relation $i");
				}
				$o->add_inclusion($i, $rel->fetch($o));
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

				$query .= ' ' . $join['type'] . ' JOIN `' . $join['table']. '` ON ';
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
					$query .= '`'.$pattern[0] . '` ' . $pattern[1] . ' :param'.$counter_params.' ';
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
							$query .= '`'.$pattern[0] . '` ' . $pattern[1] . ' ('.implode(',',array_keys($subparams)) .') ';
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

							$query .= '`'.$pattern[0] . '` BETWEEN :param'.$current_counter.' AND :param'.($current_counter+1).' ';
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
						$query .= '`'.$pattern[0].'` '.strtoupper($pattern[1]).' '.strtoupper($pattern[2]);
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
