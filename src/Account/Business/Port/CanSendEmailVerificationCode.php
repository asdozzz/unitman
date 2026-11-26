<?php

declare(strict_types=1);

namespace App\Account\Business\Port;

interface CanSendEmailVerificationCode
{
    public function otpravit(string $email, ?string $nickname, string $code, string $locale): void;
}
