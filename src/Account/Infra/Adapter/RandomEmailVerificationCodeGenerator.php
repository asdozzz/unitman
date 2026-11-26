<?php

declare(strict_types=1);

namespace App\Account\Infra\Adapter;

use App\Account\Business\Port\CanGenerateEmailVerificationCode;

/**
 * Шестизначный код подтверждения email.
 *
 * Ведущие нули сохраняются: код всегда строковый длины 6.
 */
final class RandomEmailVerificationCodeGenerator implements CanGenerateEmailVerificationCode
{
    public function generate(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
