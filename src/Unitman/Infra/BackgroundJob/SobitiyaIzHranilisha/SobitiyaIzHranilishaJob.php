<?php

namespace App\Unitman\Infra\BackgroundJob\SobitiyaIzHranilisha;

use App\BackgroundJob\Infra\Service\AbstractBackgroundJob;
use Psr\Log\LoggerInterface;

final class SobitiyaIzHranilishaJob extends AbstractBackgroundJob
{
    public function __construct(private SobitiyaIzHranilishaActivity $sobitiyaIzHranilishaActivity)
    {
    }

    function getName(): string
    {
        return 'sobitiya_iz_hranilisha';
    }

    function run(): bool
    {
        $zadachi = $this->sobitiyaIzHranilishaActivity->poluchitZadachiNaObrabotku(100);

        foreach ($zadachi as $zadacha) {
            try {
                $result = $this->sobitiyaIzHranilishaActivity->obrabotatZadachu($zadacha);
                if ($result != 'ok') {
                    $this->sobitiyaIzHranilishaActivity->setSuccess($zadacha->id, $result);
                } else {
                    $this->sobitiyaIzHranilishaActivity->udalitZadachuIzOcheredi($zadacha->id);
                }
            } catch (\Exception $e) {
                $this->sobitiyaIzHranilishaActivity->setError($zadacha->id, $e->getMessage());
            }
        }

        return true;
    }
}
