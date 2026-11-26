<?php

declare(strict_types=1);

namespace App\Account\Business\Model\Event;

/**
 * Email аккаунта подтверждён.
 *
 * Для самостоятельной регистрации это результат ввода кода из письма,
 * для OAuth-аккаунтов — подтверждение владения email провайдером.
 */
final class AccountEmailVerified
{
    public function __construct(
        public readonly string $accountId,
        public readonly string $verifiedAt,
    ) {
    }
}
