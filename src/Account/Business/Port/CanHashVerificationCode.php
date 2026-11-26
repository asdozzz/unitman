<?php

declare(strict_types=1);

namespace App\Account\Business\Port;

interface CanHashVerificationCode
{
    public function hash(string $code): string;

    public function verify(string $code, string $hash): bool;
}
