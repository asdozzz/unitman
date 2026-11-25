<?php

declare(strict_types=1);

namespace App\AiChat\Infra\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Stateless-прокси к OpenAI-совместимому `POST {baseUrl}/chat/completions`.
 *
 * ВАЖНО: воркеры RoadRunner долго живут и переиспользуются между запросами
 * разных пользователей. Поэтому в этом классе НЕТ изменяемых свойств:
 * API-ключ, заголовки авторизации, сообщения, инструменты, модель и базовый
 * URL существуют только как локальные переменные метода. Конструктор хранит
 * лишь неизменяемую конфигурацию (HTTP-клиент, логгер и таймаут).
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

    /** Session-scoped шлюз, требующий заголовок `x-opencode-session`. */
    private const PROVIDER_OPENCODE_GO = 'opencode-go';

    /** Провайдеры, которым авторизация не передаётся вовсе. */
    private const PROVIDERS_BEZ_AVTORIZACII = ['ollama', 'custom'];

    private const ANTHROPIC_VERSION = '2023-06-01';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly float $timeout,
    ) {
    }

    /**
     * Отправляет чат-запрос и возвращает статус upstream вместе с разобранным телом.
     *
     * `$provider` определяет способ авторизации. По умолчанию пустая строка
     * трактуется как «обычный Bearer», что сохраняет поведение прежних
     * вызовов, не знавших о провайдере.
     *
     * `$sessionId` передаётся заголовком `x-opencode-session` только для
     * провайдера `opencode-go`; при пустом значении генерируется UUID v4.
     * Значение используется только как аргумент и нигде не сохраняется.
     *
     * @param list<array<string, mixed>> $messages
     * @param list<array<string, mixed>> $tools
     *
     * @return array{status: int, payload: array<array-key, mixed>}
     *
     * @throws UpstreamUnavailableException      при транспортной ошибке
     * @throws UpstreamInvalidResponseException  при не-JSON или слишком большом теле
     */
    public function otpravit(
        string $baseUrl,
        string $apiKey,
        string $model,
        array $messages,
        array $tools,
        string $provider = '',
        ?string $sessionId = null,
    ): array {
        $url = rtrim($baseUrl, '/').'/chat/completions';

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

        if ($provider === self::PROVIDER_OPENCODE_GO) {
            $headers['x-opencode-session'] = $sessionId !== null && $sessionId !== ''
                ? $sessionId
                : Uuid::v4()->toRfc4122();
        }

        $payload = [
            'model' => $model,
            'messages' => $messages,
        ];

        if ($tools !== []) {
            $payload['tools'] = $tools;
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
