<?php
namespace Pragma\ORM;

class Relation{
	protected $name;
	protected $type;
	protected $class_on;
	protected $class_to;
	protected $cols = [];

	protected $sub_relation = null;

	protected static $all_relations = [];

	public static function is_stored($classon, $name){
		return isset(static::$all_relations[$classon][$name]);
	}

	public static function get($classon, $name){
		return static::is_stored($classon, $name) ? static::$all_relations[$classon][$name] : null;
	}

	public static function build($type, $name, $classon, $classto, $custom = []){
		if(isset(static::$all_relations[$classon][$name])){
			return static::$all_relations[$classon][$name];
		}
		else{
			$cols = [];
			$add_pk = isset($custom['add_pk']) ? $custom['add_pk'] : null;
			if( ! empty($add_pk ) ){
				if( ! is_array($add_pk) ){
					$add_pk = [$add_pk => $add_pk];
				}
				$cols = array_merge($cols, $add_pk);
			}
			if(empty($type)){
				throw new \Exception("Relation type should not be empty");
			}

			$on = $to = $sub = null;

			//getting on / to / through values
			switch($type){
				default:
					throw new \Exception("Unknown relation type");
					break;
				case 'belongs_to':
					$cols[empty($custom['col_on']) ? static::extract_ref($classto) : $custom['col_on']] = empty($custom['col_to']) ? 'id' : $custom['col_to'];
					break;
				case 'has_one':
				case 'has_many':
					$cols[empty($custom['col_on']) ? 'id' : $custom['col_on']] = empty($custom['col_to']) ? static::extract_ref($classon) : $custom['col_to'];
					break;
				case 'has_many_through':
					//through is the classname of the relation table
					$through = empty($custom['through_class']) ? static::extract_class_through($classon, $classto) : $custom['through_class'];
					//left
					$left = $right = [];
					$left['on'] = empty($custom['col_on']) ? 'id' : $custom['col_on'];
					$left['to'] = empty($custom['col_to']) ? static::extract_ref($classon) : $custom['col_to'];
					//right
					$right['on'] = empty($custom['col_through_on']) ? static::extract_ref($classto) : $custom['col_through_on'];
					$right['to'] = empty($custom['col_to']) ? 'id' : $custom['col_to'];
					$sub = ['through' => $through,
									'left' => $left,
									'right' => $right,
									'add_pk' => isset($custom['add_pk']) ? $custom['add_pk'] : null];
					break;
			}

			$relation = new Relation();
			$relation->name = $name;
			$relation->set_type($type);
			$relation->set_class_on($classon);
			$relation->set_class_to($classto);

			if(!is_null($sub)){
				$relation->set_sub_relation($sub);
			}
			else{
				$relation->set_cols($cols);
			}


			static::$all_relations[$classon][$name] = $relation;

		}
	}

	public static function extract_ref($classname){
		$basename = static::extract_name($classname);
		if(!empty($basename)){
			//a classname should be named in English, in singular
			return $basename . '_id';
		}
		return null;
	}


	public static function extract_name($classname = ""){
		$split = explode( "\\", $classname);
		$base = $classname;
		if(!empty($split) && is_array($split)){
			$base = end($split);
		}
		$words = preg_split('/(?=[A-Z])/', $base);

		$final = '';
		if(!empty($words) && is_array($words)){
			$first = true;
			foreach($words as $w){
				if(empty($w)){
					continue;
				}
				if( ! $first){
					$final .= '_';
				}
				else{
					$first = false;
				}
				$final .= strtolower($w);
			}
		}
		else{
			$final = $base;
		}

		return $final;
	}

	public static function extract_namespace($classname){
		$split = explode( "\\", $classname);
		$namespace = "";
		if(!empty($split) && is_array($split)){
			//shift the last element
			array_pop($split);
			if( ! empty($split) ){
				$namespace = implode("\\", $split);
			}
		}
		return $namespace."\\";
	}

	public static function extract_class_through($classon, $classto){
		return static::extract_namespace($classon).ucfirst(static::extract_name($classon)) . ucfirst(static::extract_name($classto));
	}

	public function add_through(Relation $relation){

	}

	protected function set_type($type){
		$this->type = $type;
	}

	protected function set_class_on($class_on){
		$this->class_on = $class_on;
	}

	protected function set_col_on($col_on){
		$this->col_on = $col_on;
	}

	protected function set_class_to($class_to){
		$this->class_to = $class_to;
	}

	protected function set_col_to($col_to){
		$this->col_to = $col_to;
	}

	protected function set_sub_relation($sub){
		$this->sub_relation = $sub;
	}
	protected function set_cols($cols){
		$this->cols = $cols;
	}


	public function fetch($model, $order = null){
		$remote = new $this->class_to();
		switch($this->type){
			case 'belongs_to':
			case 'has_one':
			case 'has_many':
				$qb = $this->class_to::forge();
				if( ! is_null($order) ){
					if( is_array($order) ){
						$qb->order($order[0], $order[1]);
					}
					else{
						$qb->order($order);
					}
				}
				foreach($this->cols as $on => $to){
					if( ! array_key_exists($on, $model->describe()) ){
					 	throw new \Exception("Fetching relation - unknown column 'on' : $on in source model");
					}
					if( ! array_key_exists($to, $remote->describe()) ){
					 	throw new \Exception("Fetching relation - unknown column 'to' : $to in remote model");
					}
					$qb->where($to, '=', $model->$on);
				}
				return $this->type == 'has_one' || $this->type == 'belongs_to' ? $qb->first() : $qb->get_objects();
				break;
			case 'has_many_through':
				$results = [];
				if( empty($this->sub_relation['left']) || empty($this->sub_relation['right']) || empty($this->sub_relation['through'])){
					throw \Exception("Missing part(s) of sub_relation ".$this->name);
				}

				$add_pk = isset($this->sub_relation['add_pk']) ? $this->sub_relation['add_pk'] : null;
				if( ! empty($add_pk ) ){
					if( ! is_array($add_pk) ){
						$add_pk = [$add_pk => $add_pk];
					}
				}

				$qb1 = $this->sub_relation['through']::forge();
				$lon = $this->sub_relation['left']['on'];
				$qb1->where($this->sub_relation['left']['to'], '=', $model->$lon);
				foreach($add_pk as $on => $to){
					$qb1->where($to, '=', $model->$on);
				}

				$qb1->select([$this->sub_relation['right']['on']], $this->sub_relation['right']['on'], true);

				$remote_ids = $qb1->get_arrays($this->sub_relation['right']['on']);

				if(empty($remote_ids)){
					return [];
				}


				$qb2 = $this->class_to::forge();
				if( ! is_null($order) ){
					if( is_array($order) ){
						$qb2->order($order[0], $order[1]);
					}
					else{
						$qb2->order($order);
					}
				}
				$ron = $this->sub_relation['right']['on'];
				$qb2->where($this->sub_relation['right']['to'], 'in', array_keys($remote_ids));
				foreach($add_pk as $on => $to){
					$qb2->where($to, '=', $model->$on);
				}

				return $qb2->get_objects();

				break;
		}
	}
}
