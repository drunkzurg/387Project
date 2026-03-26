<?php
require_once __DIR__ . '/../src/Auth/Auth.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$user = Auth::user();
if (!$user || $user['role'] !== 'employee') {
    header('Location: login.php');
    exit;
}
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Employee Dashboard</title>
</head>
<body>
  <h1>Employee Dashboard</h1>
  <ul>
    <li><a href="#">My Shifts</a> (coming soon)</li>
    <li><a href="#">Arcade Usage Logs</a> (coming soon)</li>
    <li><a href="#">Prize Redemptions</a> (coming soon)</li>
  </ul>
  <p><a href="index.php">Back to Home</a></p>
  <p><a href="logout.php">Logout</a></p>
</body>
</html>
