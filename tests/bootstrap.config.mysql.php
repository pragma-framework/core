<?php

/* Require prior creation of pragma-core databse */

define ('DB_CONNECTOR', 'mysql');
define ('DB_HOST',      'localhost');
define ('DB_NAME',      'pragma-core');
define ('DB_USER',      'pragma-core');
define ('DB_PASSWORD',  'pragma-core');

require_once __DIR__.'/../Pragma/View/View.php';
require_once __DIR__.'/../Pragma/Controller/BaseController.php';
require_once __DIR__.'/../Pragma/Router/RouterException.php';
require_once __DIR__.'/../Pragma/Router/Router.php';
require_once __DIR__.'/../Pragma/Router/Request.php';
require_once __DIR__.'/../Pragma/Router/Route.php';
require_once __DIR__.'/../Pragma/ORM/QueryBuilder.php';
require_once __DIR__.'/../Pragma/ORM/Model.php';
require_once __DIR__.'/../Pragma/ORM/SerializableInterface.php';
require_once __DIR__.'/../Pragma/DB/DB.php';
