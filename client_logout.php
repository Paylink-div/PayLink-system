<?php
// client_logout.php
session_start();
unset($_SESSION['client_id']);
unset($_SESSION['client_name']);
header("Location: client_login.php");
exit;
?>