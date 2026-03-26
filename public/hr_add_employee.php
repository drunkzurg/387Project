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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $department_id = (int)($_POST['department_id'] ?? 0);
    $hourly_wage = (float)($_POST['hourly_wage'] ?? 0);
    // Find user by email
    $userRow = $pdo->prepare('SELECT user_id FROM users WHERE email = :email');
    $userRow->execute(['email' => $email]);
    $userData = $userRow->fetch();
    if ($userData) {
        // Only add if not already an employee
        $check = $pdo->prepare('SELECT employee_id FROM employees WHERE user_id = :user_id');
        $check->execute(['user_id' => $userData['user_id']]);
        if (!$check->fetch()) {
            $insert = $pdo->prepare('INSERT INTO employees (user_id, name, department_id, hourly_wage, status) VALUES (:user_id, :name, :department_id, :hourly_wage, "active")');
            $insert->execute([
                'user_id' => $userData['user_id'],
                'name' => $name,
                'department_id' => $department_id,
                'hourly_wage' => $hourly_wage
            ]);
        }
    }
    header('Location: hr_dashboard.php');
    exit;
}
?>