<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Model\Unit;
use App\Unitman\Business\Model\Unit\Runner\RunnerJob;
use App\Unitman\Business\Model\Unit\Runner\RunnerJobStep;
use App\Unitman\Business\Model\Unit\Runner\RunnerJobType;
use App\Unitman\Business\Port\CanGeneateGuid;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;
use Psr\Clock\ClockInterface;

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
        private ClockInterface $clock
    )
    {
    }
    function handle(string $unitId): void
    {
        $unit = $this->unitRepository->getById($unitId);
        $job = $unit->poluchitJobuDlyObravotki();

        if (empty($job)) {
            //$this->proveritKonteinerUnitaUseCase->handle($unitId);
            return;
        }

        match ($job->getType()) {
            RunnerJobType::SBORKA => $this->sborka($unit, $job),
            RunnerJobType::OBNOVLENIE => $this->obnovlenie($unit, $job),
            RunnerJobType::PODGOTOVKA => $this->podgotovka($unit, $job),
            RunnerJobType::ZAPUSK => $this->zapusk($unit, $job),
            RunnerJobType::OSTANOVKA => $this->ostanovka($unit, $job),
            RunnerJobType::SBROS_PODGOTOVKI => $this->sbrosPodgotovki($unit, $job),
            RunnerJobType::UDALENIE => $this->udalenie($unit, $job),
            RunnerJobType::DEISTVIE => $this->deistvie($unit, $job),
            default => throw new \DomainException('unit.obrabotka_prozesa.tip_ne_opredelen.'.$job->getType()->value),
        };
    }

    private function sborka(Unit $unit, RunnerJob $runnerJob): void
    {
        if ($runnerJob->isFinish()) return;
        if ($runnerJob->isNew()) {
            try {
                $this->sobratUnitUseCase->handleSystem($unit->getId(), $runnerJob->getJobId());
            } catch (\Throwable $e) {
                $unit->otmenitZadachu($e, $runnerJob->getJobId(), $this->clock->now()->getTimestamp());
                $this->unitRepository->save($unit);
            }

        } else if ($runnerJob->isStart()) {
            try {
                $this->resultatSborkiUnitaUseCase->handle($unit->getId(), $runnerJob->getJobId());
            } catch (\Throwable $e) {
                $this->otmenaEsliPrevishenoVremyOjidaniyaResultata($unit, $runnerJob, $e, self::LIMIT_BEFORE_CANCEL);
            }
        }
    }

    private function obnovlenie(Unit $unit, RunnerJob $runnerJob): void
    {
        if ($runnerJob->isFinish()) return;
        if ($runnerJob->isNew()) {
            try {
                $this->obnovitKodUnitaUseCase->handleSystem($unit->getId(), $runnerJob->getJobId());
            } catch (\Throwable $e) {
                $unit->otmenitZadachu($e, $runnerJob->getJobId(), $this->clock->now()->getTimestamp());
                $this->unitRepository->save($unit);
            }

        } else if ($runnerJob->isStart()) {
            try {
                $this->resultatObnovleniyaUnitaUseCase->handle($unit->getId(), $runnerJob->getJobId());
            } catch (\Throwable $e) {
                $this->otmenaEsliPrevishenoVremyOjidaniyaResultata($unit, $runnerJob, $e, self::LIMIT_BEFORE_CANCEL);
            }
        }
    }

    private function podgotovka(Unit $unit, RunnerJob $runnerJob): void
    {
        if ($runnerJob->isFinish()) return;
        if ($runnerJob->isNew()) {
            try {
                $this->ustanovitDefoltniiKonfigUseCase->handleSystem($unit->getId());
                $this->podgotovitUnitKZapuskuUseCase->handle($unit->getId(), $runnerJob->getJobId());
            } catch (\Throwable $e) {
                $unit->otmenitZadachu($e, $runnerJob->getJobId(), $this->clock->now()->getTimestamp());
                $this->unitRepository->save($unit);
            }

        } else if ($runnerJob->isStart()) {
            try {
                $this->ustanovitResultatPodgotovkiUnitaUseCase->handle($unit->getId(), $runnerJob->getJobId());
            } catch (\Throwable $e) {
                $this->otmenaEsliPrevishenoVremyOjidaniyaResultata($unit, $runnerJob, $e, self::LIMIT_BEFORE_CANCEL);
            }
        }
    }

    const LIMIT_BEFORE_CANCEL = 30*60;
    private function zapusk(Unit $unit, RunnerJob $runnerJob): void
    {
        if ($runnerJob->isFinish()) return;
        $now = $this->clock->now()->getTimestamp();
        if ($runnerJob->isNew()) {
            try {
                $this->zapustitUnitUseCase->handle($unit->getId(), $runnerJob->getJobId());
            } catch (\Throwable $e) {
                $unit->otmenitZadachu($e, $runnerJob->getJobId(), $now);
                $this->unitRepository->save($unit);
            }

        } else if ($runnerJob->isStart()) {
            try {
                $this->ustanovitResultatZapuskaUseCase->handle($unit->getId(), $runnerJob->getJobId());
            } catch (\Throwable $e) {
                $this->otmenaEsliPrevishenoVremyOjidaniyaResultata($unit, $runnerJob, $e, self::LIMIT_BEFORE_CANCEL);
            }
        }
    }

    private function ostanovka(Unit $unit, RunnerJob $runnerJob): void
    {
        if ($runnerJob->isFinish()) return;
        $now = $this->clock->now()->getTimestamp();
        if ($runnerJob->isNew()) {
            try {
                $this->ostanovitUnitUseCase->handle($unit->getId(), $runnerJob->getJobId());
            } catch (\Throwable $e) {
                $unit->otmenitZadachu($e, $runnerJob->getJobId(), $this->clock->now()->getTimestamp());
                $this->unitRepository->save($unit);
            }

        } else if ($runnerJob->isStart()) {
            try {
                $this->ustanovitResultatOstanovkiUnitaUseCase->handle($unit->getId(), $runnerJob->getJobId());
            } catch (\Throwable $e) {
                $this->otmenaEsliPrevishenoVremyOjidaniyaResultata($unit, $runnerJob, $e, self::LIMIT_BEFORE_CANCEL);
            }
        }
    }

    private function sbrosPodgotovki(Unit $unit, RunnerJob $runnerJob): void
    {
        if ($runnerJob->isFinish()) return;
        if ($runnerJob->isNew()) {
            try {
                $this->sbrositPodgotovkuUnitaUseCase->handle($unit->getId(), $runnerJob->getJobId());
            } catch (\Throwable $e) {
                $unit->otmenitZadachu($e, $runnerJob->getJobId(), $this->clock->now()->getTimestamp());
                $this->unitRepository->save($unit);
            }

        } else if ($runnerJob->isStart()) {
            try {
                $this->ustanovitResultatSbrosaPodgotovkiUseCase->handle($unit->getId(), $runnerJob->getJobId());
            } catch (\Throwable $e) {
                $this->otmenaEsliPrevishenoVremyOjidaniyaResultata($unit, $runnerJob, $e, self::LIMIT_BEFORE_CANCEL);
            }
        }
    }

    private function udalenie(Unit $unit, RunnerJob $runnerJob): void
    {
        if ($runnerJob->isFinish()) return;
        if ($runnerJob->isNew()) {
            try {
                $this->udalitUnitUseCase->handle($unit->getId(), $runnerJob->getJobId());
            } catch (\Throwable $e) {
                $unit->otmenitZadachu($e, $runnerJob->getJobId(), $this->clock->now()->getTimestamp());
                $this->unitRepository->save($unit);
            }
        } else if ($runnerJob->isStart()) {
            try {
                $this->ustanovitResultatUdaleniyaUseCase->handle($unit->getId(), $runnerJob->getJobId());
            } catch (\Throwable $e) {
                $this->otmenaEsliPrevishenoVremyOjidaniyaResultata($unit, $runnerJob, $e, self::LIMIT_BEFORE_CANCEL);
            }
        }
    }

    private function deistvie(Unit $unit, RunnerJob $runnerJob): void
    {
        if ($runnerJob->isFinish()) return;
        if ($runnerJob->isNew()) {
            try {
                $this->vipolnitDeistviyeUseCase->handle($unit->getId(), $runnerJob->getJobId());
            } catch (\Throwable $e) {
                $unit->otmenitZadachu($e, $runnerJob->getJobId(), $this->clock->now()->getTimestamp());
                $this->unitRepository->save($unit);
            }

        } else if ($runnerJob->isStart()) {
            try {
                $this->ustanovitResultatDeistviyaUseCase->handle($unit->getId(), $runnerJob->getJobId());
            } catch (\Throwable $e) {
                $this->otmenaEsliPrevishenoVremyOjidaniyaResultata($unit, $runnerJob, $e, self::LIMIT_BEFORE_CANCEL*2);
            }
        }
    }

    /**
     * @param Unit $unit
     * @param RunnerJob $runnerJob
     * @param \Throwable|\Exception $e
     * @return void
     * @throws \Throwable
     */
    private function otmenaEsliPrevishenoVremyOjidaniyaResultata(Unit $unit, RunnerJob $runnerJob, \Throwable|\Exception $e, int $limit): void
    {
        $now = $this->clock->now()->getTimestamp();
        $prozes = $unit->poluchitProzesPoIdZadachi($runnerJob->getJobId());
        if (!empty($prozes->getLastUnixtime()) && ($now - $prozes->getLastUnixtime() > $limit)) {
            $unit->otmenitZadachu(new \DomainException('Превышено время обработки задания'), $runnerJob->getJobId(), $now);
            $this->unitRepository->save($unit);
        } else {
            throw $e;
        }
    }
}
