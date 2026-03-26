<?php

require_once __DIR__ . '/../Database/Database.php';
require_once __DIR__ . '/Password.php';

final class Auth
{
    public function __construct()
    {
        throw new RuntimeException('Auth is a static utility.');
    }

    private static function ensureSessionStarted(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    /**
     * @return array{user_id:int,name:string,email:string,role:string}|null
     */
    public static function user(): ?array
    {
        self::ensureSessionStarted();

        $user = $_SESSION['user'] ?? null;

        if (!is_array($user)) {
            return null;
        }

        if (!isset($user['user_id'], $user['name'], $user['email'], $user['role'])) {
            return null;
        }

        return $user;
    }

    public static function logout(): void
    {
        self::ensureSessionStarted();

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                [
                    'expires' => time() - 42000,
                    'path' => $params['path'],
                    'domain' => $params['domain'],
                    'secure' => $params['secure'],
                    'httponly' => $params['httponly'],
                    'samesite' => 'Lax',
                ]
            );
        }

        session_destroy();
    }

    /**
     * @return array{user_id:int,name:string,email:string,role:string}|null
     */
    public static function attemptLogin(string $email, string $password): ?array
    {
        self::ensureSessionStarted();

        $email = trim($email);

        if ($email === '' || $password === '') {
            return null;
        }

        $pdo = Database::connect();
        $stmt = $pdo->prepare('SELECT user_id, name, email, password_hash, role, pending_approval FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);

        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        if (!empty($row['pending_approval'])) {
            // Block login if pending approval
            return 'pending_approval';
        }

        $hash = (string)($row['password_hash'] ?? '');
        if ($hash === '') {
            return null;
        }

        if (!Password::verify($password, $hash)) {
            return null;
        }

        session_regenerate_id(true);

        $user = [
            'user_id' => (int)$row['user_id'],
            'name' => (string)$row['name'],
            'email' => (string)$row['email'],
            'role' => (string)$row['role'],
        ];

        $_SESSION['user'] = $user;

        return $user;
    }

    /**
     * Creates a new user account and logs them in.
     *
     * @return array{user_id:int,name:string,email:string,role:string}|null
     */
    public static function register(string $name, string $email, string $password, string $role = 'employee'): ?array
    {
        self::ensureSessionStarted();

        $name = trim($name);
        $email = trim($email);

        if ($name === '' || $email === '' || $password === '') {
            return null;
        }

        $allowedRoles = ['employee', 'owner', 'hr'];
        if (!in_array($role, $allowedRoles, true)) {
            $role = 'employee';
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        $hash = Password::hash($password);

        $pdo = Database::connect();

        try {
            $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role, pending_approval) VALUES (:name, :email, :password_hash, :role, 1)');
            $stmt->execute([
                'name' => $name,
                'email' => $email,
                'password_hash' => $hash,
                'role' => $role,
            ]);
        } catch (PDOException $e) {
            // Likely duplicate email (unique constraint). Keep response generic.
            return null;
        }

        session_regenerate_id(true);

        $user = [
            'user_id' => (int)$pdo->lastInsertId(),
            'name' => $name,
            'email' => $email,
            'role' => $role,
        ];

        $_SESSION['user'] = $user;

        return $user;
    }
}
