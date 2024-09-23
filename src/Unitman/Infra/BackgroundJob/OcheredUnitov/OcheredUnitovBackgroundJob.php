<?php

namespace App\Unitman\Infra\BackgroundJob\OcheredUnitov;

use App\BackgroundJob\Infra\Service\AbstractBackgroundJob;
use App\Unitman\Infra\BackgroundJob\OcheredUnitov\OcheredUnitovActivity;

final class OcheredUnitovBackgroundJob extends AbstractBackgroundJob
{
    public function __construct(private OcheredUnitovActivity $ocheredUnitovActivity)
    {
    }

    function getName(): string
    {
        return 'ochered_unitov';
    }

    function run(): bool
    {
        $zadachi = $this->ocheredUnitovActivity->poluchitZadachiNaObrabotku(100);

        foreach ($zadachi as $zadacha) {
            try {
                $this->ocheredUnitovActivity->obrabotatZadachu($zadacha);
            } catch (\Exception) {
                continue;
            }

        }

        return true;
    }


}
