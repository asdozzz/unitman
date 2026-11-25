<?php

declare(strict_types=1);

namespace App\AiChat\Infra\Service\Transport;

use App\Account\Business\Model\OpenCode\ProviderSessionPolicy;

/**
 * Передаёт provider-сессию заголовком запроса.
 */
final class HeaderSessionTransport implements SessionTransportApplier
{
    public function apply(
        ProviderSessionPolicy $policy,
        string $sessionId,
        array &$headers,
        array &$body,
        array &$query,
    ): void {
        $headers[(string) $policy->key] = $sessionId;
    }
}
