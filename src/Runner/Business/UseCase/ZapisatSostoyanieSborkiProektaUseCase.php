<?php

declare(strict_types=1);

namespace App\Runner\Business\UseCase;

use App\Runner\Business\Model\ProjectBuildState;
use App\Runner\Business\Port\ProjectBuildStateRepository;
use App\Runner\Business\UseCase\ProjectBuildState\ProjectBuildStateInvalidPayloadException;
use App\Runner\Business\UseCase\ProjectBuildState\ProjectBuildStateInvalidProjectIdException;

/**
 * Идемпотентная запись факта сборки проекта на раннере.
 *
 * Контракт: plans/runner-multi-runner.md §3.2. Upsert по
 * `(runner_id, project_id)` выполняет репозиторий, поэтому повторный вызов и
 * конкурентные вызовы не создают дублей и не затрагивают чужие записи:
 * `runnerId` и `projectId` берутся из пути запроса.
 */
final class ZapisatSostoyanieSborkiProektaUseCase
{
    public function __construct(
        private readonly RunnerSelectionService $runnerSelectionService,
        private readonly ProjectBuildStateRepository $projectBuildStateRepository,
        private readonly ProjectBuildStateInputGuard $inputGuard,
    ) {
    }

    /**
     * @throws ProjectBuildStateInvalidProjectIdException
     * @throws ProjectBuildStateInvalidPayloadException
     * @throws \DomainException `runner.not_found` | `runner.inactive`
     */
    public function handlePayload(string $runnerId, string $projectId, string $rawBody): ProjectBuildState
    {
        $payload = $this->inputGuard->parseRecordPayload($rawBody);

        return $this->handle(
            $runnerId,
            $projectId,
            $payload['builtAt'],
            $payload['commit'],
            $payload['branch'],
        );
    }

    /**
     * @throws ProjectBuildStateInvalidProjectIdException
     * @throws \DomainException `runner.not_found` | `runner.inactive`
     */
    public function handle(
        string $runnerId,
        string $projectId,
        ?int $builtAt = null,
        ?string $commit = null,
        ?string $branch = null,
    ): ProjectBuildState {
        $projectId = $this->inputGuard->validateProjectId($projectId);

        // Раннер должен существовать и быть активным.
        $this->runnerSelectionService->getByRunnerId($runnerId);

        return $this->projectBuildStateRepository->markBuilt(
            $runnerId,
            $projectId,
            $commit,
            $branch,
            $builtAt,
        );
    }
}
