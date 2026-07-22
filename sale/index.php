<?php
include "../includes/auth_check.php";
protectSales('view');
header("Location: pos.php");
exit;
