<?php

declare(strict_types=1);

namespace App\AiChat\Infra\Adapter;

use App\Account\Business\Model\OpenCode\ProviderSessionPolicy;
use App\AiChat\Business\Port\OtpravitelChatZaprosov;
use App\AiChat\Infra\Service\OpenCodeChatProxy;

/**
 * Адаптер порта {@see OtpravitelChatZaprosov} над `OpenCodeChatProxy`.
 *
 * Прокси объявлен `final` и не может быть замокан напрямую, поэтому бизнес
 * зависит от интерфейса, а этот класс лишь делегирует вызов.
 */
final class OpenCodeChatProxyOtpravitel implements OtpravitelChatZaprosov
{
    public function __construct(private readonly OpenCodeChatProxy $proxy)
    {
    }

    public function otpravit(
        string $baseUrl,
        string $apiKey,
        string $model,
        array $messages,
        array $tools,
        string $provider,
        ProviderSessionPolicy $policy,
        ?string $sessionId = null,
    ): array {
        return $this->proxy->otpravit(
            $baseUrl,
            $apiKey,
            $model,
            $messages,
            $tools,
            $provider,
            $policy,
            $sessionId,
        );
    }
}
