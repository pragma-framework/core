<?php

define ('DB_CONNECTOR', 'sqlite');
define ('DB_HOST',      '');
define ('DB_NAME',      ':memory:');
define ('DB_USER',      '');
define ('DB_PASSWORD',  '');

require_once __DIR__.'/../Pragma/Controller/BaseController.php';
require_once __DIR__.'/../Pragma/DB/DB.php';
require_once __DIR__.'/../Pragma/ORM/Model.php';
require_once __DIR__.'/../Pragma/ORM/QueryBuilder.php';
require_once __DIR__.'/../Pragma/ORM/SerializableInterface.php';
require_once __DIR__.'/../Pragma/Router/Request.php';
require_once __DIR__.'/../Pragma/Router/Route.php';
require_once __DIR__.'/../Pragma/Router/Router.php';
require_once __DIR__.'/../Pragma/Router/RouterException.php';
require_once __DIR__.'/../Pragma/View/View.php';
