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
		$this->params = Request::getRequest()->parse_params($this->sanitize);
		//CSRF Protection - enabled only if the package composer is used and the tagmanager enabled too
		if( class_exists('Pragma\\Forms\\CSRFTagsManager\\CSRFTagsManager') && \Pragma\Forms\CSRFTagsManager\CSRFTagsManager::isEnabled() ){
			if( Request::getRequest()->getMethod() != 'get' || isset($this->params[\Pragma\Forms\CSRFTagsManager\CSRFTagsManager::CSRF_TAG_NAME]) ) {
				$tag = null;
				if( isset($this->params[\Pragma\Forms\CSRFTagsManager\CSRFTagsManager::CSRF_TAG_NAME]) ){
					$tag = $this->params[\Pragma\Forms\CSRFTagsManager\CSRFTagsManager::CSRF_TAG_NAME];
					unset($this->params[\Pragma\Forms\CSRFTagsManager\CSRFTagsManager::CSRF_TAG_NAME]);
				}

				\Pragma\Forms\CSRFTagsManager\CSRFTagsManager::getManager()->checkTag($tag, $this->params, $_FILES);

			}
			else if(Request::getRequest()->getMethod() != 'get'){
				throw new \Pragma\Forms\CSRFTagsManager\CSRFTagsException(\Pragma\Forms\CSRFTagsManager\CSRFTagsException::TAG_REQUESTED_MESS, \Pragma\Forms\CSRFTagsManager\CSRFTagsException::TAG_REQUESTED);
			}
		}
		return $this->params;
	}
}
