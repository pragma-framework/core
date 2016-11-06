<?php
namespace Pragma\Router;

use Pragma\Router\Request;

class Router{
	private $mapping = array();
	private $alias_mapping = array();
	private $control_callback = null;

	private static $router = null;

	private $routeGroups = array();

	protected static $fullUrl = false;
	protected static $domain = '';

	protected $route = null;

	private function map($verb, $args){
		if(count($args) > 1){
			list($groupPattern, $middlewares) = $this->processGroups();
			$path = $args[0];
			$callback = $args[count($args) - 1];
			//$middlewares = array();
			if(count($args) > 2){
				for($i = 1 ; $i < count($args) - 1 ; $i ++){
					$middlewares[] = $args[$i];
				}
			}
			$r = new Route($verb, $groupPattern.$path, $middlewares, $callback, $this);
			$this->mapping[$verb][] = $r;
			return $r;
		}
		else{
			return null;
		}
	}


	public static function getInstance(){
		if(is_null(self::$router)){
			self::$router = new Router();
		}
		return self::$router;
	}

	public function map_alias($alias, Route $route){
		$this->alias_mapping[$alias] = $route;
	}

	public function get(){
		$args = func_get_args();
		$r = $this->map('get', $args);
		if(! is_null($r)) return $r;
		else{
			throw new RouterException(RouterException::WRONG_MAPPING, RouterException::GET_CONFIG_ERROR);
		}
	}

	public function post(){
		$args = func_get_args();
		$r = $this->map('post', $args);
		if(! is_null($r)) return $r;
		else{
			throw new RouterException(RouterException::WRONG_MAPPING, RouterException::POST_CONFIG_ERROR);
		}
	}

	public function delete(){
		$args = func_get_args();
		$r = $this->map('delete', $args);
		if(! is_null($r)) return $r;
		else{
			throw new RouterException(RouterException::WRONG_MAPPING, RouterException::DELETE_CONFIG_ERROR);
		}
	}

	public function patch(){
		$args = func_get_args();
		$r = $this->map('patch', $args);
		if(! is_null($r)) return $r;
		else{
			throw new RouterException(RouterException::WRONG_MAPPING, RouterException::PATCH_CONFIG_ERROR);
		}
	}

	public function put(){
		$args = func_get_args();
		$r = $this->map('put', $args);
		if(! is_null($r)) return $r;
		else{
			throw new RouterException(RouterException::WRONG_MAPPING, RouterException::PUT_CONFIG_ERROR);
		}
	}

	public function run(){

		$request = Request::getRequest();

		if(!empty($request->getPath())){
			//find the matching route, if exists
			if(isset($this->mapping[$request->getMethod()])){
				foreach($this->mapping[$request->getMethod()] as $route){
					if($route->matches($request->getPath())){
						$route->execute();
						$this->route = $route;
						break;
					}
				}

				//Clean old CSRF token
				if( class_exists('Pragma\\Forms\\CSRFTagsManager\\CSRFTagsManager') && \Pragma\Forms\CSRFTagsManager\CSRFTagsManager::isEnabled() ){
					\Pragma\Forms\CSRFTagsManager\CSRFTagsManager::getManager()->cleanup();
				}

				if( empty($this->route) ){
					throw new RouterException(RouterException::NO_ROUTE, RouterException::NO_ROUTE_CODE);
				}
			}
			else{
				throw new RouterException(RouterException::NO_ROUTE, RouterException::NO_ROUTE_CODE);
			}
		}
		else{
			throw new RouterException(RouterException::NO_ROUTE, RouterException::NO_ROUTE_CODE);
		}
	}

	public function getCurrentRoute(){
		return $this->route;
	}

	public static function url_for($name, $params = array()){
		$router = self::getInstance();
		if(isset($router->alias_mapping[$name])){
			$r = $router->alias_mapping[$name];

			$domain = "";
			if(self::$fullUrl){
				$domain = self::getDomain();
			}

			return $domain.$r->get_path_for($params);
		}
		else{
			throw new RouterException(RouterException::NO_ROUTE_FOR, RouterException::NO_ROUTE_FOR_CODE);
		}
	}

	public function has_alias($alias){
		return isset($this->alias_mapping[$alias]);
	}

	public function setControlMiddleware($control_callback){
		$this->control_callback = $control_callback;
	}

	public function getControlMiddleware(){
		return $this->control_callback;
	}

	public function group(){
		$args = func_get_args();
		$pattern = array_shift($args);
		$callable = array_pop($args);
		$this->pushGroup($pattern, $args);
		if (is_callable($callable)) {
			call_user_func($callable);
		}
		$this->popGroup();
	}

	private function pushGroup($group, $middleware = array()){
		return array_push($this->routeGroups, array($group => $middleware));
	}

	private function popGroup(){
		return (array_pop($this->routeGroups) !== null);
	}

	protected function processGroups(){
		$pattern = "";
		$middleware = array();
		foreach ($this->routeGroups as $group) {
			$k = key($group);
			$pattern .= $k;
			if (is_array($group[$k])) {
				$middleware = array_merge($middleware, $group[$k]);
			}
		}
		return array($pattern, $middleware);
	}

	public function getRouteGroups(){
		return $this->routeGroups;
	}

	public function setFullUrl($full = false){
		self::$fullUrl = $full;
	}
	public function getFullUrl(){
		return self::$fullUrl;
	}
	public static function getDomain(){
		if(empty(self::$domain)){
			$port = empty($_SERVER['SERVER_PORT'])?getenv('SERVER_PORT'):$_SERVER['SERVER_PORT'];
			self::$domain = 'http'.($port==80?'':'s').'://'.(empty($_SERVER['HTTP_HOST'])?getenv('HTTP_HOST'):$_SERVER['HTTP_HOST']);
			if(substr(self::$domain, -1) == '/'){
				self::$domain = substr(self::$domain, 0,-1);
			}
		}
		return self::$domain;
	}

	public function __debugInfo(){
		return array(
			'current_route' => $this->getCurrentRoute(),
			'mapping' => $this->alias_mapping,
		);
	}

	public function debugConsole(){
		$debug = $this->__debugInfo();
		ob_start();
		echo '<table width="100%"><thead><tr><th>Current route</th><th colspan="2">'.strtoupper($debug['current_route']->getVerb()).':'.$debug['current_route']->getPath().' ('.$debug['current_route']->getAlias().')</th></tr></thead>';
		foreach($debug['mapping'] as $d){
			$dd = $d->__debugInfo();
			echo '<tr><td>'.$dd['alias'].'</td><td>'.strtoupper($dd['verb']).'</td><td>'.$dd['path'].'</td></tr>';
		}
		echo '</thead></table>';
		$consoleTpl = ob_get_contents();
		ob_end_clean();
		?>
		<script type="text/javascript">
			var _view_console = window.open("","Console :router_debug","width=680,height=600,resizable,scrollbars=yes");
			_view_console.document.write('<?= str_replace(array("\\","'","\n","\r"),array("\\\\","\'","",""),nl2br($consoleTpl)); ?>');
			_view_console.document.close();
		</script>
		<?php
		//}
	}
}
