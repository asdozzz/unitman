<?php

declare(strict_types=1);

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Model\SobitieIzHranilisha\DannieSobitiya;
use App\Unitman\Business\Model\SobitieIzHranilisha\TipSobitiya;
use App\Unitman\Business\Model\Unit;
use App\Unitman\Business\Port\Unit\OtlozhennieDeistviyaRepository;
use Psr\Log\LoggerInterface;

/**
 * Постановка события вебхука в очередь отложенных действий.
 *
 * Вызывается только тогда, когда юнит занят и применить событие прямо сейчас
 * нельзя. Джоб вебхука ретраится ограниченное число раз и теряет событие —
 * поэтому намерение сохраняется в БД и живёт до первого освобождения юнита.
 *
 * Приоритет `VETKA_UDALENA` над `KOD_OBNOVLEN` реализован здесь: удаление
 * ветки отменяет смысл её обновления, и держать оба намерения незачем.
 */
final class OtlozhitDeistvieNadUnitomUseCase
{
    public function __construct(
        private OtlozhennieDeistviyaRepository $otlozhennieRepository,
        private LoggerInterface $logger,
    ) {
    }

    public function handle(Unit $unit, TipSobitiya $tipSobitiya, DannieSobitiya $dannieSobitiya): void
    {
        $unitId = $unit->getId();

        if ($tipSobitiya === TipSobitiya::VETKA_UDALENA) {
            $this->otlozhennieRepository->udalitPoUnituITipu($unitId, TipSobitiya::KOD_OBNOVLEN);
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
        ]);
    }
}
