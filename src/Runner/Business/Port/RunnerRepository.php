<?php

declare(strict_types=1);

namespace App\Runner\Business\Port;

use App\Runner\Business\Model\RunnerState;

interface RunnerRepository
{
    function save(RunnerState $runner): void;
    function getById(string $id): RunnerState;

    function findById(string $id): ?RunnerState;

    /**
     * Устанавливает или очищает публичный адрес раннера.
     *
     * @throws \DomainException `runner.not_found`, если раннер неизвестен
     */
    function savePublicAddress(string $id, ?string $publicAddress): RunnerState;

    /**
     * @return RunnerState[]
     */
    function getAll(): array;
}
