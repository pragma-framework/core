<?php
namespace Pragma\ORM;

class Relation{
	protected $name;
	protected $type;
	protected $class_on;
	protected $class_to;
	protected $cols = [];
	protected $conditionnal_loading = [];

	protected $sub_relation = null;

	protected static $all_relations = [];
	protected static $progress_work = [];

	public static function is_stored($classon, $name){
		return isset(static::$all_relations[$classon][$name]);
	}

	public static function get($classon, $name){
		return static::is_stored($classon, $name) ? static::$all_relations[$classon][$name] : null;
	}

	public static function getAll($classon = null){
		if(empty($classon)){
			return static::$all_relations;
		}else{
			return isset(static::$all_relations[$classon]) ? static::$all_relations[$classon] : [];
		}
	}

	public static function build($type, $name, $classon, $classto, $custom = []){
		if(isset(static::$all_relations[$classon][$name])){
			self::unstore_in_progress($classon, $name); // Unstore work in progress
			return static::$all_relations[$classon][$name];
		}
		else{
			$cols = [];
			$matchers = isset($custom['matchers']) ? $custom['matchers'] : null;
			if( ! empty($matchers ) ){
				if( ! is_array($matchers) ){
					$matchers = [$matchers => $matchers];
				}
			}
			$cols['matchers'] = $matchers;
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
					$cols['on'] = empty($custom['col_on']) ? static::extract_ref($classto) : $custom['col_on'];
					$cols['to'] = empty($custom['col_to']) ? 'id' : $custom['col_to'];
					break;
				case 'has_one':
				case 'has_many':
					$cols['on'] = empty($custom['col_on']) ? 'id' : $custom['col_on'];
					$cols['to'] = empty($custom['col_to']) ? static::extract_ref($classon) : $custom['col_to'];
					break;
				case 'has_many_through':
					//through is the classname of the relation table
					$through = empty($custom['through_class']) ? static::extract_class_through($classon, $classto) : $custom['through_class'];
					//left
					$left = $right = [];
					$left['on'] = empty($custom['col_on']) ? 'id' : $custom['col_on'];
					$left['to'] = empty($custom['col_through_to']) ? static::extract_ref($classon) : $custom['col_through_to'];
					//right
					$right['on'] = empty($custom['col_through_on']) ? static::extract_ref($classto) : $custom['col_through_on'];
					$right['to'] = empty($custom['col_to']) ? 'id' : $custom['col_to'];
					$sub = ['through' => $through,
									'left' => $left,
									'right' => $right,
									'matchers' => isset($custom['matchers']) ? $custom['matchers'] : null];
					break;
			}

			$relation = new Relation();
			$relation->name = $name;
			$relation->conditionnal_loading = isset($custom['loaders']) ? $custom['loaders'] : null;
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
			self::unstore_in_progress($classon, $name); // Unstore work in progress
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

	public function get_name(){
		return $this->name;
	}

	public function get_type(){
		return $this->type;
	}
	public function get_class_on(){
		return $this->class_on;
	}
	public function get_class_to(){
		return $this->class_to;
	}
	public function get_sub_relation(){
		return $this->sub_relation;
	}
	public function get_cols(){
		return $this->cols;
	}

	public function fetch($model, $order = null, $overriding = [], $only_one = false){
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

				if($only_one){
					$qb->limit(1);
				}

				if( ! array_key_exists($this->cols['on'], $model->describe()) ){
				 	throw new \Exception("Fetching relation - unknown column 'on' : ".$this->cols['on']." in source model ".get_class($model));
				}
				if( ! array_key_exists($this->cols['to'], $remote->describe()) ){
				 	throw new \Exception("Fetching relation - unknown column 'to' : ".$this->cols['to']." in remote model ".get_class($remote));
				}
				$on = $this->cols['on'];
				$qb->where($this->cols['to'], '=', $model->$on);

				$matchers = $this->cols['matchers'];
				if(isset($overriding['matchers'])){
					$matchers = $overriding['matchers'];
					if( ! empty($matchers ) ){
						if( ! is_array($matchers) ){
							$matchers = [$matchers => $matchers];
						}
					}
					$cols['matchers'] = $matchers;
				}

				if( ! empty($matchers)){
					foreach($matchers as $on => $to){
						$qb->where($to, '=', $model->$on);
					}
				}

				return $this->type == 'has_one' || $this->type == 'belongs_to' ? $qb->first() : $qb->get_objects();
				break;
			case 'has_many_through':
				$results = [];
				if( empty($this->sub_relation['left']) || empty($this->sub_relation['right']) || empty($this->sub_relation['through'])){
					throw \Exception("Missing part(s) of sub_relation ".$this->name);
				}

				$matchers = isset($this->sub_relation['matchers']) ? $this->sub_relation['matchers'] : null;
				if(isset($overriding['matchers'])){
					$matchers = $overriding['matchers'];
				}

				if( ! empty($matchers ) ){
					if( ! is_array($matchers) ){
						$matchers = [$matchers => $matchers];
					}
				}

				$qb1 = $this->sub_relation['through']::forge();
				if($only_one){
					$qb1->limit(1);
				}
				$lon = $this->sub_relation['left']['on'];
				$qb1->where($this->sub_relation['left']['to'], '=', $model->$lon);
				foreach($matchers as $on => $to){
					$qb1->where($to, '=', $model->$on);
				}

				$remote_ids = $qb1->select([$this->sub_relation['right']['on']])->get_arrays($this->sub_relation['right']['on']);

				if(empty($remote_ids)){
					return [];
				}

				$qb2 = $this->class_to::forge();
				if($only_one){
					$qb2->limit(1);
				}

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
				foreach($matchers as $on => $to){
					$qb2->where($to, '=', $model->$on);
				}

				return $qb2->get_objects();

				break;
		}
	}

