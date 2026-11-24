<?php

declare(strict_types=1);

namespace App\Runner\Business\UseCase;

use App\Runner\Business\Model\RunnerState;
use App\Runner\Business\Port\RunnerRepository;
use App\Runner\Business\Port\RunnerSelector;
use App\Unitman\Business\Port\Unit\UnitRunnerRepository;

/**
 * Единая точка выбора раннера для всех операций модуля Runner.
 *
 * Контракт: `plans/runner-multi-runner.md` §6 и §7.
 *
 * Приоритет разрешения:
 * 1. явно переданный `taskQueue` (вызывающая сторона уже знает раннер);
 * 2. явно переданный `runnerId`;
 * 3. жёсткая привязка юнита (`unit_runner`) — привязанный раннер не
 *    переносится автоматически;
 * 4. выбор самого свободного живого раннера (`RunnerSelector`).
 *
 * Для явно адресованного раннера проверяется только `active`: свежесть
 * health и блокировка ресурсов — критерии *выбора*, а не адресации уже
 * привязанного юнита. Их проверяет `RunnerSelector` для кандидатов.
 */
final class RunnerSelectionService
{
    public function __construct(
        private readonly RunnerRepository $runnerRepository,
        private readonly RunnerSelector $runnerSelector,
        private readonly UnitRunnerRepository $unitRunnerRepository,
    ) {
    }

    /**
     * Свободный выбор возможен только среди раннеров с публичным адресом:
     * такой раннер можно адресовать для сборки и построения ссылок.
     *
     * @throws \DomainException `runner.not_found`, если нет подходящих раннеров
     */
    public function select(): RunnerState
    {
        $runners = array_values(array_filter(
            $this->runnerRepository->getAll(),
            static fn (RunnerState $runner): bool => $runner->hasPublicAddress(),
        ));

        $boundUnitsByRunner = [];
        foreach ($runners as $runner) {
            $boundUnitsByRunner[$runner->getId()] = $this->unitRunnerRepository
                ->countByRunnerId($runner->getId());
        }

        return $this->runnerSelector->select($runners, $boundUnitsByRunner);
    }

    public function getTaskQueue(): string
    {
        return $this->select()->getTaskQueue();
    }

    /**
     * Разрешает раннер для конкретной операции.
     *
     * @throws \DomainException `runner.not_found` | `runner.inactive`
     */
    public function resolve(
        ?string $taskQueue = null,
        ?string $runnerId = null,
        ?string $unitId = null,
    ): RunnerState {
        if ($taskQueue !== null && $taskQueue !== '') {
            return $this->getByTaskQueue($taskQueue);
        }

        if ($runnerId !== null && $runnerId !== '') {
            return $this->getByRunnerId($runnerId);
        }

        if ($unitId !== null && $unitId !== '') {
            $bound = $this->findByUnitId($unitId);
            if ($bound !== null) {
                return $bound;
            }
        }

        return $this->select();
    }

    /**
     * @throws \DomainException `runner.not_found` | `runner.inactive`
     */
    public function getByRunnerId(string $runnerId): RunnerState
    {
        $state = $this->runnerRepository->findById($runnerId);

        if ($state === null) {
            throw new \DomainException('runner.not_found');
        }

        return $this->assertActive($state);
    }

    /**
     * Для зарегистрированного раннера `taskQueue === runnerId`; поиск по
     * очереди сохраняет совместимость с записями, где поле было задано
     * отдельно.
     *
     * @throws \DomainException `runner.not_found` | `runner.inactive`
     */
    public function getByTaskQueue(string $taskQueue): RunnerState
    {
        foreach ($this->runnerRepository->getAll() as $state) {
            if ($state->getTaskQueue() === $taskQueue) {
                return $this->assertActive($state);
            }
        }

        // taskQueue зарегистрированного раннера — это его runnerId.
        return $this->getByRunnerId($taskQueue);
    }

    /**
     * @throws \DomainException `runner.not_found` | `runner.inactive`
     */
    public function findByUnitId(string $unitId): ?RunnerState
    {
        $runnerId = $this->unitRunnerRepository->findRunnerIdByUnitId($unitId);

        if ($runnerId === null) {
            return null;
        }

        return $this->getByRunnerId($runnerId);
    }

    /**
     * @throws \DomainException `runner.inactive`
     */
    private function assertActive(RunnerState $state): RunnerState
    {
        if (!$state->isActive()) {
            throw new \DomainException('runner.inactive');
        }

        return $state;
    }
}
