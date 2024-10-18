<?php

namespace App\Runner\Infra\Activity;

use App\Runner\Business\Command\UstanovitResultatProverkiRabotosposobnosti;
use App\Runner\Business\Model\GolangRunner\Runner\RunnerHealthCheckResult;
use App\Runner\Business\Model\RunnerState;
use App\Runner\Business\UseCase\UstanovitResultatProverkiRabotosposobnostiRunneraUseCase;
use App\Runner\Infra\Repository\SqlRunnerStateRepository;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;

#[ActivityInterface(prefix:"")]
final class UstanovitResultatRabotosposobnostiRunneraActivity
{
    public function __construct(private UstanovitResultatProverkiRabotosposobnostiRunneraUseCase $useCase, private SqlRunnerStateRepository $sqlRunnerStateRepository)
    {
    }

    /**
     * @return array
     * */
    #[ActivityMethod(name: "PoluchitSpisokRunnerov")]
    function getAll(): array
    {
        return $this->sqlRunnerStateRepository->getAll();
    }

    #[ActivityMethod(name: "setErrorState")]
    function setErrorState(string $runnerId): bool
    {
        $this->useCase->handle(new UstanovitResultatProverkiRabotosposobnosti($runnerId, false));
        return true;
    }

    #[ActivityMethod(name: "setSuccessState")]
    function setSuccessState(string $runnerId, RunnerHealthCheckResult $result): bool
    {
        $this->useCase->handle(new UstanovitResultatProverkiRabotosposobnosti($runnerId, true, $result->DockerStats, $result->MemInfo));
        return true;
    }
}
