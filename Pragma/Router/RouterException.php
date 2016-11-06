<?php
namespace Pragma\Router;

class RouterException extends \Exception{

	const GET_CONFIG_ERROR = 1;
	const POST_CONFIG_ERROR = 2;
	const DELETE_CONFIG_ERROR = 3;
	const PATCH_CONFIG_ERROR = 4;
	const PUT_CONFIG_ERROR = 5;
	const NO_ROUTE_CODE = 6;
	const NO_ROUTE_FOR_CODE = 7;
	const WRONG_NUMBER_PARAMS_CODE = 8;
	const ALREADY_USED_ALIAS_CODE = 9;

	const WRONG_MAPPING = 'Wrong number of args for route mapping';
	const NO_ROUTE = 'No route found';
	const NO_ROUTE_FOR = 'This alias doesn\'t seem to exists';
	const WRONG_NUMBER_PARAMS = 'Wrong number of parameters for the url_for call';
	const ALREADY_USED_ALIAS = 'Alias already used';


	public function __constructor($message, $code = 0, \Exception $previous = null){
		parent::__constructor($message, $code, $previous);
	}

	public function __toString(){
		return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
	}
}
