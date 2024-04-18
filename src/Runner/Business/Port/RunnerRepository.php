<?php

namespace App\Runner\Business\Port;

use App\Runner\Business\Model\RunnerState;

interface RunnerRepository
{
    function save(RunnerState $runner): void;
    function getById(string $id): RunnerState;

    function getDefaultRunnerState(): RunnerState;
}
