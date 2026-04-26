<?php

namespace App\Helpers;

class PasswordHelper
{
    /**
     * Hashes a password using Bcrypt.
     *
     * @param string $password The password to hash.
     * @return string The hashed password.
     */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT);
    }

    /**
     * Verifies a password against a hash.
     *
     * @param string $password The password to verify.
     * @param string $hash The hash to verify against.
     * @return bool True if the password matches the hash, false otherwise.
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Hashes a token using Bcrypt.
     *
     * @param string $token The token to hash.
     * @return string The hashed token.
     */
    public static function hashToken(string $token): string
    {
        return password_hash($token, PASSWORD_BCRYPT);
    }
}
