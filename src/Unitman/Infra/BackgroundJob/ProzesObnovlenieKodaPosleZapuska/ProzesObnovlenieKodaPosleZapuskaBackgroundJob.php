<?php

namespace App\Unitman\Infra\BackgroundJob\ProzesObnovlenieKodaPosleZapuska;

use App\BackgroundJob\Infra\Service\AbstractBackgroundJob;
use Psr\Log\LoggerInterface;

final class ProzesObnovlenieKodaPosleZapuskaBackgroundJob extends AbstractBackgroundJob
{
    public function __construct(private ProzesObnovlenieKodaPosleZapuskaHandler $activity, private LoggerInterface $logger)
    {
    }

    /**
     * @inheritDoc
     */
    function getName(): string
    {
        return 'prozess_obnovlenie_koda_posle_zapuska';
    }

    function run(): bool
    {
        $zadachi = $this->activity->poluchitZadachiNaObrabotku(100);

        foreach ($zadachi as $zadacha) {
            $this->logger->debug(var_export($zadacha, true));
            try {
                $this->activity->obrabotatZadachu($zadacha);
            } catch (\Exception $e) {
                $this->logger->error($e->getMessage());
                continue;
            }

        }

        return true;
    }
}
