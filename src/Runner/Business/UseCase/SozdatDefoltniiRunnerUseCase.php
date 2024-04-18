<?php

namespace App\Runner\Business\UseCase;

use App\Runner\Business\Model\RunnerState;
use App\Runner\Business\Port\CanGenerateGuid;
use App\Runner\Business\Port\RunnerRepository;

final class SozdatDefoltniiRunnerUseCase
{
    public function __construct(private RunnerRepository $runnerRepository, private CanGenerateGuid $canGenerateGuid)
    {
    }

    function handle(): void
    {
        $id = $this->canGenerateGuid->makeGuid();
        $runner = RunnerState::sozdatDefoltniiRunner($id);
        $this->runnerRepository->save($runner);
    }
}
