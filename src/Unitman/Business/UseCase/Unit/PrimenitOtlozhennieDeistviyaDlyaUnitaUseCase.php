<?php

declare(strict_types=1);

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Model\SobitieIzHranilisha\TipSobitiya;
use App\Unitman\Business\Model\Unit\OtlozhennoeDeistvie;
use App\Unitman\Business\Port\Unit\OtlozhennieDeistviyaRepository;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Utils\ProverkaSvobodyUnita;
use Psr\Log\LoggerInterface;

/**
 * Применение отложенных вебхук-намерений к юниту.
 *
 * Вызывается из трёх мест: сразу после освобождения юнита, после завершения
 * последней задачи процесса юнита и из фонового обхода. Все три входа должны
 * вести себя одинаково, поэтому вся логика сосредоточена здесь.
 *
 * Ошибки классифицируются, а не пробрасываются: обработчик вебхука уже отдал
 * событие, и падение здесь означало бы его потерю. Временная занятость —
 * повод повторить попытку позже, окончательная ошибка — повод перестать
 * пытаться и записать причину.
 */
final class PrimenitOtlozhennieDeistviyaDlyaUnitaUseCase
{
    /**
     * Бюджет ретраев: при задержке до 15 минут 40 попыток покрывают около
     * десяти часов реальной занятости юнита. Дольше держать намерение
     * бессмысленно — событие вебхука к тому моменту устарело.
     */
    const MAX_ATTEMPTS = 40;

    const BASE_DELAY_SECONDS = 30;

    const MAX_DELAY_SECONDS = 900;

    public function __construct(
        private OtlozhennieDeistviyaRepository $otlozhennieRepository,
        private ProverkaSvobodyUnita $proverkaSvobody,
        private UnitRepository $unitRepository,
        private DobavitProzesObnovleniyaUseCase $dobavitProzesObnovleniyaUseCase,
        private DobavitProzesUdaleniyaUseCase $dobavitProzesUdaleniyaUseCase,
        private LoggerInterface $logger,
    ) {
    }

    public function handle(string $unitId): void
    {
        $now = new \DateTimeImmutable();
        $deistviya = $this->otlozhennieRepository->poluchitGotovieDlyaUnita($unitId, $now);

        if ($deistviya === []) {
            return;
        }

        try {
            $unit = $this->unitRepository->getById($unitId);
        } catch (\Throwable $e) {
            /*
             * Юнита нет в event store — применять нечего и восстанавливать
             * нечего: намерения просто удаляются вместе с юнитом.
             */
            $this->logger->warning('unit.otlozhennoe_deistvie.unit_not_found', [
                'unitId' => $unitId,
                'error' => $e->getMessage(),
            ]);

            foreach ($deistviya as $deistvie) {
                $this->otlozhennieRepository->udalitPoUnituITipu($unitId, $deistvie->tipSobitiya);
            }

            return;
        }

        /*
         * Проверка свободы выполняется явно: `handleSystem` передаёт
         * `neproveryatProzesi: true`, и агрегат не остановит применение.
         */
        if (!$this->proverkaSvobody->svoboden($unit)) {
            foreach ($deistviya as $deistvie) {
                $this->otlozhitPovtorno($deistvie, $now, 'unit.zanyat_hodom_opencode');
            }

            return;
        }

        foreach ($deistviya as $deistvie) {
            $this->primenit($unitId, $deistvie, $now);
        }
    }

    private function primenit(string $unitId, OtlozhennoeDeistvie $deistvie, \DateTimeImmutable $now): void
    {
        try {
            $this->zapustitProzes($unitId, $deistvie->tipSobitiya);
        } catch (\DomainException $e) {
            $this->obrabotatDomennuyuOshibku($deistvie, $now, $e->getMessage());

            return;
        } catch (\Throwable $e) {
            $this->logger->error('unit.otlozhennoe_deistvie.primenenie_upalo', [
                'unitId' => $unitId,
                'tipSobitiya' => $deistvie->tipSobitiya->value,
                'error' => $e->getMessage(),
            ]);
            $this->otlozhitPovtorno($deistvie, $now, $e->getMessage());

            return;
        }

        $this->otlozhennieRepository->udovletvorit($unitId, $deistvie->tipSobitiya);

        $this->logger->info('unit.otlozhennoe_deistvie.primeneno', [
            'unitId' => $unitId,
            'tipSobitiya' => $deistvie->tipSobitiya->value,
            'attempts' => $deistvie->attempts,
        ]);
    }

    /**
     * @throws \DomainException
     */
    private function zapustitProzes(string $unitId, TipSobitiya $tipSobitiya): void
    {
        match ($tipSobitiya) {
            TipSobitiya::KOD_OBNOVLEN => $this->dobavitProzesObnovleniyaUseCase->handleSystem($unitId),
            TipSobitiya::VETKA_UDALENA => $this->dobavitProzesUdaleniyaUseCase->handleSystem($unitId),
            TipSobitiya::VETKA_SOZDANA => throw new \DomainException('unit.otlozhennoe_deistvie.tip_ne_podderzhan'),
        };
    }

    private function obrabotatDomennuyuOshibku(
        OtlozhennoeDeistvie $deistvie,
        \DateTimeImmutable $now,
        string $oshibka,
    ): void {
        if ($oshibka === 'unit.udalen') {
            /*
             * Юнит уже удалён — намерение неактуально, повторять бессмысленно.
             */
            $this->otlozhennieRepository->udalitPoUnituITipu($deistvie->unitId, $deistvie->tipSobitiya);
            $this->logger->info('unit.otlozhennoe_deistvie.otmeneno', [
                'unitId' => $deistvie->unitId,
                'tipSobitiya' => $deistvie->tipSobitiya->value,
                'reason' => $oshibka,
            ]);

            return;
        }

        /*
         * `unit.zanyat`, `unit.wait_another_proccess` и прочие отказы агрегата
         * временны: юнит ещё собирается, обновляется или занят чем-то своим.
         */
        $this->otlozhitPovtorno($deistvie, $now, $oshibka);
    }

    private function otlozhitPovtorno(OtlozhennoeDeistvie $deistvie, \DateTimeImmutable $now, string $oshibka): void
    {
        if ($deistvie->attempts + 1 >= self::MAX_ATTEMPTS) {
            $this->otlozhennieRepository->udalitPoUnituITipu($deistvie->unitId, $deistvie->tipSobitiya);
            $this->logger->error('unit.otlozhennoe_deistvie.ischerpan_budget', [
                'unitId' => $deistvie->unitId,
                'tipSobitiya' => $deistvie->tipSobitiya->value,
                'attempts' => $deistvie->attempts,
                'error' => $oshibka,
            ]);

            return;
        }

        $this->otlozhennieRepository->otmetitPopytku(
            $deistvie->unitId,
            $deistvie->tipSobitiya,
            $now->modify(sprintf('+%d seconds', $this->zaderzhkaSekund($deistvie->attempts))),
            $oshibka,
        );
    }

    private function zaderzhkaSekund(int $attempts): int
    {
        /** @var int $mnozhitel */
        $mnozhitel = 1 << min($attempts, 10);

        return min(self::BASE_DELAY_SECONDS * $mnozhitel, self::MAX_DELAY_SECONDS);
    }
}
