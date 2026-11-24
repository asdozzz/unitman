<?php

declare(strict_types=1);

namespace App\Runner\Business\UseCase;

use App\Runner\Business\Model\RunnerState;
use App\Runner\Business\Port\RunnerRepository;
use App\Runner\Business\UseCase\RegisterRunner\RegisterRunnerResult;
use App\Runner\Business\UseCase\RegisterRunner\RunnerRegistrationFailedException;
use App\Runner\Business\UseCase\RegisterRunner\RunnerRegistrationInvalidPayloadException;
use App\Runner\Business\Utils\RunnerIdGenerator;
use App\Runner\Business\Utils\RunnerSecretGenerator;

/**
 * Регистрация Go-раннера (multi-runner, контракт v2).
 *
 * Вход — `{"hostname":"...","version":"..."}`. Публичный эндпоинт, без
 * bootstrap-подписи. Адрес раннера в регистрации не передаётся: публичный
 * `publicAddress` задаётся администратором отдельно после регистрации.
 *
 * `runnerId` — UUID v4; `taskQueue === runnerId` verbatim.
 * `secret = hash_hmac('sha256', runnerId, masterKey)` вычисляется
 * детерминированно, отдаётся ровно один раз и НЕ сохраняется в БД.
 */
final class RegisterRunnerUseCase
{
    private const DEFAULT_API_PORT = 8082;
    private const DEFAULT_TEMPORAL_PORT = 7233;
    private const DEFAULT_REGISTRY_PORT = 5000;

    public function __construct(
        private readonly RunnerRepository $runnerRepository,
        private readonly RunnerIdGenerator $runnerIdGenerator,
        private readonly RunnerSecretGenerator $secretGenerator,
        private readonly ?string $registryPublicAddress = null,
        private readonly int|string|null $runnerApiPort = null,
        private readonly int|string|null $runnerTemporalPort = null,
        private readonly int|string|null $runnerRegistryPort = null,
    ) {
    }

    /**
     * @throws RunnerRegistrationInvalidPayloadException
     * @throws RunnerRegistrationFailedException
     */
    public function handle(string $rawBody): RegisterRunnerResult
    {
        $payload = $this->decodePayload($rawBody);
        $hostname = $this->extractOptionalString($payload, 'hostname');

        $runnerId = $this->runnerIdGenerator->generate();
        $registryHost = $this->registryPublicAddress ?? '';
        $registeredAt = time();
        $secret = $this->secretGenerator->generate($runnerId);

        $state = RunnerState::sozdatZaregistrirovanniiRunnerV2(
            $runnerId,
            $registryHost === '' ? null : $registryHost,
            $hostname,
            $registeredAt,
        );

        try {
            $this->runnerRepository->save($state);
        } catch (\Throwable $exception) {
            throw new RunnerRegistrationFailedException($exception);
        }

        return new RegisterRunnerResult(
            runnerId: $runnerId,
            // taskQueue всегда строго равен runnerId (без префиксов).
            taskQueue: $runnerId,
            secret: $secret,
            registryHost: $registryHost,
            ports: [
                'api' => $this->resolvePort($this->runnerApiPort, self::DEFAULT_API_PORT),
                'temporal' => $this->resolvePort($this->runnerTemporalPort, self::DEFAULT_TEMPORAL_PORT),
                'registry' => $this->resolvePort($this->runnerRegistryPort, self::DEFAULT_REGISTRY_PORT),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decodePayload(string $rawBody): array
    {
        try {
            $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new RunnerRegistrationInvalidPayloadException();
        }

        if (!is_array($payload)) {
            throw new RunnerRegistrationInvalidPayloadException();
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractOptionalString(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    private function resolvePort(int|string|null $port, int $default): int
    {
        if (is_int($port)) {
            return $port > 0 ? $port : $default;
        }

        if (is_string($port) && ctype_digit(trim($port))) {
            $resolved = (int) trim($port);

            return $resolved > 0 ? $resolved : $default;
        }

        return $default;
    }
}
