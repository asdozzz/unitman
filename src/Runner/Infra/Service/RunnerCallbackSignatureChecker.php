<?php

declare(strict_types=1);

namespace App\Runner\Infra\Service;

use App\Runner\Business\Port\RunnerRepository;
use App\Runner\Business\Utils\RunnerSecretGenerator;

/**
 * Проверка подписи callback-запросов Golang-раннера (контракт v2).
 *
 * Раннер присылает два заголовка:
 * - `X-Runner-Id` — идентификатор зарегистрированного раннера;
 * - `X-Runner-Signature` — hex(HMAC-SHA256(payload, secret)).
 *
 * Секрет НЕ хранится в БД: он детерминированно вычисляется по `runnerId`
 * (см. {@see RunnerSecretGenerator}). Неизвестный `X-Runner-Id` — это ошибка
 * `runner.not_found` (HTTP 404), неверная подпись — 401 (решает вызывающий
 * код). Сравнение выполняется через `hash_equals` (constant time).
 */
final class RunnerCallbackSignatureChecker
{
    public const HEADER_NAME = 'X-Runner-Signature';
    public const RUNNER_ID_HEADER = 'X-Runner-Id';

    public function __construct(
        private readonly RunnerRepository $runnerRepository,
        private readonly RunnerSecretGenerator $secretGenerator,
    ) {
    }

    /**
     * Подпись сырого тела запроса: health и unitStateNotifications.
     *
     * @throws \DomainException `runner.not_found`, если раннер неизвестен
     */
    public function estPodpisTelaValidna(?string $runnerId, string $body, ?string $signature): bool
    {
        return $this->compare($this->resolveSecret($runnerId), $body, $signature);
    }

    /**
     * Подпись saveSteps: `responseId . stepsContent`.
     *
     * @throws \DomainException `runner.not_found`, если раннер неизвестен
     */
    public function estPodpisValidna(
        ?string $runnerId,
        string $responseId,
        string $stepsContent,
        ?string $signature,
    ): bool {
        return $this->compare(
            $this->resolveSecret($runnerId),
            $responseId . $stepsContent,
            $signature,
        );
    }

    /**
     * Подпись артефакта хода: `workflowId "\n" name "\n" rawBody`.
     *
     * Тело — сырые байты файла, имя — ровно то, что пришло в query (до
     * нормализации). Отдельного timestamp/nonce нет: повторная доставка при
     * retry Temporal идемпотентна по паре `(turnId, name)`.
     *
     * @throws \DomainException `runner.not_found`, если раннер неизвестен
     */
    public function estPodpisFajlaValidna(
        ?string $runnerId,
        string $workflowId,
        string $name,
        string $body,
        ?string $signature,
    ): bool {
        return $this->compare(
            $this->resolveSecret($runnerId),
            $workflowId . "\n" . $name . "\n" . $body,
            $signature,
        );
    }

    /**
     * Подпись GET build-state: HMAC от пустого тела.
     *
     * @throws \DomainException `runner.not_found`, если раннер неизвестен
     */
    public function estPodpisPustogoTelaValidna(?string $runnerId, ?string $signature): bool
    {
        return $this->estPodpisTelaValidna($runnerId, '', $signature);
    }

    /**
     * Разрешает секрет по `X-Runner-Id`.
     *
     * @throws \DomainException `runner.not_found`
     */
    public function resolveSecret(?string $runnerId): string
    {
        if ($runnerId === null || trim($runnerId) === '') {
            throw new \DomainException('runner.not_found');
        }

        if ($this->runnerRepository->findById($runnerId) === null) {
            throw new \DomainException('runner.not_found');
        }

        return $this->secretGenerator->generate($runnerId);
    }

    private function compare(string $secret, string $payload, ?string $signature): bool
    {
        if ($signature === null || $signature === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $payload, $secret), $signature);
    }
}
