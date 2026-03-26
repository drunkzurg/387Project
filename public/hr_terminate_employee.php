<?php
require_once __DIR__ . '/../src/Auth/Auth.php';
require_once __DIR__ . '/../src/Database/Database.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$user = Auth::user();
if (!$user || $user['role'] !== 'hr') {
    header('Location: login.php');
    exit;
}
$pdo = Database::connect();
$employeeId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$pdo->prepare('UPDATE employees SET status = "terminated" WHERE employee_id = :id')->execute(['id' => $employeeId]);
header('Location: hr_dashboard.php');
exit;
?>