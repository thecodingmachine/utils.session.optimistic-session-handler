<?php

ini_set('display_errors', 'On');
error_reporting(E_ALL);

require_once __DIR__.'/../../vendor/autoload.php';

use Mouf\Utils\Session\SessionHandler\OptimisticSessionHandler;

session_set_save_handler(new OptimisticSessionHandler(), true);

session_start(['read_and_close' => true]);

$_SESSION['mouf'] = 'mouf';

session_set_save_handler(new \SessionHandler(), true);// unset the OptimisticSessionHandler

// Second session start... that should trigger an Exception.
@session_start(['read_and_close' => true]);
