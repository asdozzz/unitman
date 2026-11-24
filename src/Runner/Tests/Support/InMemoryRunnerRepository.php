<?php

declare(strict_types=1);

namespace App\Runner\Tests\Support;

use App\Runner\Business\Model\RunnerState;
use App\Runner\Business\Port\RunnerRepository;

/**
 * In-memory реализация `RunnerRepository` для чистых unit-тестов.
 */
final class InMemoryRunnerRepository implements RunnerRepository
{
    /** @var array<string, RunnerState> */
    private array $runners = [];

    public function save(RunnerState $runner): void
    {
        $this->runners[$runner->getId()] = $runner;
    }

    public function getById(string $id): RunnerState
    {
        $runner = $this->findById($id);

        if ($runner === null) {
            throw new \Exception('runner.state.not_found');
        }

        return $runner;
    }

    public function findById(string $id): ?RunnerState
    {
        return $this->runners[$id] ?? null;
    }

    public function savePublicAddress(string $id, ?string $publicAddress): RunnerState
    {
        $runner = $this->findById($id);

        if ($runner === null) {
            throw new \DomainException('runner.not_found');
        }

        $runner->ustanovitPublicAddress($publicAddress);
        $this->save($runner);

        return $runner;
    }

    /**
     * @return RunnerState[]
     */
    public function getAll(): array
    {
        return array_values($this->runners);
    }
}
