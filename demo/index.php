<?php

require_once('../src/OptimisticSessionHandler.php');

use Mouf\Utils\Session\SessionHandler\OptimisticSessionHandler;

session_set_save_handler(new OptimisticSessionHandler(), true);
echo "Starting session with the OptimisticSessionHandler<br/>";
session_start();

echo "The session status is : ";
switch(session_status()) {
    case PHP_SESSION_ACTIVE:
        echo "active";
        break;
    case PHP_SESSION_DISABLED:
        echo "disabled";
        break;
    case PHP_SESSION_NONE:
        echo "none (The session is not locked and other requests can be sent to the server while this request is treated)";
        break;
}
echo "<br/><br/>";

echo "Content of the \$_SESSION array :";
var_dump($_SESSION);

if(isset($_GET['session_destroy']) && $_GET['session_destroy'] == '1') {
    echo "Destroying the session by setting \$_SESSION = array()<br/><br/>";
    $_SESSION = array();
    echo "<a href=\"?session_destroy=0\">Stop destroying the session</a>";
} else {
    echo "<br/>Changing the content of the \$_SESSION while session is closed : ";
    echo "\$_SESSION['random'] = rand(0,100)<br/>";

    $_SESSION['random'] = rand(0,100);

    echo "Content of the \$_SESSION array modified :";
    var_dump($_SESSION);

    echo "<a href=\"?session_destroy=1\">Destroy the session</a>";
}

?>
