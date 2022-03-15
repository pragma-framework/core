<?php
namespace Pragma\Router;

use Pragma\Router\Request;

class Router{
	public $mapping = array();
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
			self::$router->initModules();
		}
		return self::$router;
	}

	protected function initModules(){
		if(defined('PRAGMA_MODULES') && !empty(PRAGMA_MODULES)){
			$modules = array_map('trim', explode(',', PRAGMA_MODULES));
			$pragmaPath = realpath(__DIR__.'/../../..').'/';
			foreach($modules as $m){
				if(file_exists($pragmaPath.$m.'/routes/index.php')){
					require_once $pragmaPath.$m.'/routes/index.php';
				}
			}
		}
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

	public function cli(){
		$args = func_get_args();
		$r = $this->map('cli', $args);
		if(! is_null($r)) return $r;
		else{
			throw new RouterException(RouterException::WRONG_MAPPING, RouterException::CLI_CONFIG_ERROR);
		}
	}

	public function run(){

		$request = Request::getRequest();
		$path = $request->getPath();

		if(!empty($path) || $request->isCli()){
			//find the matching route, if exists
			if(isset($this->mapping[$request->getMethod()])){
				foreach($this->mapping[$request->getMethod()] as $route){
					if($route->matches($request->getPath())){
						$this->route = $route;
						$route->execute();
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

	public function getMatchingRoute($verb, $path){
		$verb = strtolower($verb);
		if(isset($this->mapping[$verb])){
			foreach($this->mapping[$verb] as $route){
				if($route->matches($path)){
					return $route;
				}
			}
		}
		return null;
	}

	public function getMatchingGroup($group){
		if(substr($group, 0, 1) != '/'){
			$group = '/'.$group;
		}
		$group = explode('/', $group);
		$matching = array();
		foreach($this->mapping as $verb => $routes){
			foreach($routes as $r){
				reset($group);
				$rGroup = $r->getGroups();
				$isOk = true;
				while($isOk && ($g = next($group))){
					$isOk = ($g == next($rGroup));
				}
				if($isOk){
					$matching[] = $r;
				}
			}
		}
		return $matching;
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

	public function resources($pattern, $controller = null, $callback = [], $ctrl_builder = null, $prefix = null){
		$this->group($pattern, function() use($controller, $callback, $pattern, $ctrl_builder, $prefix) {
			if (isset($callback['collection']) && is_callable($callback['collection'])) {//needs to be played before classical routes
				call_user_func($callback['collection']);
			}

			if(is_null($prefix)){
				$prefix = str_replace(['/', ':'], ['-', ''], strpos($pattern, '/') === 0 ? substr($pattern, 1) : $pattern);
			}

			$this->get('', function() use($controller, $ctrl_builder) {
				$route = $this->getCurrentRoute();
				$controller = ! is_null($controller) ? $controller : ( is_callable($ctrl_builder) ? call_user_func_array($ctrl_builder,  $route->getValues()) : null );
				if( ! is_null($controller) && ! method_exists($controller, 'index') ) {
					Router::halt(404, 'Resource not found');
				}
				call_user_func_array([is_object($controller) ? $controller : new $controller(), 'index'], $route->getValues());
			})->alias("$prefix-index");

			$pname = str_replace(['/', ':'], ['_', ''], strpos($pattern, '/') === 0 ? substr($pattern, 1) : $pattern).'_id';

			if(isset($callback['member']) && is_callable($callback['member'])){
				$this->group("/:$pname", function() use($controller, $callback, $ctrl_builder, $prefix){
						$this->get('', function($param) use($controller, $ctrl_builder) {
							$route = $this->getCurrentRoute();
							$controller = ! is_null($controller) ? $controller : ( is_callable($ctrl_builder) ? call_user_func_array($ctrl_builder,  $route->getValues()) : null );
							if( ! is_null($controller) && ! method_exists($controller, 'show') ) {
								Router::halt(404, 'Resource not found');
							}
							call_user_func_array([is_object($controller) ? $controller : new $controller(), 'show'], $route->getValues());
						})->alias("$prefix-show");
					call_user_func($callback['member']);
				});
			}
			else {
				$this->get("/:$pname", function($pid) use($controller, $ctrl_builder) {
					$route = $this->getCurrentRoute();
					$controller = ! is_null($controller) ? $controller : ( is_callable($ctrl_builder) ? call_user_func_array($ctrl_builder,  $route->getValues()) : null );
					if( ! is_null($controller) && ! method_exists($controller, 'show') ) {
						Router::halt(404, 'Resource not found');
					}
					call_user_func_array([is_object($controller) ? $controller : new $controller(), 'show'], $route->getValues());
				})->alias("$prefix-show");
			}

			$this->post('', function() use($controller, $ctrl_builder) {
				$route = $this->getCurrentRoute();
				$controller = ! is_null($controller) ? $controller : ( is_callable($ctrl_builder) ? call_user_func_array($ctrl_builder,  $route->getValues()) : null );
				if( ! is_null($controller) && ! method_exists($controller, 'create') ) {
					Router::halt(404, 'Resource not found');
				}
				call_user_func_array([is_object($controller) ? $controller : new $controller(), 'create'], $route->getValues());
			})->alias("$prefix-create");

			$this->put("/:$pname", function($id) use($controller, $ctrl_builder) {
				$route = $this->getCurrentRoute();
				$controller = ! is_null($controller) ? $controller : ( is_callable($ctrl_builder) ? call_user_func_array($ctrl_builder,  $route->getValues()) : null );
				if( ! is_null($controller) && ! method_exists($controller, 'update') ) {
					Router::halt(404, 'Resource not found');
				}
				call_user_func_array([is_object($controller) ? $controller : new $controller(), 'update'], $route->getValues());
			})->alias("$prefix-update");

			$this->delete("/:$pname", function($id) use($controller, $ctrl_builder) {
				$route = $this->getCurrentRoute();
				$controller = ! is_null($controller) ? $controller : ( is_callable($ctrl_builder) ? call_user_func_array($ctrl_builder,  $route->getValues()) : null );
				if( ! is_null($controller) && ! method_exists($controller, 'delete') ) {
					Router::halt(404, 'Resource not found');
				}
				call_user_func_array([is_object($controller) ? $controller : new $controller(), 'delete'], $route->getValues());
			})->alias("$prefix-delete");

			$this->patch("/:$pname/field/:field", function($id, $field) use($controller, $ctrl_builder) {
				$route = $this->getCurrentRoute();
				$controller = ! is_null($controller) ? $controller : ( is_callable($ctrl_builder) ? call_user_func_array($ctrl_builder,  $route->getValues()) : null );
				if( ! is_null($controller) && ! method_exists($controller, 'toggle') ) {
					Router::halt(404, 'Resource not found');
				}
				call_user_func_array([is_object($controller) ? $controller : new $controller(), 'toggle'], $route->getValues());
			})->alias("$prefix-toggle");
		});
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
			self::$domain = 'http'.( $port == 80 || (defined('FORCE_NO_SSL') && FORCE_NO_SSL) ?'':'s').'://'.(empty($_SERVER['HTTP_HOST'])?getenv('HTTP_HOST'):$_SERVER['HTTP_HOST']);
			if(substr(self::$domain, -1) == '/'){
				self::$domain = substr(self::$domain, 0,-1);
			}
		}
		return self::$domain;
	}

	public static function redirect($url){
		header('location: '.$url);
		exit();
	}

	public static function halt($status, $msg, $print = null){
		if(Request::getRequest()->isCli()){
			echo "Route not found\n";
			\Pragma\Controller\CliController::displayDescriptions();
		}else{
			ob_clean();
			header("HTTP/1.0 $status $msg");
			if( ! is_null($print)){
				echo $print;
			}
			die();
		}
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

	public function getMapping(){
		return $this->mapping;
	}
}
