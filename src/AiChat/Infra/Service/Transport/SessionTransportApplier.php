<?php

declare(strict_types=1);

namespace App\AiChat\Infra\Service\Transport;

use App\Account\Business\Model\OpenCode\ProviderSessionPolicy;

/**
 * Применяет идентификатор provider-сессии к исходящему запросу.
 *
 * Реализация выбирается по `ProviderSessionPolicy::$transport`, а не по коду
 * конкретного провайдера: прокси не должен знать, кто именно требует
 * заголовок, тело или query-параметр.
 */
interface SessionTransportApplier
{
    /**
     * Мутирует ровно одну «корзину» запроса согласно политике.
     *
     * @param array<string, string> $headers
     * @param array<string, mixed>  $body
     * @param array<string, string> $query
     *
     * @throws ProviderSessionTransportUnknownException при неизвестном транспорте
     */
    public function apply(
        ProviderSessionPolicy $policy,
        string $sessionId,
        array &$headers,
        array &$body,
        array &$query,
    ): void;
}
