<?php

declare(strict_types=1);

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\ObnovitSostoyanieKonteyneraUnita;
use App\Unitman\Business\Port\Unit\UmeetObnovlyatSostoyanieKonteyneraUnita;
use App\Unitman\Business\Port\Unit\UmeetOpoveshatObIzmeneniiUnita;

final class ObnovitSostoyanieKonteyneraUnitaUseCase
{
    public function __construct(
        private UmeetObnovlyatSostoyanieKonteyneraUnita $umeetObnovlyatSostoyanieKonteynera,
        private UmeetOpoveshatObIzmeneniiUnita $umeetOpoveshatObIzmenenii,
    ) {
    }

    public function handle(ObnovitSostoyanieKonteyneraUnita $command): void
    {
        $obnovleno = $this->umeetObnovlyatSostoyanieKonteynera->obnovitSostoyanieKonteynera(
            $command->unitId,
            $command->mismatch,
            $command->expectedState,
            $command->actualState,
            $command->detectedAt,
        );

        if ($obnovleno) {
            $this->umeetOpoveshatObIzmenenii->opoveshatObObnovlenii($command->unitId);
        }
    }
}
