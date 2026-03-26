<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    echo "Not Found\n";
    exit(1);
}

require __DIR__ . '/../src/Auth/Password.php';

$argv = $_SERVER['argv'] ?? [];
$argc = $_SERVER['argc'] ?? 0;

if ($argc < 2) {
    fwrite(STDERR, "Usage: php scripts/hash_password.php <password>\n");
    fwrite(STDERR, "   or: echo -n '<password>' | php scripts/hash_password.php -\n");
    exit(2);
}

$input = $argv[1];

if ($input === '-') {
    $stdin = stream_get_contents(STDIN);
    $input = rtrim((string)$stdin, "\r\n");
}

if ($input === '') {
    fwrite(STDERR, "Error: empty password\n");
    exit(2);
}

echo Password::hash($input) . PHP_EOL;
