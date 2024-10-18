<?php

namespace App\Runner\Business\UseCase;

use App\Runner\Business\Command\UstanovitResultatProverkiRabotosposobnosti;
use App\Runner\Business\Model\RunnerState\DockerContainerStats;
use App\Runner\Business\Model\RunnerState\MemoryInfo;
use App\Runner\Business\Port\RunnerRepository;

final class UstanovitResultatProverkiRabotosposobnostiRunneraUseCase
{
    public function __construct(private RunnerRepository $runnerRepository)
    {
    }

    function handle(UstanovitResultatProverkiRabotosposobnosti $command): void
    {
        $runner = $this->runnerRepository->getById($command->id);

        $dockerStats = $this->makeDockerStats($command);

        $memInfo = $this->makeMemInfo($command);

        $runner->ustanovitResultatRabotosposobnosti($command->active, $dockerStats, $memInfo);
        $this->runnerRepository->save($runner);
    }

    /**
     * @param UstanovitResultatProverkiRabotosposobnosti $command
     * @return int[]
     */
    private function parseMemInfo(UstanovitResultatProverkiRabotosposobnosti $command): array
    {
        $memInfoArr = explode("\n", $command->memInfo);

        $total = 0;
        $free = 0;

        foreach ($memInfoArr as $memInfoItem) {
            $item = explode(':', $memInfoItem);
            if (empty($item) || empty($item[1])) continue;
            $code = trim($item[0]);
            $value = trim($item[1]);

            if ($code == 'MemTotal') {
                $total = (int)$value;
            }
            if ($code == 'MemAvailable') {
                $free = (int)$value;
            }
        }
        return array($total, $free);
    }

    /**
     * @param UstanovitResultatProverkiRabotosposobnosti $command
     * @return MemoryInfo
     */
    private function makeMemInfo(UstanovitResultatProverkiRabotosposobnosti $command): MemoryInfo
    {
        if (empty($command->memInfo)) {
            $memInfo = new MemoryInfo(0, 0);
        } else {
            list($total, $free) = $this->parseMemInfo($command);

            $memInfo = new MemoryInfo($total, $free);
        }
        return $memInfo;
    }

    /**
     * @param UstanovitResultatProverkiRabotosposobnosti $command
     * @return array
     */
    private function makeDockerStats(UstanovitResultatProverkiRabotosposobnosti $command): array
    {
        $dockerStats = [];
        if (!empty($command->dockerStats)) {
            $statsArr = explode("\n", $command->dockerStats);
            foreach ($statsArr as $stat) {
                $statArr = json_decode($stat, true);
                if (empty($statArr)) continue;
                $dockerStats[] = new DockerContainerStats(
                    $statArr['Container'],
                    $statArr['Name'],
                    $statArr['CPUPerc'],
                    $statArr['MemPerc'],
                    $statArr['MemUsage'],
                    $statArr['NetIO']
                );
            }
        }

        return $dockerStats;
    }
}
