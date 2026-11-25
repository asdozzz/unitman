<?php

declare(strict_types=1);

namespace App\AiChat\Tests\Support;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

/**
 * HttpClientInterface, который запоминает параметры запросов.
 *
 * Делегирует отправку MockHttpClient: только он умеет корректно
 * инициализировать MockResponse через fromRequest(). При этом запоминаются
 * именно те опции (headers/json/timeout), которые передал прокси, — это и
 * нужно для проверки отсутствия утечки состояния.
 */
final class RecordingHttpClient implements HttpClientInterface
{
    /**
     * @var list<array{method: string, url: string, options: array<string, mixed>}>
     */
    public array $requests = [];

    private MockHttpClient $inner;

    public function __construct(ResponseInterface ...$responses)
    {
        $this->inner = new MockHttpClient($responses);
    }

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $this->requests[] = [
            'method' => $method,
            'url' => $url,
            'options' => $options,
        ];

        return $this->inner->request($method, $url, $options);
    }

    public function stream(ResponseInterface|iterable $responses, ?float $timeout = null): ResponseStreamInterface
    {
        return $this->inner->stream($responses, $timeout);
    }

    public function withOptions(array $options): static
    {
        $clone = clone $this;
        $clone->inner = $this->inner->withOptions($options);

        return $clone;
    }
}
