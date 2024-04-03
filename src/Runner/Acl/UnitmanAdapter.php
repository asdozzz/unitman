<?php

namespace App\Runner\Acl;

use App\Runner\Business\Model\ResultatSbrosaPodgotovkiUnita;
use App\Unitman\Api\UnitmanApi;
use App\Unitman\Business\Command\Unit\UstanovitResultatSbrosaPodgotovki;
use App\Unitman\Business\Command\Unit\UstanovitResultatSbrosaPodgotovkiOtRunnera;

final class UnitmanAdapter
{
    public function __construct(private UnitmanApi $unitmanApi)
    {
    }

    function ustanovitResultatSbrosaPodgotovki(ResultatSbrosaPodgotovkiUnita $resultatSbrosaPodgotovkiUnita): void
    {
        $command = new UstanovitResultatSbrosaPodgotovkiOtRunnera($resultatSbrosaPodgotovkiUnita->UnitId, $resultatSbrosaPodgotovkiUnita->Success, $resultatSbrosaPodgotovkiUnita->Steps);
        $this->unitmanApi->ustanovitResultatSbrosaPodgotovki($command);
    }
}
