<?php
namespace Pragma\Router;

class Request{
	protected $path = '';
	protected $method = '';
	protected $isXhr = false;
	protected $isSameOrigin = true;
	protected $isCli = false;
	protected $options = array();

	private static $request = null;//singleton

	public function __construct(){
		if (php_sapi_name() == "cli") {
			$this->isCli = true;
			global $argv;
			$path = empty($argv)?array():$argv;
			unset($path[0]); // public/index.php
			$this->path = array();
			foreach($path as $p){
				if(substr($p, 0,2) == "--"){
					// --option=value OR --option
					$p = explode("=", substr($p, 2));
					$k = $p[0];
					unset($p[0]);
					$v = urldecode(implode("=", $p));
					if(empty($v)){
						$v = true;
					}
					$this->options[$k] = $v;
				}else{
					$this->path[] = $p;
				}
			}
			$this->path = implode(' ',array_values($this->path));
			unset($path);

			$this->method = 'cli';
		}else{
			$this->path = urldecode(parse_url(trim(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/'), PHP_URL_PATH));

			$this->method = strtolower($_SERVER['REQUEST_METHOD']);

			if(!empty($this->method) && $this->method == 'post'){//we need to check _METHOD
				if(!empty($_POST['_METHOD'])){
					$verb = strtolower($_POST['_METHOD']);
					switch($verb){
						case 'delete':
						case 'put':
						case 'patch':
							$this->method = $verb;
							break;
					}
				}
			}

			//isXhr ?
			$this->isXhr =
				  (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
				  || isset($_SERVER['CONTENT_TYPE']) && strpos(strtolower($_SERVER['CONTENT_TYPE']), 'application/json') !== false
				  || isset($_SERVER['CONTENT_TYPE']) && strpos(strtolower($_SERVER['CONTENT_TYPE']), 'application/javascript') !== false;//jsonp

			//isSameOrigin ? HTTP_REFERER is not always given by the browser agent, HTTP_HOST too
			if(isset($_SERVER['HTTP_PRAGMA_REFERER']) || isset($_SERVER['HTTP_REFERER']) && isset($_SERVER['HTTP_HOST'])){
				$referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : $_SERVER['HTTP_PRAGMA_REFERER'];
				$requestOrigin = parse_url(strtolower($referer), PHP_URL_HOST);
				if($requestOrigin != strtolower($_SERVER['HTTP_HOST'])){
					$this->isSameOrigin = false;
				}
			}
		}
	}

	public static function getRequest(){
		if(is_null(self::$request)){
			self::$request = new Request();
		}

		return self::$request;
	}

	public function getPath(){
		return $this->path;
	}

	public function getMethod(){
		return $this->method;
	}

	public function isXhr(){
		return $this->isXhr;
	}

	public  function isSameOrigin(){
		return $this->isSameOrigin;
	}

	public function isCli(){
		return $this->isCli;
	}

	public function getOptions(){
		return $this->options;
	}

	//Allow developpers to access current request' params out of a controller (i.e. : a Router Middleware for example)
	public function parse_params($sanitize = true){
		$params = [];
		if(isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], "application/json") !== false){
			$params = json_decode(file_get_contents('php://input'), true);
		}
		else{
			parse_str(file_get_contents('php://input'), $params);
		}

		if(is_null($params)){ //parse_str peut retourner nul si la chaîne passée en paramètre est vide
			$params = array();
		}

		if( $sanitize ){
			$params = array_map('self::recursive_filter', $params);
			$_GET   = filter_input_array(INPUT_GET, FILTER_SANITIZE_STRING);
			$_POST  = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);
			$this->options = filter_var_array($this->options, FILTER_SANITIZE_STRING);
		}

		if(is_null($_POST)){
			$_POST = array();
		}

		if(is_null($_GET)){
			$_GET = array();
		}

		if($this->isCli()){
			return array_merge($params,$this->options);
		}else{
			return array_merge($_POST, $params, $_GET);
		}
	}

	public static function recursive_filter($val)
	{
		if ($val === null)
			return null;
		elseif (is_array($val))
			return array_map('self::recursive_filter', $val);
		else
			return filter_var($val, FILTER_SANITIZE_STRING);
	}
}
