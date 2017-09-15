<?php
namespace Pragma\Core;

class Module {
	public static function getDescription(){
		return array(
			"Pragma-Framework/Core",
			array("index.php\t\t\tDisplay all descriptions (helper)"),
			array("index.php core:display-routes\tDisplay all defined routes"),
		);
	}
}