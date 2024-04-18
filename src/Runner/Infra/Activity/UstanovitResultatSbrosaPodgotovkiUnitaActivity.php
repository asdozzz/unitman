<?php

namespace App\Runner\Infra\Activity;

use App\Runner\Acl\UnitmanAdapter;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatSbrosaPodgotovkiUnita;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;

#[ActivityInterface(prefix:"")]
class UstanovitResultatSbrosaPodgotovkiUnitaActivity
{
    public function __construct(private UnitmanAdapter $unitmanAdapter)
    {
    }

    #[ActivityMethod(name: "UstanovitResultatSbrosaPodgotovkiUnitaActivity")]
    public function execute(ResultatSbrosaPodgotovkiUnita $resultatSbrosaPodgotovkiUnita): string
    {
        $this->unitmanAdapter->ustanovitResultatSbrosaPodgotovki($resultatSbrosaPodgotovkiUnita);

        return 'OK 123';
    }
}
