<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\UstanovitResultatSbrosaPodgotovkiOtRunnera;
use App\Unitman\Business\Port\Unit\UnitRepository;

final class UstanovitResultatSbrosaPodgotovkiOtRunneraUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
    )
    {
    }

    function handle(UstanovitResultatSbrosaPodgotovkiOtRunnera $command): void
    {
        $unit = $this->unitRepository->getById($command->id);
        if ($command->success) {
            $unit->ustanovitUspehSbrosaPodgotovki($command->steps);
        } else {
            $unit->ustanovitOshibkuSbrosaPodgotovki($command->steps);
        }
        $this->unitRepository->save($unit);
    }
}
