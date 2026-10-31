<?php

namespace App\Account\Business\Port;

/**
 * Умеет генерировать значение refresh-токена и вычислять его хеш.
 * */
interface CanGenerateRefreshToken
{
    public function generate(): string;

    public function hash(string $refreshToken): string;
}
