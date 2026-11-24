<?php

declare(strict_types=1);

namespace App\Runner\Business\UseCase;

use App\Runner\Business\Port\RunnerRepository;
use App\Runner\Business\UseCase\ProjectBuildState\ProjectBuildStateInvalidSignatureException;
use App\Runner\Business\Utils\RunnerSecretGenerator;

/**
 * Подпись callback-запросов build-state (контракт v2).
 *
 * Раннер присылает `X-Runner-Id` и `X-Runner-Signature`. Секрет разрешается
 * по `runnerId` детерминированно (см. {@see RunnerSecretGenerator}), в БД он
 * не хранится:
 * - GET  — `hex(hmac_sha256('', secret))` (пустое тело);
 * - PUT  — `hex(hmac_sha256(raw_body, secret))`.
 *
 * Неизвестный раннер — `\DomainException('runner.not_found')` (HTTP 404),
 * неверная подпись — {@see ProjectBuildStateInvalidSignatureException} (401).
 * Сравнение constant-time через `hash_equals`.
 */
final class ProjectBuildStateSignatureVerifier
{
    public const HEADER_NAME = 'X-Runner-Signature';
    public const RUNNER_ID_HEADER = 'X-Runner-Id';

    public function __construct(
        private readonly RunnerRepository $runnerRepository,
        private readonly RunnerSecretGenerator $secretGenerator,
    ) {
    }

    /**
     * @throws ProjectBuildStateInvalidSignatureException
     * @throws \DomainException `runner.not_found`
     */
    public function assertValid(string $runnerId, string $rawBody, ?string $signature): void
    {
        if ($runnerId === '' || $this->runnerRepository->findById($runnerId) === null) {
            throw new \DomainException('runner.not_found');
        }

        if ($signature === null || $signature === '') {
            throw new ProjectBuildStateInvalidSignatureException();
        }

        $expected = hash_hmac(
            'sha256',
            $rawBody,
            $this->secretGenerator->generate($runnerId),
        );

        if (!hash_equals($expected, $signature)) {
            throw new ProjectBuildStateInvalidSignatureException();
        }
    }
}
