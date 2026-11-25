<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Model\Unit;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\Unit\UmeetSozdavatUvedomlenieOZapuskeUnita;
use Psr\Log\LoggerInterface;

final class UstanovitResultatZapuskaUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
        private RunnerService $runnerService,
        private ?UmeetSozdavatUvedomlenieOZapuskeUnita $notifications = null,
        private ?LoggerInterface $logger = null,
    )
    {
    }

    function handle(string $unitId, string $jobId): void
    {
        $unit = $this->unitRepository->getById($unitId);
        $resultatZapuska = $this->runnerService->poluchitResultatZapuskaUnita($jobId);
        if ($resultatZapuska->success) {
            $unit->ustanovitUspehZapuska($jobId,$resultatZapuska->steps);
        } else {
            $unit->ustanovitOshibkuZapuska($jobId,$resultatZapuska->steps);
        }
        $this->unitRepository->save($unit);

        if ($resultatZapuska->success) {
            $this->uvedomitOZapuske($unit, $jobId);
        }
    }

    private function uvedomitOZapuske(Unit $unit, string $jobId): void
    {
        $accountId = $unit->getAuthorId();

        if ($accountId === null || $accountId === '' || $this->notifications === null) {
            return;
        }

        $unitId = $unit->getId();
        $payload = [
            'unitId' => $unitId,
            'unitName' => $this->bezOshibki(static fn (): string => $unit->getName()) ?? $unitId,
            'projectId' => $this->bezOshibki(static fn (): string => $unit->getProjectId()) ?? '',
            'branch' => $this->bezOshibki(static fn (): string => $unit->getBranch()) ?? '',
            'jobId' => $jobId,
            'startedAt' => time(),
        ];

        try {
            $this->notifications->sozdat(
                $accountId,
                $payload,
                sprintf('unit_started:%s:%s', $unitId, $jobId),
            );
        } catch (\Throwable $error) {
            $this->logger?->error('Не удалось создать уведомление о запуске юнита.', [
                'unitId' => $unitId,
                'jobId' => $jobId,
                'exception' => $error,
            ]);
        }
    }

    /**
     * @param callable(): string $callback
     */
    private function bezOshibki(callable $callback): ?string
    {
        try {
            return $callback();
        } catch (\Throwable) {
            return null;
        }
    }
}
