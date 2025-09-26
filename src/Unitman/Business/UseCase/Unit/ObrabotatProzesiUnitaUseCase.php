<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Model\Unit;
use App\Unitman\Business\Model\Unit\Runner\RunnerJob;
use App\Unitman\Business\Model\Unit\Runner\RunnerJobType;
use App\Unitman\Business\Port\CanGeneateGuid;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;

final class ObrabotatProzesiUnitaUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
        private SobratUnitUseCase $sobratUnitUseCase,
        private UstanovitResultatSborkiUnitaUseCase $resultatSborkiUnitaUseCase,
        private ObnovitKodUnitaUseCase $obnovitKodUnitaUseCase,
        private UstanovitResultatObnovleniyaUnitaUseCase $resultatObnovleniyaUnitaUseCase,
        private PodgotovitUnitKZapuskuUseCase $podgotovitUnitKZapuskuUseCase,
        private UstanovitResultatPodgotovkiUnitaUseCase $ustanovitResultatPodgotovkiUnitaUseCase,
        private ZapustitUnitUseCase $zapustitUnitUseCase,
        private UstanovitResultatZapuskaUseCase $ustanovitResultatZapuskaUseCase,
        private OstanovitUnitUseCase $ostanovitUnitUseCase,
        private UstanovitResultatOstanovkiUnitaUseCase $ustanovitResultatOstanovkiUnitaUseCase,
        private SbrositPodgotovkuUnitaUseCase $sbrositPodgotovkuUnitaUseCase,
        private UstanovitResultatSbrosaPodgotovkiUseCase $ustanovitResultatSbrosaPodgotovkiUseCase,
        private UdalitUnitUseCase $udalitUnitUseCase,
        private UstanovitResultatUdaleniyaUseCase $ustanovitResultatUdaleniyaUseCase,
        private VipolnitDeistviyeUseCase $vipolnitDeistviyeUseCase,
        private UstanovitResultatDeistviyaUseCase $ustanovitResultatDeistviyaUseCase,
        private UstanovitDefoltniiKonfigUseCase $ustanovitDefoltniiKonfigUseCase,
        private ProveritKonteinerUnitaUseCase $proveritKonteinerUnitaUseCase
    )
    {
    }
    function handle(string $unitId): void
    {
        $unit = $this->unitRepository->getById($unitId);
        $job = $unit->poluchitJobuDlyObravotki();

        if (empty($job)) {
            $this->proveritKonteinerUnitaUseCase->handle($unitId);
            return;
        }

        match ($job->getType()) {
            RunnerJobType::SBORKA => $this->sborka($unitId, $job),
            RunnerJobType::OBNOVLENIE => $this->obnovlenie($unitId, $job),
            RunnerJobType::PODGOTOVKA => $this->podgotovka($unitId, $job),
            RunnerJobType::ZAPUSK => $this->zapusk($unitId, $job),
            RunnerJobType::OSTANOVKA => $this->ostanovka($unitId, $job),
            RunnerJobType::SBROS_PODGOTOVKI => $this->sbrosPodgotovki($unitId, $job),
            RunnerJobType::UDALENIE => $this->udalenie($unitId, $job),
            RunnerJobType::DEISTVIE => $this->deistvie($unitId, $job),
            default => throw new \DomainException('unit.obrabotka_prozesa.tip_ne_opredelen.'.$job->getType()->value),
        };
    }

    private function sborka(string $unitId, RunnerJob $runnerJob): void
    {
        if ($runnerJob->isFinish()) return;
        if ($runnerJob->isNew()) {
            $this->sobratUnitUseCase->handleSystem($unitId, $runnerJob->getJobId());
        } else if ($runnerJob->isStart()) {
            $this->resultatSborkiUnitaUseCase->handle($unitId, $runnerJob->getJobId());
        }
    }

    private function obnovlenie(string $unitId, RunnerJob $runnerJob): void
    {
        if ($runnerJob->isFinish()) return;
        if ($runnerJob->isNew()) {
            $this->obnovitKodUnitaUseCase->handleSystem($unitId, $runnerJob->getJobId());
        } else if ($runnerJob->isStart()) {
            $this->resultatObnovleniyaUnitaUseCase->handle($unitId, $runnerJob->getJobId());
        }
    }

    private function podgotovka(string $unitId, RunnerJob $runnerJob): void
    {
        if ($runnerJob->isFinish()) return;
        if ($runnerJob->isNew()) {
            $this->ustanovitDefoltniiKonfigUseCase->handleSystem($unitId);
            $this->podgotovitUnitKZapuskuUseCase->handle($unitId, $runnerJob->getJobId());
        } else if ($runnerJob->isStart()) {
            $this->ustanovitResultatPodgotovkiUnitaUseCase->handle($unitId, $runnerJob->getJobId());
        }
    }

    private function zapusk(string $unitId, RunnerJob $runnerJob): void
    {
        if ($runnerJob->isFinish()) return;
        if ($runnerJob->isNew()) {
            $this->zapustitUnitUseCase->handle($unitId, $runnerJob->getJobId());
        } else if ($runnerJob->isStart()) {
            $this->ustanovitResultatZapuskaUseCase->handle($unitId, $runnerJob->getJobId());
        }
    }

    private function ostanovka(string $unitId, RunnerJob $runnerJob): void
    {
        if ($runnerJob->isFinish()) return;
        if ($runnerJob->isNew()) {
            $this->ostanovitUnitUseCase->handle($unitId, $runnerJob->getJobId());
        } else if ($runnerJob->isStart()) {
            $this->ustanovitResultatOstanovkiUnitaUseCase->handle($unitId, $runnerJob->getJobId());
        }
    }

    private function sbrosPodgotovki(string $unitId, RunnerJob $runnerJob): void
    {
        if ($runnerJob->isFinish()) return;
        if ($runnerJob->isNew()) {
            $this->sbrositPodgotovkuUnitaUseCase->handle($unitId, $runnerJob->getJobId());
        } else if ($runnerJob->isStart()) {
            $this->ustanovitResultatSbrosaPodgotovkiUseCase->handle($unitId, $runnerJob->getJobId());
        }
    }

    private function udalenie(string $unitId, RunnerJob $runnerJob): void
    {
        if ($runnerJob->isFinish()) return;
        if ($runnerJob->isNew()) {
            $this->udalitUnitUseCase->handle($unitId, $runnerJob->getJobId());
        } else if ($runnerJob->isStart()) {
            $this->ustanovitResultatUdaleniyaUseCase->handle($unitId, $runnerJob->getJobId());
        }
    }

    private function deistvie(string $unitId, RunnerJob $runnerJob): void
    {
        if ($runnerJob->isFinish()) return;
        if ($runnerJob->isNew()) {
            $this->vipolnitDeistviyeUseCase->handle($unitId, $runnerJob->getJobId());
        } else if ($runnerJob->isStart()) {
            $this->ustanovitResultatDeistviyaUseCase->handle($unitId, $runnerJob->getJobId());
        }
    }


}
