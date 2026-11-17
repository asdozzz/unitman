<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Port\Unit\UmeetOtbiratNeaktivnieUniti;
use Psr\Clock\ClockInterface;

final class UdalitNeaktivnieUnitiUseCase
{
    public function __construct(
        private UmeetOtbiratNeaktivnieUniti $umeetOtbiratNeaktivnieUniti,
        private DobavitProzesUdaleniyaUseCase $dobavitProzesUdaleniyaUseCase,
        private ClockInterface $clock
    )
    {
    }

    function handle(int $period): void
    {
        $currentDatetime = $this->clock->now();
        $ids = $this->umeetOtbiratNeaktivnieUniti->otobratNeaktivnieUniti($currentDatetime->getTimestamp() - $period);
        foreach ($ids as $id) {
            /*
             * Юнит может быть занят ходом OpenCode — тогда удаление запрещено
             * доменным гейтом. Это не повод бросать весь обход: остальные
             * неактивные юниты к работе не относятся, а занятый попадёт
             * в следующий прогон (он всё ещё неактивен).
             */
            try {
                $this->dobavitProzesUdaleniyaUseCase->handleSystem($id);
            } catch (\DomainException) {
                continue;
            }
        }
    }
}
