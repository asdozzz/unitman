<?php

declare(strict_types=1);

namespace App\Account\Business\Port;

interface CanGenerateEmailVerificationCode
{
    public function generate(): string;
}
