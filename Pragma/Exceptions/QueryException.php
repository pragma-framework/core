<?php
namespace Pragma\Exceptions;

class QueryException extends \Exception{
	const EMPTY_IN_VALUE_ERROR = 1;

	const EMPTY_IN_VALUE_MSG = 'Trying to do IN/NOT IN whereas value is empty';

	public function __toString(){
		return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
	}
}
