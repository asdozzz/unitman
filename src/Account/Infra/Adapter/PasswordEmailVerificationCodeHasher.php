<?php

declare(strict_types=1);

namespace App\Account\Infra\Adapter;

use App\Account\Business\Port\CanHashVerificationCode;

/**
 * Хеширование кода подтверждения тем же механизмом, что и пароли.
 */
final class PasswordEmailVerificationCodeHasher implements CanHashVerificationCode
{
    public function hash(string $code): string
    {
        return password_hash($code, PASSWORD_DEFAULT);
    }

    public function verify(string $code, string $hash): bool
    {
        return password_verify($code, $hash);
    }
}
