<?php

declare(strict_types=1);

namespace App\Runner\Business\UseCase\PublicAddress;

use App\Runner\Business\Port\RunnerRepository;
use App\Runner\Business\Port\UmeetProveryatPravaAdmina;
use App\Runner\Business\Utils\RunnerPublicAddressValidator;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * `POST /api/runner/{runnerId}/public-address/check` — консультативная
 * проверка доступности адреса.
 *
 * Выполняется best-effort `GET <publicAddress>/__unitman/health` с коротким
 * таймаутом. Локальный дев-адрес (`localhost`/`127.0.0.1` на порту 8087)
 * перенаправляется на внутреннее имя прокси `uproxy:80`: `localhost` внутри
 * контейнера бэкенда указывает на сам контейнер, а не на хост с опубликованным
 * портом nginx-proxy.
 * Недоступность цели никогда не превращается в ошибку запроса и
 * ничего не сохраняет: сохранение адреса от проверки не зависит.
 */
final class ProveritPublicAddressUseCase
{
    private const REQUEST_TIMEOUT_SECONDS = 3;
    private const MAX_DURATION_SECONDS = 5;
    private const MAX_ERROR_LENGTH = 512;

    // Служебный health-эндпоинт nginx-прокси (см. .docker/proxy/nginx.conf).
    private const HEALTH_PATH = '/__unitman/health';

    // Дев-адрес прокси: внутри контейнера бэкенда `localhost` указывает на сам
    // контейнер, поэтому проверяем по внутреннему имени в сети unitman_default
    // и контейнерному порту nginx (80), а не по опубликованному 8087.
    private const LOCAL_PROXY_HOSTS = ['localhost', '127.0.0.1', '::1', '[::1]'];
    private const LOCAL_PROXY_PORT = 8087;
    private const INTERNAL_PROXY_HOST = 'uproxy';
    private const INTERNAL_PROXY_PORT = 80;

    public function __construct(
        private readonly RunnerRepository $runnerRepository,
        private readonly UmeetProveryatPravaAdmina $security,
        private readonly RunnerPublicAddressValidator $validator,
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * @return array{reachable: bool, error?: string}
     *
     * @throws \DomainException      `runner.not_found`
     * @throws \InvalidArgumentException `runner.public_address.invalid`
     */
    public function handle(string $runnerId, string $rawPublicAddress): array
    {
        if (!$this->security->estAdministrator()) {
            throw new AccessDeniedHttpException('security.access_denied');
        }

        $publicAddress = $this->validator->validate($rawPublicAddress);

        if ($this->runnerRepository->findById($runnerId) === null) {
            throw new \DomainException('runner.not_found');
        }

        $url = $this->buildProbeUrl($publicAddress);

        try {
            // `connect_timeout` не поддерживается HttpClientInterface:
            // CurlHttpClient отвергает его. Ограничиваем проверку idle-таймаутом
            // и общим `max_duration`.
            $response = $this->httpClient->request('GET', $url, [
                'timeout' => self::REQUEST_TIMEOUT_SECONDS,
                'max_duration' => self::MAX_DURATION_SECONDS,
                'max_redirects' => 0,
                'headers' => ['Accept' => 'application/json'],
            ]);

            $status = $response->getStatusCode();

            if ($status >= 200 && $status < 400) {
                return ['reachable' => true];
            }

            return ['reachable' => false, 'error' => 'http_status_' . $status];
        } catch (\Throwable $exception) {
            return ['reachable' => false, 'error' => $this->describeError($exception)];
        }
    }

    /**
     * Собирает URL проверки: `<publicAddress>/__unitman/health`.
     *
     * Если адрес указывает на локальный дев-прокси, host/port заменяются на
     * внутреннее имя `uproxy:80` — иначе запрос уйдёт на loopback контейнера
     * бэкенда. Query/fragment отбрасываются: health-путь должен быть последним
     * сегментом.
     */
    private function buildProbeUrl(string $publicAddress): string
    {
        $parts = parse_url($publicAddress);

        if (!is_array($parts)) {
            return rtrim($publicAddress, '/') . self::HEALTH_PATH;
        }

        $scheme = strtolower($parts['scheme'] ?? 'http');
        $host = $parts['host'] ?? '';
        $port = $parts['port'] ?? null;

        if (in_array(strtolower($host), self::LOCAL_PROXY_HOSTS, true)
            && $port === self::LOCAL_PROXY_PORT
        ) {
            $scheme = 'http';
            $host = self::INTERNAL_PROXY_HOST;
            $port = self::INTERNAL_PROXY_PORT;
        }

        $url = $scheme . '://' . $host;

        if ($port !== null) {
            $url .= ':' . $port;
        }

        return $url . rtrim($parts['path'] ?? '', '/') . self::HEALTH_PATH;
    }

    private function describeError(\Throwable $exception): string
    {
        $message = trim($exception->getMessage());

        if ($message === '') {
            $message = $exception::class;
        }

        return mb_substr($message, 0, self::MAX_ERROR_LENGTH);
    }
}
