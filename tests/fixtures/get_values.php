<?php

require_once(__DIR__.'/../../vendor/autoload.php');

use Mouf\Utils\Session\SessionHandler\OptimisticSessionHandler;

session_set_save_handler(new OptimisticSessionHandler(), true);

session_start();

echo "a=".(isset($_SESSION['a'])?$_SESSION['a']:'null')."\n";
echo "b=".(isset($_SESSION['b'])?$_SESSION['b']:'null')."\n";
