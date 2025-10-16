<?php
//session_name('consultant_session');
session_start();
$_SESSION = array();
session_destroy();
header("Location: consultant_login.php");
exit();
?>