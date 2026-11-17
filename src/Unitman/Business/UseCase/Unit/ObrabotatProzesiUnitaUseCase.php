<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Model\Unit;
use App\Unitman\Business\Model\Unit\Runner\RunnerJob;
use App\Unitman\Business\Model\Unit\Runner\RunnerJobType;
use App\Unitman\Business\Port\Unit\UnitRepository;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

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
        private PrimenitOtlozhennieDeistviyaDlyaUnitaUseCase $primenitOtlozhennieDeistviya,
        private LoggerInterface $logger,
        private ClockInterface $clock
    )
    {
    }
    /**
     * @return bool false — у юнита не осталось незавершённых заданий, то есть
     *              обрабатывать нечего. Вызывающий обязан снять юнита с опроса:
     *              раньше метод молча возвращал управление, очередь не получала
     *              ни события, ни повода удалить строку, и «пустой» юнит
     *              опрашивался вечно (ровно так зависла строка ochered_unitov).
     */
    function handle(string $unitId): bool
    {
        $unit = $this->unitRepository->getById($unitId);
        $job = $unit->poluchitJobuDlyObravotki();

        if (empty($job)) {
            //$this->proveritKonteinerUnitaUseCase->handle($unitId);
            /*
             * Заданий не осталось — это и есть момент, когда юнит освободился
             * после собственной активити. Применяем отложенные вебхук-намерения
             * сразу, не дожидаясь фонового обхода: иначе обновление или удаление
             * ветки ждало бы до 10 секунд без причины.
             *
             * Ошибки не пробрасываем: вызывающая очередь должна получить свой
             * false и снять юнита с опроса. Если что-то пойдёт не так, намерение
             * останется в таблице и его подберёт фоновый обход.
             */
            $this->zapustitPrimenenieOtlozhennihDeistviy($unitId);

            return false;
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
            default => throw new \DomainException('unit.obrabotka_prozesa.tip_ne_opredelen: '.$job->getType()->value),
        };

        return true;
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

    private function zapustitPrimenenieOtlozhennihDeistviy(string $unitId): void
    {
        try {
            $this->primenitOtlozhennieDeistviya->handle($unitId);
        } catch (\Throwable $e) {
            $this->logger->warning('unit.otlozhennoe_deistvie.primenenie_pri_osvobozhdenii_upalo', [
                'unitId' => $unitId,
                'error' => $e->getMessage(),
            ]);
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
