<?php
require_once('Group5_session.php');

session_unset();
session_destroy();

session_start();
$_SESSION["message"] = "You have been logged out.";

header("Location: index.php");
exit;
?>