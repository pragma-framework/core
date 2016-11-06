<?php
namespace Pragma\Router;

class Route{
	private $verb;
	private $path;
	private $middlewares;
	private $callback;
	private $constraints;
	private $token_by_position;
	private $values;
	private $alias;

	private $router;
	protected $groups;

	public function __construct($verb, $path, $middlewares, $callback, $router){
		$this->verb = $verb;
		$this->path = $path;
		$this->middlewares = $middlewares;
		$this->callback = $callback;
		$this->router = $router;
		$grs = $router->getRouteGroups();
		$this->groups = array();
		foreach($grs as $g){
			$this->groups = array_merge($this->groups,array_keys($g));
		}
		array_walk($this->groups, function(&$item){
			if(substr($item, 0,1) == '/'){
				$item = substr($item, 1);
			}
		});
		$this->init_constraints();
	}

	public function constraints($hash){
		if(!empty($hash)){
			foreach($hash as $token => $pattern){
				if(isset($this->constraints[$token])){
					$this->constraints[$token] = $pattern;
				}
			}
		}
		return $this;
	}

	public function alias($alias){
		if( ! $this->router->has_alias($alias) ){
			$this->alias = $alias;
			if(isset($this->router)){
				$this->router->map_alias($alias, $this);
			}
			return $this;
		}
		else{
			throw new RouterException(RouterException::ALREADY_USED_ALIAS, RouterException::ALREADY_USED_ALIAS_CODE);
		}
	}

	public function matches($path){
		$pattern = "#^".$this->path."$#";
		if(!empty($this->constraints)){
			foreach($this->constraints as $token => $subp){
				$pattern = preg_replace("#".$token."#", "(".$subp.")", $pattern);
			}
		}

		if(preg_match_all($pattern, $path, $match)){//to get values
			if(count($match) > 1){
				for($i = 1 ; $i < count($match) ; $i++){
					if(isset($this->token_by_position[$i])){
						$this->values[$this->token_by_position[$i]] = $match[$i][0];
					}
					else return false;
				}
			}
			return true;
		}
		else return false;
	}

	public function execute(){
		//here, we're sure that the route matched

		//0. Does a middleware exists ?
		$c_middleware = $this->router->getControlMiddleware();
		if( ! is_null($c_middleware)){
			call_user_func_array($c_middleware, array('route' => $this));
		}
		//1. Call the middlewares
		if(!empty($this->middlewares)){
			foreach($this->middlewares as $m){
				//call_user_func($m);
				call_user_func_array($m, array_values($this->values));
			}
		}
		//2. call the callback
		call_user_func_array($this->callback, array_values($this->values));
	}

	public function get_path_for($params){
		$path = '';
		$rebuild = $this->path;
		if(!empty($this->constraints)){
			foreach($this->constraints as $token => $subp){
				if(isset($params[substr($token, 1)])){
					$rebuild = preg_replace("#".$token."#", $params[substr($token, 1)], $rebuild);
					unset($params[substr($token, 1)]);
				}else{
					$rebuild = preg_replace("#".$token."#", '', $rebuild);
				}
			}
		}
		$path .= $rebuild;

		if( ! empty($params) ){//params remain, they will be added as simple params
			$path .= '?'.http_build_query($params);
		}

		return $path;
	}

	private function init_constraints(){
		$this->constraints = array();
		$this->token_by_position = array();
		$this->values = array();
		$pattern = "/(:[a-zA-Z_-]+)\/?/";
		$match = null;
		if(preg_match_all($pattern, $this->path, $match)){
			if(!empty($match[1])){
				$idx = 1;
				foreach($match[1] as $token){
					$this->constraints[$token] = "[^\s\/]*";
					$this->token_by_position[$idx] = $token;//keeps the order
					$this->values[$token] = '';//keeps the order
					$idx++;
				}
			}
		}

		//sort constraints by $token length
		uksort($this->constraints, function($a, $b){
			$al = strlen($a);
			$bl = strlen($b);

			if($al == $bl) return 0;
			else return $al > $bl ? -1 : 1;
		});
	}

	public function getPath(){
		return $this->path;
	}

	public function getVerb(){
		return $this->verb;
	}

	public function getAlias(){
		return $this->alias;
	}

	public function getGroups(){
		return $this->groups;
	}

	public function getValue($ident){
		if (isset($this->values[$ident])) {
			return $this->values[$ident];
		}

		return null;
	}

	public function __debugInfo(){
		return array(
			'path' => $this->getPath(),
			'verb' => $this->getVerb(),
			'alias' => $this->getAlias(),
			'groups' => $this->getGroups(),
		);
	}
}
