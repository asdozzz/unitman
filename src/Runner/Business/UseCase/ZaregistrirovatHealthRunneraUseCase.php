<?php

declare(strict_types=1);

namespace App\Runner\Business\UseCase;

use App\Runner\Business\Command\ZaregistrirovatHealthRunnera;
use App\Runner\Business\Model\RunnerState;
use App\Runner\Business\Port\RunnerRepository;
use App\Runner\Business\Port\UmeetObnovlyatStatistikuPoUnitam;
use App\Runner\Business\Port\UmeetSozdavatUvedomlenieOBugroze;
use App\Runner\Business\Utils\RunnerHealthPayloadParser;
use App\Runner\Business\Utils\UgrozyRannerov;
use Psr\Log\LoggerInterface;

final class ZaregistrirovatHealthRunneraUseCase
{
    public function __construct(
        private RunnerRepository $runnerRepository,
        private RunnerHealthPayloadParser $payloadParser,
        private UmeetObnovlyatStatistikuPoUnitam $statisticsUpdater,
        private ?UmeetSozdavatUvedomlenieOBugroze $notifications = null,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function handle(ZaregistrirovatHealthRunnera $command): void
    {
        $runner = $this->runnerRepository->findById($command->runnerId)
            ?? RunnerState::sozdatRunner($command->runnerId, $command->taskQueue);

        $oldLevel = UgrozyRannerov::uroven($runner);

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

        $this->uvedomitOBperekhodeVKriticheskiyUroven($runner, $oldLevel);
    }

    private function uvedomitOBperekhodeVKriticheskiyUroven(RunnerState $runner, string $oldLevel): void
    {
        $newLevel = UgrozyRannerov::uroven($runner);

        $oldCritical = in_array($oldLevel, [UgrozyRannerov::LEVEL_CRITICAL, UgrozyRannerov::LEVEL_BLOCKER], true);
        $newCritical = in_array($newLevel, [UgrozyRannerov::LEVEL_CRITICAL, UgrozyRannerov::LEVEL_BLOCKER], true);

        if ($oldCritical || !$newCritical || $this->notifications === null) {
            return;
        }

        $payload = $this->payloadDlyaUvedomleniya($runner, $newLevel);
        $dedupKey = sprintf(
            'runner_threat:%s:%s:%d',
            $runner->getId(),
            $newLevel,
            $runner->getUnixtimeLastHealth(),
        );

        try {
            $this->notifications->sozdat($runner->getId(), $newLevel, $payload, $dedupKey);
        } catch (\Throwable $error) {
            $this->logger?->error('Не удалось создать уведомление об угрозе раннера.', [
                'runnerId' => $runner->getId(),
                'level' => $newLevel,
                'exception' => $error,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadDlyaUvedomleniya(RunnerState $runner, string $level): array
    {
        $resources = $runner->getResources();
        $hostname = $runner->getHostname() ?? $runner->getId();
        $payload = [
            'runnerId' => $runner->getId(),
            'hostname' => $hostname,
            'level' => $level,
            'blocked' => $runner->isResourcesBlocked(),
        ];

        if ($resources === null) {
            return $payload;
        }

        $payload['hostname'] = $resources->hostname !== '' ? $resources->hostname : $hostname;
        $payload['memoryUsedPercent'] = $resources->memory->usedPercent;
        $payload['disks'] = array_values(array_map(
            static fn (array $disk): array => [
                'path' => (string) ($disk['path'] ?? ''),
                'usedPercent' => (float) ($disk['usedPercent'] ?? 0),
                'level' => (string) ($disk['level'] ?? 'ok'),
            ],
            $resources->disks,
        ));
        $payload['blockReason'] = $resources->blockReason;
        $payload['collectedAt'] = $resources->collectedAt;

        return $payload;
    }
}
