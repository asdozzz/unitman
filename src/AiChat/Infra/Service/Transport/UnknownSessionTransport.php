<?php

declare(strict_types=1);

namespace App\AiChat\Infra\Service\Transport;

use App\Account\Business\Model\OpenCode\ProviderSessionPolicy;

/**
 * Транспорт не описан: запрос не отправляется, провайдер заблокирован.
 */
final class UnknownSessionTransport implements SessionTransportApplier
{
    public function apply(
        ProviderSessionPolicy $policy,
        string $sessionId,
        array &$headers,
        array &$body,
        array &$query,
    ): void {
        throw new ProviderSessionTransportUnknownException();
    }
}
