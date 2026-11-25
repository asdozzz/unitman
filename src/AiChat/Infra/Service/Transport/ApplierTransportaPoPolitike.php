<?php

declare(strict_types=1);

namespace App\AiChat\Infra\Service\Transport;

use App\Account\Business\Model\OpenCode\ProviderSessionPolicy;
use App\Account\Business\Model\OpenCode\ProviderSessionTransport;

/**
 * Выбирает applier по транспорту политики provider-сессии.
 *
 * `match` намеренно без `default`: новый case транспорта ломает выполнение,
 * пока для него не описан соответствующий applier.
 */
final class ApplierTransportaPoPolitike
{
    public function poluchit(ProviderSessionPolicy $policy): SessionTransportApplier
    {
        return match ($policy->transport) {
            ProviderSessionTransport::None => new NoSessionTransport(),
            ProviderSessionTransport::Header => new HeaderSessionTransport(),
            ProviderSessionTransport::Body => new BodySessionTransport(),
            ProviderSessionTransport::Query => new QuerySessionTransport(),
            ProviderSessionTransport::Unknown => new UnknownSessionTransport(),
        };
    }
}
