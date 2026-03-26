<?php

final class Password
{
    public function __construct()
    {
        throw new RuntimeException('Password is a static utility.');
    }

    public static function hash(string $plainText): string
    {
        $hash = password_hash($plainText, PASSWORD_DEFAULT);

        if ($hash === false) {
            throw new RuntimeException('Failed to hash password.');
        }

        return $hash;
    }

    public static function verify(string $plainText, string $hash): bool
    {
        return password_verify($plainText, $hash);
    }
}
