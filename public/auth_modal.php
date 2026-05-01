<?php

// json endpoint used by the spa login/register modal on index.php (posts to same-origin auth_modal.php)
require_once __DIR__ . '/../src/Auth/Auth.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

// maps user.role to the php dashboard entry script after login
$dashboardPath = static function (string $role): string {
    return match ($role) {
        'sys_admin' => 'admin_dashboard.php',
        'owner' => 'owner_dashboard.php',
        'hr' => 'hr_dashboard.php',
        'employee' => 'employee_dashboard.php',
        default => 'index.php',
    };
};

// uniform json exit helper for validation / auth failures
$respond = static function (array $payload, int $statusCode = 200): void {
    http_response_code($statusCode);
    echo json_encode($payload, JSON_THROW_ON_ERROR);
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $respond(['ok' => false, 'message' => 'Unsupported request method.'], 405);
}

// spa sends action=login | register from hidden field
$action = (string)($_POST['action'] ?? '');

if ($action === 'login') {
    $user = Auth::attemptLogin(
        (string)($_POST['email'] ?? ''),
        (string)($_POST['password'] ?? '')
    );

    if ($user === 'pending_approval') {
        $respond(['ok' => false, 'message' => 'Your account is pending admin approval.']);
    }

    if ($user === null) {
        $respond(['ok' => false, 'message' => 'Invalid email or password.'], 401);
    }

    $respond([
        'ok' => true,
        'message' => 'Logged in successfully.',
        'user' => [
            'name' => (string)$user['name'],
            'role' => (string)$user['role'],
        ],
        'dashboardPath' => $dashboardPath((string)$user['role']),
    ]);
}

// registers staff account then logs out so approval queue stays enforced (pending_approval)
if ($action === 'register') {
    $name = (string)($_POST['name'] ?? '');
    $email = (string)($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');
    $role = (string)($_POST['role'] ?? 'employee');

    if (trim($name) === '') {
        $respond(['ok' => false, 'message' => 'Name is required.'], 422);
    }
    if (filter_var(trim($email), FILTER_VALIDATE_EMAIL) === false) {
        $respond(['ok' => false, 'message' => 'Please enter a valid email.'], 422);
    }
    if (strlen($password) < 6) {
        $respond(['ok' => false, 'message' => 'Password must be at least 6 characters.'], 422);
    }
    if ($password !== $confirm) {
        $respond(['ok' => false, 'message' => 'Passwords do not match.'], 422);
    }

    $user = Auth::register($name, $email, $password, $role);
    if ($user === null) {
        $respond(['ok' => false, 'message' => 'Could not create account. The email may already be in use.'], 422);
    }

    Auth::logout();

    $respond([
        'ok' => true,
        'message' => 'Account request sent to admin.',
    ]);
}

// fallback when action is missing or typo’d
$respond(['ok' => false, 'message' => 'Unknown auth action.'], 400);
