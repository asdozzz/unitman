<?php

declare(strict_types=1);

namespace App\AiChat\Infra\Service;

use App\Account\Business\Model\OpenCode\ProviderSessionPolicy;
use App\AiChat\Infra\Service\Transport\ApplierTransportaPoPolitike;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Stateless-прокси к OpenAI-совместимому `POST {baseUrl}/chat/completions`.
 *
 * ВАЖНО: воркеры RoadRunner долго живут и переиспользуются между запросами
 * разных пользователей. Поэтому в этом классе НЕТ изменяемых свойств:
 * API-ключ, заголовки авторизации, сообщения, инструменты, модель, политика
 * provider-сессии и её идентификатор существуют только как локальные
 * переменные метода. Конструктор хранит лишь неизменяемую конфигурацию
 * (HTTP-клиент, логгер, таймаут и резолвер транспорта).
 *
 * Провайдер влияет только на способ авторизации. Как передавать
 * provider-сессию, решает {@see ProviderSessionPolicy} и её applier: прокси
 * не содержит provider-specific веток для сессии и не генерирует UUID.
 *
 * Заголовки и тело запроса/ответа не логируются: при 4xx/5xx в лог попадают
 * только статус, провайдер и `error.message`/`error.code` из ответа upstream.
 */
final class OpenCodeChatProxy
{
    /** Верхняя граница тела ответа upstream (10 МиБ). */
    public const MAX_RESPONSE_BYTES = 10485760;

    /** Максимальная длина текста ошибки upstream, попадающего в лог. */
    private const MAX_LOGGED_ERROR_LENGTH = 500;

    /** Провайдер, который ждёт ключ в заголовке `x-api-key`. */
    private const PROVIDER_ANTHROPIC = 'anthropic';

    /** Провайдеры, которым авторизация не передаётся вовсе. */
    private const PROVIDERS_BEZ_AVTORIZACII = ['ollama', 'custom'];

    private const ANTHROPIC_VERSION = '2023-06-01';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly float $timeout,
        private readonly ApplierTransportaPoPolitike $applierTransporta,
    ) {
    }

    /**
     * Отправляет чат-запрос и возвращает статус upstream вместе с разобранным телом.
     *
     * `$provider` определяет только способ авторизации.
     *
     * `$policy` определяет, куда положить `$sessionId`: в заголовок, тело,
     * query или никуда. Прокси не генерирует идентификатор сессии: `null`
     * означает «сессия не задана» и не добавляет никаких session-полей.
     *
     * @param list<array<string, mixed>> $messages
     * @param list<array<string, mixed>> $tools
     *
     * @return array{status: int, payload: array<array-key, mixed>}
     *
     * @throws UpstreamUnavailableException                 при транспортной ошибке
     * @throws UpstreamInvalidResponseException             при не-JSON или слишком большом теле
     * @throws \DomainException                             при неизвестном транспорте сессии
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
    ): array {
        $url = $this->sozdatUrl($baseUrl);

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        $provider = strtolower(trim($provider));

        if ($provider === self::PROVIDER_ANTHROPIC) {
            $headers['x-api-key'] = $apiKey;
            $headers['anthropic-version'] = self::ANTHROPIC_VERSION;
        } elseif (!\in_array($provider, self::PROVIDERS_BEZ_AVTORIZACII, true)) {
            $headers['Authorization'] = 'Bearer '.$apiKey;
        }

        $payload = [
            'model' => $model,
            'messages' => $messages,
        ];

        if ($tools !== []) {
            $payload['tools'] = $tools;
        }

        $query = [];

        if ($sessionId !== null && $sessionId !== '') {
            $this->applierTransporta
                ->poluchit($policy)
                ->apply($policy, $sessionId, $headers, $payload, $query);
        }

        if ($query !== []) {
            $url = $this->dopolnitUrlQuery($url, $query);
        }

        try {
            $response = $this->httpClient->request('POST', $url, [
                'headers' => $headers,
                'json' => $payload,
                'timeout' => $this->timeout,
            ]);

            $status = $response->getStatusCode();
            $body = $response->getContent(false);
        } catch (TransportExceptionInterface) {
            throw new UpstreamUnavailableException();
        }

        if (\strlen($body) > self::MAX_RESPONSE_BYTES) {
            throw new UpstreamInvalidResponseException();
        }

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new UpstreamInvalidResponseException();
        }

        if (!\is_array($decoded)) {
            throw new UpstreamInvalidResponseException();
        }

        if ($status >= 400) {
            $this->zalogirovatOshibkuUpstream($status, $provider, $decoded);
        }

        return [
            'status' => $status,
            'payload' => $decoded,
        ];
    }

    /**
     * Собирает URL `chat/completions`, сохраняя query-параметры базового URL.
     *
     * `baseUrl` может содержать query (`?api-version=...`): путь дописывается
     * до него, а не после.
     */
    private function sozdatUrl(string $baseUrl): string
    {
        $baseUrl = rtrim($baseUrl, '/');
        $queryPosition = strpos($baseUrl, '?');

        if ($queryPosition === false) {
            return $baseUrl.'/chat/completions';
        }

        return rtrim(substr($baseUrl, 0, $queryPosition), '/')
            .'/chat/completions'
            .substr($baseUrl, $queryPosition);
    }

    /**
     * Добавляет query-параметры к URL, не ломая уже существующие.
     *
     * @param array<string, string> $query
     */
    private function dopolnitUrlQuery(string $url, array $query): string
    {
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.http_build_query($query);
    }

    /**
     * Пишет warning о 4xx/5xx без секретов и содержимого переписки:
     * только статус, провайдер и текст/код ошибки из тела upstream.
     *
     * @param array<array-key, mixed> $decoded
     */
    private function zalogirovatOshibkuUpstream(int $status, string $provider, array $decoded): void
    {
        $this->logger->warning('AI chat upstream request failed.', [
            'status' => $status,
            'provider' => $provider,
            'error.message' => $this->izvlechPoleOshibki($decoded, 'message'),
            'error.code' => $this->izvlechPoleOshibki($decoded, 'code'),
        ]);
    }

    /**
     * @param array<array-key, mixed> $decoded
     */
    private function izvlechPoleOshibki(array $decoded, string $field): string
    {
        $error = $decoded['error'] ?? null;

        $value = \is_array($error) && \array_key_exists($field, $error)
            ? $error[$field]
            : ($decoded[$field] ?? null);

        if (\is_int($value) || \is_float($value)) {
            $value = (string) $value;
        }

        if (!\is_string($value)) {
            return '';
        }

        return \function_exists('mb_substr')
            ? mb_substr($value, 0, self::MAX_LOGGED_ERROR_LENGTH)
            : substr($value, 0, self::MAX_LOGGED_ERROR_LENGTH);
    }
}
