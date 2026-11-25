<?php

declare(strict_types=1);

namespace App\AiChat\Infra\Service\Transport;

use App\Account\Business\Model\OpenCode\ProviderSessionPolicy;

/**
 * Передаёт provider-сессию полем JSON-тела запроса.
 */
final class BodySessionTransport implements SessionTransportApplier
{
    public function apply(
        ProviderSessionPolicy $policy,
        string $sessionId,
        array &$headers,
        array &$body,
        array &$query,
    ): void {
        $body[(string) $policy->key] = $sessionId;
    }
}
