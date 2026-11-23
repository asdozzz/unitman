<?php

declare(strict_types=1);

namespace App\Runner\Business\UseCase;

use App\Runner\Business\Command\ZaregistrirovatHealthRunnera;
use App\Runner\Business\Model\RunnerState;
use App\Runner\Business\Port\RunnerRepository;
use App\Runner\Business\Port\UmeetObnovlyatStatistikuPoUnitam;
use App\Runner\Business\Utils\RunnerHealthPayloadParser;

final class ZaregistrirovatHealthRunneraUseCase
{
    public function __construct(
        private RunnerRepository $runnerRepository,
        private RunnerHealthPayloadParser $payloadParser,
        private UmeetObnovlyatStatistikuPoUnitam $statisticsUpdater,
    ) {
    }

    public function handle(ZaregistrirovatHealthRunnera $command): void
    {
        $runner = $this->runnerRepository->findById($command->runnerId)
            ?? RunnerState::sozdatRunner($command->runnerId, $command->taskQueue);

        $dockerStats = $this->payloadParser->dockerStats($command->dockerStats);
        $memInfo = $this->payloadParser->memoryInfo($command->memInfo);
        $resources = $this->payloadParser->resources($command->resources);

        $runner->ustanovitResultatRabotosposobnosti(
            $command->active,
            $dockerStats,
            $memInfo,
            $command->unixtime ?? time(),
            $resources,
        );

        $this->runnerRepository->save($runner);

        $this->statisticsUpdater->obnovitStatistikuPoUnitam($dockerStats);
    }
}
