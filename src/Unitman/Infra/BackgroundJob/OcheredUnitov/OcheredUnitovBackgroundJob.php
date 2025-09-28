<?php

namespace App\Unitman\Infra\BackgroundJob\OcheredUnitov;

use App\BackgroundJob\Infra\Service\AbstractBackgroundJob;
use Psr\Log\LoggerInterface;

final class OcheredUnitovBackgroundJob extends AbstractBackgroundJob
{
    public function __construct(
        private OcheredUnitovActivity $ocheredUnitovActivity,
        private LoggerInterface $logger
    )
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
            } catch (\Throwable $e) {
                echo "AAAAAAAAA:".$e->getTraceAsString()."\n";
                //$this->logger->error($e->getMessage());
                //continue;
            }
        }

        return true;
    }

    function getProcessNum(): int
    {
        return 10;
    }
}
