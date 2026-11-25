<?php

declare(strict_types=1);

namespace App\AiChat\Infra\Service\Transport;

use App\Account\Business\Model\OpenCode\ProviderSessionPolicy;

/**
 * Провайдер не использует provider-сессию: запрос не меняется.
 */
final class NoSessionTransport implements SessionTransportApplier
{
    public function apply(
        ProviderSessionPolicy $policy,
        string $sessionId,
        array &$headers,
        array &$body,
        array &$query,
    ): void {
    }
}