	public function load(&$models, $type = 'objects', $overriding = []){
		switch($this->type){
			case 'belongs_to':
			case 'has_one':
			case 'has_many':
				//1 : fetch the reference
				$refs = [];
				$on = $this->cols['on'];
				foreach($models as $m){
					$val = $type == 'objects' ? $m->$on : $m[$on];
					$refs[$val] = $val;
				}

				if( ! empty($refs) ){
					$qb = $this->class_to::forge();
					$qb->where($this->cols['to'], 'in', $refs);

					$loaders = isset($overriding['loaders']) ? $overriding['loaders'] : $this->conditionnal_loading;
					if( ! empty($loaders) ){
						call_user_func($loaders, $qb);
					}
					$results = $qb->get_objects();

					$pairing = [];
					if(!empty($results)){
						$to = $this->cols['to'];
						foreach($results as $id => $r){
							$pairing[$r->$to][$id] = $r;
						}
					}

					//2 : complete the models
					foreach($models as &$m){
						$ref = $type == 'objects' ? $m->$on : $m[$on];

						if(isset($pairing[$ref])){
							if($type == 'objects'){
								$m->add_inclusion($this->name, $this->type == 'has_many' ? $pairing[$ref] : current($pairing[$ref]));
							}
							else{
								switch($this->type){
									case 'has_many':
										$asarray = [];
										foreach($pairing[$ref] as $id => $rel){
											$asarray[] = $rel->as_array();
										}
										$m[$this->name] = $asarray;
										break;
									default:
										$m[$this->name] = current($pairing[$ref])->as_array();
										break;
								}
							}
						}
						else{
							if($type == 'objects'){
								$m->add_inclusion($this->name, $this->type == 'has_many' ? [] : null);
							}
							else{
								$m[$this->name] = $this->type == 'has_many' ? [] : null;
							}
						}
					}
				}
				break;
			case 'has_many_through':
				//1 : fetch the left reference
				$refs = [];
				$on = $this->sub_relation['left']['on'];

				$loading_left = $loading_right = null;
				$source = isset($overriding['loaders']) ? $overriding['loaders'] : $this->conditionnal_loading;
				if( !empty($source) ){
					if( ! is_array($source) ){
						$loading_left = $loading_right = $source;
					}
					else{
						if( isset($source['left']) ){
							$loading_left = $source['left'];
						}

						if( isset($source['right']) ){
							$loading_right = $source['right'];
						}
					}
				}

				foreach($models as $m){
					$val = $type == 'objects' ? $m->$on : $m[$on];
					$refs[$val] = $val;
				}

				if( ! empty($refs) ){
					$qb1 = $this->sub_relation['through']::forge();
					$qb1->where($this->sub_relation['left']['to'], 'IN', $refs);

					if( ! is_null($loading_left) ){
						call_user_func($loading_left, $qb1);
					}

					$pairing = $qb1->select([$this->sub_relation['right']['on'], $this->sub_relation['left']['to']])->get_arrays($this->sub_relation['left']['to'], true);//true : multiple

					$ins = [];
					if(!empty($pairing)){
						foreach($pairing as $left => $rights){
							foreach($rights as $r){
								$ins[$r[$this->sub_relation['right']['on']]] = $r[$this->sub_relation['right']['on']];
							}
						}
					}

					$remotes = [];
					if( ! empty($pairing) ){
						$qb2 = $this->class_to::forge();
						$qb2->where($this->sub_relation['right']['to'], 'in', $ins);
						if( ! is_null($loading_right) ){
							call_user_func($loading_right, $qb2);
						}
						$remotes = $qb2->get_objects();
					}

					foreach($models as &$m){
						$ref = $type == 'objects' ? $m->$on : $m[$on];//left

						$loaded = [];

						if(isset($pairing[$ref])){
							foreach($pairing[$ref] as $pair){
								if(isset($remotes[$pair[$this->sub_relation['right']['on']]])){
									$loaded[$pair[$this->sub_relation['right']['on']]] = $type == 'objects' ? $remotes[$pair[$this->sub_relation['right']['on']]] : $remotes[$pair[$this->sub_relation['right']['on']]]->as_array();
								}
							}
						}

						if($type == 'objects'){
							$m->add_inclusion($this->name, $loaded);
						}
						else{
							$m[$this->name] = $loaded;
						}
					}
				}
				break;
		}
	}

	public static function store_in_progress($classon, $name){
		static::$progress_work[$classon][$name] = true;
	}
	public static function is_in_progress($classon, $name){
		return isset(static::$progress_work[$classon][$name]) ? static::$progress_work[$classon][$name] : false;
	}
	public static function unstore_in_progress($classon, $name){
		unset(static::$progress_work[$classon][$name]);
	}
}
