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
            $this->dobavitProzesUdaleniyaUseCase->handleSystem($id);
        }
    }
}
