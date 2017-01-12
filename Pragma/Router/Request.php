<?php
namespace Pragma\Router;

class Request{
	protected $path = '';
	protected $method = '';
	protected $isXhr = false;
	protected $isSameOrigin = true;

	private static $request = null;//singleton

	public function __construct(){
		$this->path = parse_url(trim(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/'), PHP_URL_PATH);

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
			  || isset($_SERVER['CONTENT_TYPE']) && strtolower($_SERVER['CONTENT_TYPE']) == 'application/json'
			  || isset($_SERVER['CONTENT_TYPE']) && strtolower($_SERVER['CONTENT_TYPE']) == 'application/javascript';//jsonp

		//isSameOrigin ? HTTP_REFERER is not always given by the browser agent, HTTP_HOST too
		if(isset($_SERVER['HTTP_REFERER']) && isset($_SERVER['HTTP_HOST'])){
			$requestOrigin = parse_url(strtolower($_SERVER['HTTP_REFERER']), PHP_URL_HOST);
			if($requestOrigin != strtolower($_SERVER['HTTP_HOST'])){
				$this->isSameOrigin = false;
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
}
