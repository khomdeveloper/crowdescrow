<?php

session_start();

ini_set("display_errors", 1);
error_reporting(E_ALL);

include "find.php";  //find framework
 
header('P3P: CP="IDC DSP COR CURa ADMa OUR IND PHY ONL COM STA"'); //this is necessary for correct session in iframe mode for IE
header('Content-Type: text/html; charset=utf-8');
date_default_timezone_set('Europe/Moscow');

$yii = Environment::get('framework') . '/yii.php';
$config = Environment::get('site_root') . '/protected/config/main.php';

//print_r($_SERVER);
//print_r($_SESSION['environment']);

// remove the following lines when in production mode
defined('YII_DEBUG') or define('YII_DEBUG', true);
// specify how many levels of call stack should be shown in each log message
defined('YII_TRACE_LEVEL') or define('YII_TRACE_LEVEL', 3);

require_once($yii);
Yii::createWebApplication($config)->run();
