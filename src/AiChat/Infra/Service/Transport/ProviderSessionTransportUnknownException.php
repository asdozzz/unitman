<?php

declare(strict_types=1);

namespace App\AiChat\Infra\Service\Transport;

/**
 * Транспорт provider-сессии не описан.
 *
 * Сообщение — ключ словаря `ai.chat.provider_session_transport_unknown`;
 * контроллер переводит его и отдаёт 422.
 */
final class ProviderSessionTransportUnknownException extends \DomainException
{
    public function __construct()
    {
        parent::__construct('ai.chat.provider_session_transport_unknown');
    }
}
