<?php

namespace App\Unitman\Infra\BackgroundJob\ProzesUdaleniyaUnitaPosleZapuska;

use App\BackgroundJob\Infra\Service\AbstractBackgroundJob;
use App\Unitman\Infra\BackgroundJob\ProzesObnovlenieKodaPosleZapuska\ProzesObnovlenieKodaPosleZapuskaHandler;
use Psr\Log\LoggerInterface;

final class ProzesUdaleniyaUnitaPosleZapuskaBackgroundJob extends AbstractBackgroundJob
{
    public function __construct(private ProzesUdaleniyaUnitaPosleZapuskaHandler $activity, private LoggerInterface $logger)
    {
    }

    /**
     * @inheritDoc
     */
    function getName(): string
    {
        return 'prozess_udaleniya_unita_posle_zapuska';
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
