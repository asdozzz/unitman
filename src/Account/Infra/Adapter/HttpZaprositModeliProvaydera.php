<?php

declare(strict_types=1);

namespace App\Account\Infra\Adapter;

use App\Account\Business\Model\OpenCode\OshibkaZaprosaModelei;
use App\Account\Business\Model\OpenCode\ProvayderOpenCode;
use App\Account\Business\Port\ZaprositModeliProvaydera;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * HTTP-адаптер live-списка моделей провайдера OpenAI-совместимого API.
 *
 * Безопасность:
 *  - URL строится ТОЛЬКО из каталога провайдера (никакого baseUrl из запроса);
 *  - ключ передаётся только в заголовке и никогда не попадает в query/логи;
 *  - таймаут 5 секунд, лимит ответа 1 МБ.
 */
final class HttpZaprositModeliProvaydera implements ZaprositModeliProvaydera
{
    private const TIMEOUT = 5.0;
    private const MAX_BYTES = 1048576;
    private const ANTHROPIC_VERSION = '2023-06-01';

    public function __construct(private HttpClientInterface $httpClient)
    {
    }

    public function zaprosit(ProvayderOpenCode $provider, string $apiKey): array
    {
        if (!$provider->podderzhivaetSpisokModelei() || $provider->baseUrl() === '') {
            throw new \DomainException('account.opencode_credential.provider_list_not_supported');
        }

        $url = rtrim($provider->baseUrl(), '/') . $provider->modelsPath();

        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => $this->headers($provider, $apiKey),
                'timeout' => self::TIMEOUT,
                'max_duration' => self::TIMEOUT,
            ]);

            $status = $response->getStatusCode();
            $content = $response->getContent(false);
        } catch (HttpClientExceptionInterface $exception) {
            throw new OshibkaZaprosaModelei('account.opencode_credential.provider_unavailable');
        }

        if ($status >= 400) {
            throw new OshibkaZaprosaModelei('account.opencode_credential.provider_error', $status);
        }

        if (\strlen($content) > self::MAX_BYTES) {
            throw new OshibkaZaprosaModelei('account.opencode_credential.provider_response_too_large', $status);
        }

        return $this->razobrat($content, $status);
    }

    /**
     * @return array<string, string>
     */
    private function headers(ProvayderOpenCode $provider, string $apiKey): array
    {
        if ($provider->authStyle() === 'none') {
            return ['accept' => 'application/json'];
        }

        if ($apiKey === '') {
            throw new \DomainException('account.opencode_credential.empty_api_key');
        }

        if ($provider->authStyle() === 'x-api-key') {
            return [
                'x-api-key' => $apiKey,
                'anthropic-version' => self::ANTHROPIC_VERSION,
                'accept' => 'application/json',
            ];
        }

        return [
            'Authorization' => 'Bearer ' . $apiKey,
            'accept' => 'application/json',
        ];
    }

    /**
     * Разбирает ответ провайдера: `data[].id` (OpenAI) и `models[].name`
     * (Anthropic/Google), плюс плоский массив строк как fallback.
     *
     * @return list<string>
     */
    private function razobrat(string $content, ?int $status): array
    {
        $data = json_decode($content, true);

        if (!\is_array($data)) {
            throw new OshibkaZaprosaModelei('account.opencode_credential.provider_invalid_response', $status);
        }

        $models = [];

        if (isset($data['data']) && \is_array($data['data'])) {
            foreach ($data['data'] as $item) {
                if (\is_array($item) && isset($item['id']) && \is_string($item['id'])) {
                    $models[] = $item['id'];
                }
            }
        }

        if (isset($data['models']) && \is_array($data['models'])) {
            foreach ($data['models'] as $item) {
                if (\is_array($item) && isset($item['name']) && \is_string($item['name'])) {
                    $models[] = $item['name'];
                } elseif (\is_string($item)) {
                    $models[] = $item;
                }
            }
        }

        if ($models === [] && array_is_list($data)) {
            foreach ($data as $item) {
                if (\is_string($item)) {
                    $models[] = $item;
                }
            }
        }

        $models = array_values(array_unique(array_filter(
            array_map(static fn (string $model): string => trim($model), $models),
            static fn (string $model): bool => $model !== '',
        )));

        if ($models === []) {
            throw new OshibkaZaprosaModelei('account.opencode_credential.provider_empty_response', $status);
        }

        return $models;
    }
}
