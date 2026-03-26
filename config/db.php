<?php
// DB config.
// Resolution order:
// 1) Environment variables (recommended for deployment)
// 2) /home/bpaudel2/DBPaudel.php (your existing local dev creds)
// 3) Fallback scaffold defaults

$envHost = getenv('DB_HOST') ?: null;
$envName = getenv('DB_NAME') ?: null;
$envUser = getenv('DB_USER') ?: null;
$envPass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : null;
$envPort = getenv('DB_PORT') ?: null;

if (!$envHost || !$envName || !$envUser) {
    $legacyCredPath = '/home/bpaudel2/DBPaudel.php';
    if (is_file($legacyCredPath)) {
        require_once $legacyCredPath;
        if (!isset($envHost) && defined('DBHOST')) {
            $envHost = DBHOST;
        }
        if (!isset($envName) && defined('DBNAME')) {
            $envName = DBNAME;
        }
        if (!isset($envUser) && defined('USERNAME')) {
            $envUser = USERNAME;
        }
        if ($envPass === null && defined('PASSWORD')) {
            $envPass = PASSWORD;
        }
    }
}

return [
    'host' => $envHost ?: 'localhost',
    'port' => $envPort ? (int)$envPort : 3306,
    'database' => $envName ?: 'bpaudel2',
    'username' => $envUser ?: 'root',
    'password' => $envPass !== null ? $envPass : '',
    'charset' => 'utf8mb4',
];
