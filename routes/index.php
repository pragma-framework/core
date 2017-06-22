<?php
use Pragma\Router\Router;
use Pragma\Controller\CliController;

$app = Router::getInstance();

$app->cli('',function(){
	CliController::displayDescriptions();
});
$app->cli('core:example',function(){
	CliController::example();
});
