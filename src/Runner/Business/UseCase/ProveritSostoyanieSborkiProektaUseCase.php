<?php

declare(strict_types=1);

namespace App\Runner\Business\UseCase;

use App\Runner\Business\Model\ProjectBuildState;
use App\Runner\Business\Port\ProjectBuildStateRepository;
use App\Runner\Business\UseCase\ProjectBuildState\ProjectBuildStateInvalidProjectIdException;

/**
 * Проверка факта сборки проекта на конкретном раннере.
 *
 * Контракт: plans/runner-multi-runner.md §3.1.
 *
 * Отсутствие записи — нормальное состояние (`null`, ответ `"built": false`),
 * а не ошибка. Раннер обязан существовать и быть активным; иначе
 * `runner.not_found` / `runner.inactive` (см. §7).
 */
final class ProveritSostoyanieSborkiProektaUseCase
{
    public function __construct(
        private readonly RunnerSelectionService $runnerSelectionService,
        private readonly ProjectBuildStateRepository $projectBuildStateRepository,
        private readonly ProjectBuildStateInputGuard $inputGuard,
    ) {
    }

    /**
     * @throws ProjectBuildStateInvalidProjectIdException
     * @throws \DomainException `runner.not_found` | `runner.inactive`
     */
    public function handle(string $runnerId, string $projectId): ?ProjectBuildState
    {
        $projectId = $this->inputGuard->validateProjectId($projectId);

        // Раннер должен существовать и быть активным.
        $this->runnerSelectionService->getByRunnerId($runnerId);

        return $this->projectBuildStateRepository->find($runnerId, $projectId);
    }
}
