<?php
session_start();
$_SESSION = [];
session_destroy();

setcookie('remember_email', '', time() - 3600, '/');

header("Location: login.php");
exit;
