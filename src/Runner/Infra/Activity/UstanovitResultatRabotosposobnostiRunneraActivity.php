<?php

namespace App\Runner\Infra\Activity;

use App\Runner\Business\Command\UstanovitResultatProverkiRabotosposobnosti;
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

    #[ActivityMethod(name: "UstanovitResultat")]
    function updateState(string $runnerId, bool $active): bool
    {
        $this->useCase->handle(new UstanovitResultatProverkiRabotosposobnosti($runnerId, $active));
        return true;
    }
}
