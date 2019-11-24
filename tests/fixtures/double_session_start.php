<?php

ini_set('display_errors', 'On');
error_reporting(E_ALL);

require_once __DIR__.'/../../vendor/autoload.php';

use Mouf\Utils\Session\SessionHandler\OptimisticSessionHandler;

session_set_save_handler(new OptimisticSessionHandler(), true);

$a = isset($_GET['a']) ? $_GET['a'] : null;
$waitBeforeStart = isset($_GET['waitBeforeStart']) ? $_GET['waitBeforeStart'] : 0;
$waitBeforeQuit = isset($_GET['waitBeforeQuit']) ? $_GET['waitBeforeQuit'] : 0;

sleep($waitBeforeStart);

session_start(['read_and_close' => true]);

if ($a) {
    $_SESSION['a'] = $a;
}

// Second session start... that should not change the session.
@session_start(['read_and_close' => true]);

sleep($waitBeforeQuit);
