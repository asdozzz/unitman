<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Model;

/**
 * Чьи реквизиты (и бюджет) тратит конкретный ход.
 *
 * Модуль OpenCode не хранит и не расшифровывает ключ: он фиксирует пару
 * `accountId` + `credentialId` автора хода, а расшифровка остаётся в Account
 * и выполняется Runner'ом в момент старта активити.
 */
final class IstochnikRekvizitovOpenCode
{
    public function __construct(
        public readonly string $accountId,
        public readonly string $credentialId,
        public readonly ?string $modelPoUmolchaniyu = null,
    ) {
        if (trim($accountId) === '') {
            throw new \DomainException('opencode.session.account_required');
        }

        if (trim($credentialId) === '') {
            throw new \DomainException('opencode.session.credential_required');
        }
    }
}
