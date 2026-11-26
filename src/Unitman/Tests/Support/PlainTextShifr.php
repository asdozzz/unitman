<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Support;

use App\Unitman\Business\Port\UmeetShifrovatSekrety;

/**
 * Passthrough-шифр для юнит-тестов, которым важен только контракт порта.
 *
 * Использует синтетические значения из allowlist WS-10; в БД не пишет.
 */
final class PlainTextShifr implements UmeetShifrovatSekrety
{
    public function zashifrovat(string $secret, string $aad): string
    {
        return $secret;
    }

    public function rasshifrovat(string $cipher, string $aad): string
    {
        return $cipher;
    }
}
