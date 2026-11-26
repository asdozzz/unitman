<?php

declare(strict_types=1);

namespace App\Account\Business\Port;

use App\Account\Business\Model\EmailVerification\EmailVerificationCode;

interface EmailVerificationCodeRepository
{
    public function findByEmail(string $email): ?EmailVerificationCode;

    public function findByAccountId(string $accountId): ?EmailVerificationCode;

    public function save(EmailVerificationCode $code): void;

    public function consume(EmailVerificationCode $code, int $now): void;
}
