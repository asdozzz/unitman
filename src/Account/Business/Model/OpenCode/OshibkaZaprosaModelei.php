<?php

declare(strict_types=1);

namespace App\Account\Business\Model\OpenCode;

/**
 * Ошибка запроса списка моделей у провайдера.
 *
 * Несёт ключ перевода для формы реквизитов и HTTP-статус ответа провайдера:
 * статус позволяет отличить гео-блокировку (403) от невалидного ключа (401).
 * `status` равен null, когда ответа нет — сетевая ошибка или таймаут.
 */
final class OshibkaZaprosaModelei extends \DomainException
{
    public function __construct(
        string $errorKey,
        public readonly ?int $status = null,
    ) {
        parent::__construct($errorKey);
    }
}
