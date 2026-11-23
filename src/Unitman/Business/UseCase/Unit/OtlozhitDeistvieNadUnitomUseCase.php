<?php

declare(strict_types=1);

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Model\SobitieIzHranilisha\DannieSobitiya;
use App\Unitman\Business\Model\SobitieIzHranilisha\TipSobitiya;
use App\Unitman\Business\Model\Unit;
use App\Unitman\Business\Model\Unit\OtlozhennoeDeistvie;
use App\Unitman\Business\Port\Unit\OtlozhennieDeistviyaRepository;
use Psr\Log\LoggerInterface;

/**
 * Постановка события вебхука в очередь отложенных действий.
 *
 * Вызывается только тогда, когда юнит занят и применить событие прямо сейчас
 * нельзя. Джоб вебхука ретраится ограниченное число раз и теряет событие —
 * поэтому намерение сохраняется в БД и живёт до первого освобождения юнита.
 *
 * Приоритет удаления над `KOD_OBNOVLEN` реализован здесь: и удаление ветки,
 * и её слияние отменяют смысл обновления. Намерения удаления и слияния
 * взаимоисключающие — оба ведут к удалению юнита, поэтому второе заменяет
 * первое и не создаёт второй процесс.
 */
final class OtlozhitDeistvieNadUnitomUseCase
{
    public function __construct(
        private OtlozhennieDeistviyaRepository $otlozhennieRepository,
        private LoggerInterface $logger,
    ) {
    }

    public function handle(
        Unit $unit,
        TipSobitiya $tipSobitiya,
        DannieSobitiya $dannieSobitiya,
        string $status = OtlozhennoeDeistvie::STATUS_READY,
    ): void {
        $unitId = $unit->getId();

        /*
         * Удаление и слияние уже отменяют смысл обновления. Если намерение
         * удаления появилось раньше, новое обновление не создаём — иначе
         * карточка показывала бы взаимоисключающие намерения.
         */
        if ($tipSobitiya === TipSobitiya::KOD_OBNOVLEN
            && $this->otlozhennieRepository->estNamerenieUdaleniya($unitId)
        ) {
            $this->logger->info('unit.otlozhennoe_deistvie.obnovlenie_otkloneno', [
                'unitId' => $unitId,
                'vetka' => $dannieSobitiya->vetka,
                'reason' => 'est_namerenie_udaleniya',
            ]);

            return;
        }

        if ($this->esliUdalenie($tipSobitiya)) {
            $this->otlozhennieRepository->udalitPoUnituITipu($unitId, TipSobitiya::KOD_OBNOVLEN);
            $this->otlozhennieRepository->udalitPoUnituITipu($unitId, $this->drugayaPrichinaUdaleniya($tipSobitiya));
        }

        $this->otlozhennieRepository->otlozhit(
            $unitId,
            $unit->getProjectId(),
            $tipSobitiya,
            [
                'vetka' => $dannieSobitiya->vetka,
                'unixtime' => $dannieSobitiya->unixtime,
                'tip_sobitiya' => $tipSobitiya->value,
            ],
            new \DateTimeImmutable(),
            $status,
        );

        /*
         * Это не ошибка для пользователя: событие принято и будет применено
         * автоматически. Логируем, чтобы при разборе инцидента было видно,
         * почему сборка или удаление начались позже вебхука.
         */
        $this->logger->info('unit.otlozhennoe_deistvie.postupilo', [
            'unitId' => $unitId,
            'vetka' => $dannieSobitiya->vetka,
            'tipSobitiya' => $tipSobitiya->value,
            'unixtime' => $dannieSobitiya->unixtime,
            'status' => $status,
        ]);
    }

    private function esliUdalenie(TipSobitiya $tipSobitiya): bool
    {
        return $tipSobitiya === TipSobitiya::VETKA_UDALENA
            || $tipSobitiya === TipSobitiya::VETKA_SMERZHENA;
    }

    private function drugayaPrichinaUdaleniya(TipSobitiya $tipSobitiya): TipSobitiya
    {
        return $tipSobitiya === TipSobitiya::VETKA_UDALENA
            ? TipSobitiya::VETKA_SMERZHENA
            : TipSobitiya::VETKA_UDALENA;
    }
}
