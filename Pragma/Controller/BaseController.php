<?php
namespace Pragma\Controller;

use Pragma\View\View;
use Pragma\Router\Router;
use Pragma\Router\Request;

class BaseController{
	protected $view;
	protected $app;
	protected $params;
	protected $sanitize = true;

	public function __construct($sanitize = true){
		$this->view = View::getInstance();
		$this->app = Router::getInstance();
		$this->sanitize = $sanitize;
		$this->init_params();
	}

	private function init_params(){
		$this->params = array();
		if(isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], "application/json") !== false){
			$this->params = json_decode(file_get_contents('php://input'), true);
		}
		else{
			parse_str(file_get_contents('php://input'), $this->params);
		}

		if(is_null($this->params)){ //parse_str peut retourner nul si la chaîne passée en paramètre est vide
			$this->params = array();
		}

		if( $this->sanitize ){
			$this->params = filter_var_array($this->params, FILTER_SANITIZE_STRING);
			$_GET   = filter_input_array(INPUT_GET, FILTER_SANITIZE_STRING);
			$_POST  = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);
		}

		if(is_null($_POST)){
			$_POST = array();
		}

		if(is_null($_GET)){
			$_GET = array();
		}

		$this->params = array_merge($_POST, $this->params, $_GET);

		//CSRF Protection - enabled only if the package composer is used and the tagmanager enabled too
		if( class_exists('Pragma\\Forms\\CSRFTagsManager\\CSRFTagsManager') && \Pragma\Forms\CSRFTagsManager\CSRFTagsManager::isEnabled() ){
			if( Request::getRequest()->getMethod() != 'get' || isset($this->params[\Pragma\Forms\CSRFTagsManager\CSRFTagsManager::CSRF_TAG_NAME]) ) {
				$tag = null;
				if( isset($this->params[\Pragma\Forms\CSRFTagsManager\CSRFTagsManager::CSRF_TAG_NAME]) ){
					$tag = $this->params[\Pragma\Forms\CSRFTagsManager\CSRFTagsManager::CSRF_TAG_NAME];
					unset($this->params[\Pragma\Forms\CSRFTagsManager\CSRFTagsManager::CSRF_TAG_NAME]);
				}

				\Pragma\Forms\CSRFTagsManager\CSRFTagsManager::getManager()->checkTag($tag, $this->params);

			}
			else if(Request::getRequest()->getMethod() != 'get'){
				throw new \Pragma\Forms\CSRFTagsManager\CSRFTagsException(\Pragma\Forms\CSRFTagsManager\CSRFTagsException::TAG_REQUESTED_MESS, \Pragma\Forms\CSRFTagsManager\CSRFTagsException::TAG_REQUESTED);
			}
		}
		return $this->params;
	}
}
