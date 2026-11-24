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
 * Выполняется best-effort `GET <publicAddress>` с коротким таймаутом.
 * Недоступность цели никогда не превращается в ошибку запроса и
 * ничего не сохраняет: сохранение адреса от проверки не зависит.
 */
final class ProveritPublicAddressUseCase
{
    private const REQUEST_TIMEOUT_SECONDS = 3;
    private const MAX_DURATION_SECONDS = 5;
    private const MAX_ERROR_LENGTH = 512;

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

        $url = rtrim($publicAddress, '/');

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

    private function describeError(\Throwable $exception): string
    {
        $message = trim($exception->getMessage());

        if ($message === '') {
            $message = $exception::class;
        }

        return mb_substr($message, 0, self::MAX_ERROR_LENGTH);
    }
}
