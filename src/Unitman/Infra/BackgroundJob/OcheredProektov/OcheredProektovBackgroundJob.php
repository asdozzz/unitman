<?php

namespace App\Unitman\Infra\BackgroundJob\OcheredProektov;

use App\BackgroundJob\Infra\Service\AbstractBackgroundJob;

final class OcheredProektovBackgroundJob extends AbstractBackgroundJob
{
    public function __construct(private OcheredProektovActivity $ocheredProektovActivity)
    {
    }

    function getName(): string
    {
        return 'ochered_proektov';
    }

    function run(): bool
    {
        $zadachi = $this->ocheredProektovActivity->poluchitZadachiNaObrabotku(100);

        foreach ($zadachi as $zadacha) {
            try {
                $this->ocheredProektovActivity->obrabotatZadachu($zadacha);
            } catch (\Exception $e) {
                echo var_export("WWWWWWWWWWWWWWWW:". $e->getMessage(), true);
                continue;
            }

        }

        return true;
    }


}
