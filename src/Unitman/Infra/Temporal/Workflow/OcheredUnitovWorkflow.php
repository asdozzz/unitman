<?php

namespace App\Unitman\Infra\Temporal\Workflow;

use App\BackgroundJob\Infra\Service\AbstractBackgroundJob;
use App\Unitman\Infra\Temporal\Activity\OcheredUnitovActivity;

final class OcheredUnitovWorkflow extends AbstractBackgroundJob
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
        $zadachi = $this->ocheredUnitovActivity->poluchitZadachiNaObrabotku(20);

        foreach ($zadachi as $zadacha) {
            $this->ocheredUnitovActivity->obrabotatZadachu($zadacha);
        }

        return true;
    }


}
