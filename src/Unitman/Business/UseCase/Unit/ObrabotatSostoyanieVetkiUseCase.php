<?php

declare(strict_types=1);

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Model\Project;
use App\Unitman\Business\Model\SobitieIzHranilisha\DannieSobitiya;
use App\Unitman\Business\Model\SobitieIzHranilisha\TipSobitiya;
use App\Unitman\Business\Model\Unit;
use App\Unitman\Business\Model\Unit\OtlozhennoeDeistvie;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Unit\OtlozhennieDeistviyaRepository;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Utils\ProverkaSvobodyUnita;
use Psr\Log\LoggerInterface;

/**
 * Единый сценарий реакции на событие ветки: обновление, удаление, слияние.
 *
 * Используется и Git-вебхуком, и polling. Это важно: правила приоритета
 * намерений, удержания при выключенном автообновлении и проверки занятости
 * должны совпадать независимо от источника события.
 */
final class ObrabotatSostoyanieVetkiUseCase
{
    const OK = 'ok';
    const DEFERRED = 'deferred';
    const HELD = 'held';
    const NOT_NEEDED = 'ne_nugno_obnovlyat';
    const PENDING_DELETE = 'est_namerenie_udaleniya';
    const INVALID_STATUS = 'invalid status';

    public function __construct(
        private UnitRepository $unitRepository,
        private ProjectRepository $projectRepository,
        private DobavitProzesObnovleniyaUseCase $dobavitProzesObnovleniya,
        private DobavitProzesUdaleniyaUseCase $dobavitProzesUdaleniya,
        private OtlozhitDeistvieNadUnitomUseCase $otlozhitDeistvie,
        private OtlozhennieDeistviyaRepository $otlozhennieRepository,
        private ProverkaSvobodyUnita $proverkaSvobody,
        private LoggerInterface $logger,
    ) {
    }

    public function handle(Unit $unit, TipSobitiya $tipSobitiya, DannieSobitiya $dannieSobitiya): string
    {
        $project = $this->projectRepository->getById($unit->getProjectId());

        return match ($tipSobitiya) {
            TipSobitiya::KOD_OBNOVLEN => $this->obrabotatObnovlenie($unit, $project, $dannieSobitiya),
            TipSobitiya::VETKA_UDALENA, TipSobitiya::VETKA_SMERZHENA
                => $this->obrabotatUdalenie($unit, $project, $tipSobitiya, $dannieSobitiya),
            TipSobitiya::VETKA_SOZDANA => throw new \DomainException('unit.otlozhennoe_deistvie.tip_ne_podderzhan'),
        };
    }

    private function obrabotatObnovlenie(Unit $unit, Project $project, DannieSobitiya $dannieSobitiya): string
    {
        $unitId = $unit->getId();

        /*
         * Метка времени обновляется всегда: это событие event store, раннер
         * в нём не участвует. Даже если обновление сейчас только откладывается,
         * следующий polling не должен повторно обнаруживать тот же коммит.
         */
        $unit->izmenitVremyaPoslednegoIzmeneniyaKodaVHranilishe($dannieSobitiya->unixtime);
        $this->unitRepository->save($unit);

        if ($this->otlozhennieRepository->estNamerenieUdaleniya($unitId)) {
            return self::PENDING_DELETE;
        }

        if (!$unit->esliNugnoObnovitKodUnita()) {
            return self::NOT_NEEDED;
        }

        if (!$project->poluchitNastroikiHuka()->avtoobnovlenie) {
            $this->otlozhitDeistvie->handle(
                $unit,
                TipSobitiya::KOD_OBNOVLEN,
                $dannieSobitiya,
                OtlozhennoeDeistvie::STATUS_HELD_AUTO_OBNOVLENIE,
            );

            return self::HELD;
        }

        if (!$this->proverkaSvobody->svoboden($unit)) {
            $this->otlozhitDeistvie->handle($unit, TipSobitiya::KOD_OBNOVLEN, $dannieSobitiya);

            return self::DEFERRED;
        }

        /*
         * Несобранный юнит не откладываем: такое состояние не транзиентное,
         * и намерение висело бы вечно. Сохраняем прежнюю политику вебхука.
         */
        if (!$unit->esliSobran()) {
            return self::INVALID_STATUS;
        }

        try {
            $this->dobavitProzesObnovleniya->handleSystem($unitId);
        } catch (\DomainException $e) {
            if ($this->etoVremennayaZanyatost($e->getMessage())) {
                $this->otlozhitDeistvie->handle($unit, TipSobitiya::KOD_OBNOVLEN, $dannieSobitiya);

                return self::DEFERRED;
            }

            $this->logger->warning('unit.obrabotka_vetki.obnovlenie_otkloneno', [
                'unitId' => $unitId,
                'error' => $e->getMessage(),
            ]);

            return $e->getMessage();
        }

        $this->otlozhennieRepository->udalitPoUnituITipu($unitId, TipSobitiya::KOD_OBNOVLEN);

        return self::OK;
    }

    private function obrabotatUdalenie(
        Unit $unit,
        Project $project,
        TipSobitiya $tipSobitiya,
        DannieSobitiya $dannieSobitiya,
    ): string {
        if (!$project->poluchitNastroikiHuka()->avtoudalenie) {
            return self::OK;
        }

        $unitId = $unit->getId();

        if (!$this->proverkaSvobody->svoboden($unit)) {
            $this->otlozhitDeistvie->handle($unit, $tipSobitiya, $dannieSobitiya);

            return self::DEFERRED;
        }

        try {
            $this->dobavitProzesUdaleniya->handleSystem($unitId);
        } catch (\DomainException $e) {
            if ($this->etoVremennayaZanyatost($e->getMessage())) {
                $this->otlozhitDeistvie->handle($unit, $tipSobitiya, $dannieSobitiya);

                return self::DEFERRED;
            }

            $this->logger->warning('unit.obrabotka_vetki.udalenie_otkloneno', [
                'unitId' => $unitId,
                'error' => $e->getMessage(),
            ]);

            return $e->getMessage();
        }

        $this->otlozhennieRepository->udalitPoUnituITipu($unitId, TipSobitiya::KOD_OBNOVLEN);

        return self::OK;
    }

    private function etoVremennayaZanyatost(string $oshibka): bool
    {
        return in_array($oshibka, [
            'unit.zanyat',
            'unit.zanyat_hodom_opencode',
            'unit.wait_another_proccess',
        ], true);
    }
}
