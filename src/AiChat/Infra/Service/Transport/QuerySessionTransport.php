<?php

declare(strict_types=1);

namespace App\AiChat\Infra\Service\Transport;

use App\Account\Business\Model\OpenCode\ProviderSessionPolicy;

/**
 * Передаёт provider-сессию query-параметром URL.
 */
final class QuerySessionTransport implements SessionTransportApplier
{
    public function apply(
        ProviderSessionPolicy $policy,
        string $sessionId,
        array &$headers,
        array &$body,
        array &$query,
    ): void {
        $query[(string) $policy->key] = $sessionId;
    }
}
