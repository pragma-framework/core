<?php
namespace Pragma\Controller;

use Pragma\Router\Router;
use Pragma\Router\Request;

class CliController{
	public static function displayDescriptions(){
		if(defined('PRAGMA_MODULES') && !empty(PRAGMA_MODULES)){
			$modules = array_map('trim', explode(',', PRAGMA_MODULES));
			$pragmaPath = realpath(__DIR__.'/../../..').'/';
			foreach($modules as $m){
				if(file_exists($pragmaPath.$m.'/module.php')){
					require_once $pragmaPath.$m.'/module.php';
					$c = "Pragma\\".ucfirst($m)."\\Module";
					if(class_exists($c) && method_exists($c, 'getDescription')){
						self::echoDescription($c::getDescription());
					}
				}
			}
		}else{
			echo "\nNo modules defined in \"PRAGMA_MODULES\"";
		}
	}

	protected static function echoDescription(array $desc, $depth = 0){
		if($depth <= 0){
			echo "\n";
		}
		foreach($desc as $d){
			if(is_array($d)){
				self::echoDescription($d,$depth+1);
			}else{
				echo str_repeat("\t", $depth).$d."\n";
			}
		}
	}

	public static function example(){
		echo "Example method\n\nParams:\n";
		$params = Request::getRequest()->parse_params(true);
		var_dump($params);
	}
}