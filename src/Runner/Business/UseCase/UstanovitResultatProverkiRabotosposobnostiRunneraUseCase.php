<?php

namespace App\Runner\Business\UseCase;

use App\Runner\Business\Command\UstanovitResultatProverkiRabotosposobnosti;
use App\Runner\Business\Port\UmeetProveryatRabotosposobnostGolangRunnera;
use App\Runner\Business\Port\RunnerRepository;

final class UstanovitResultatProverkiRabotosposobnostiRunneraUseCase
{
    public function __construct(private RunnerRepository $runnerRepository)
    {
    }

    function handle(UstanovitResultatProverkiRabotosposobnosti $command): void
    {
        $runner = $this->runnerRepository->getById($command->id);

        $runner->ustanovitResultatRabotosposobnosti($command->active);
        $this->runnerRepository->save($runner);
    }
}
