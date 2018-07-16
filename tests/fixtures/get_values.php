<?php

require_once __DIR__.'/../../vendor/autoload.php';

use Mouf\Utils\Session\SessionHandler\OptimisticSessionHandler;

session_set_save_handler(new OptimisticSessionHandler(), true);

$waitBeforeStart = isset($_GET['waitBeforeStart']) ? $_GET['waitBeforeStart'] : 0;
$waitBeforeQuit = isset($_GET['waitBeforeQuit']) ? $_GET['waitBeforeQuit'] : 0;

sleep($waitBeforeStart);
session_start(['read_and_close' => true]);

echo 'a='.(isset($_SESSION['a']) ? $_SESSION['a'] : 'null')."\n";
echo 'b='.(isset($_SESSION['b']) ? $_SESSION['b'] : 'null')."\n";

sleep($waitBeforeQuit);
