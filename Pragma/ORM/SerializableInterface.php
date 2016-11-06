<?php
namespace Pragma\ORM;

/*
In order to get compatibility with PHP 5.3
*/
interface SerializableInterface{
	public function toJSON();
}
