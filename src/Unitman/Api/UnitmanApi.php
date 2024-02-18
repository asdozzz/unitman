<?php

namespace App\Unitman\Api;

use App\Unitman\Business\Command\Unit\UstanovitResultatSbrosaPodgotovkiOtRunnera;
use App\Unitman\Business\UseCase\Unit\UstanovitResultatSbrosaPodgotovkiOtRunneraUseCase;

final class UnitmanApi
{
    public function __construct(private UstanovitResultatSbrosaPodgotovkiOtRunneraUseCase $resultatSbrosaPodgotovkiOtRunnera)
    {
    }

    function ustanovitResultatSbrosaPodgotovki(UstanovitResultatSbrosaPodgotovkiOtRunnera $command): void
    {
        $this->resultatSbrosaPodgotovkiOtRunnera->handle($command);
    }
}
