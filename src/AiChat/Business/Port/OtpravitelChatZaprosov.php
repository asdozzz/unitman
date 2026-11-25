<?php

declare(strict_types=1);

namespace App\AiChat\Business\Port;

use App\Account\Business\Model\OpenCode\ProviderSessionPolicy;

/**
 * Порт отправки одного chat-запроса в OpenAI-совместимый upstream.
 *
 * Введён, чтобы бизнес-слой не зависел от конкретного HTTP-клиента и чтобы
 * use case можно было тестировать без сети. Инфраструктурная реализация —
 * адаптер над `OpenCodeChatProxy`.
 *
 * Состояние провайдера и пользователя не хранится: всё передаётся
 * аргументами одного вызова.
 */
interface OtpravitelChatZaprosov
{
    /**
     * @param list<array<string, mixed>> $messages
     * @param list<array<string, mixed>> $tools
     *
     * @return array{status: int, payload: array<array-key, mixed>}
     *
     * @throws \App\AiChat\Infra\Service\UpstreamUnavailableException    при транспортной ошибке
     * @throws \App\AiChat\Infra\Service\UpstreamInvalidResponseException при не-JSON или большом теле
     */
    public function otpravit(
        string $baseUrl,
        string $apiKey,
        string $model,
        array $messages,
        array $tools,
        string $provider,
        ProviderSessionPolicy $policy,
        ?string $sessionId = null,
    ): array;
}
