<?php

namespace App\Unitman\Infra\Temporal\Activity;

use App\Unitman\Business\Command\Unit\PodgotovitUnitKZapusku;
use App\Unitman\Business\Command\Unit\SobratUnit;
use App\Unitman\Business\Command\Unit\UstanovitOshibkuAvtosborkiUnita;
use App\Unitman\Business\Command\Unit\UstanovitOshibkuObnovleniyaUnitaPosleZapuska;
use App\Unitman\Business\Command\Unit\ZapustitUnit;
use App\Unitman\Business\UseCase\Unit\PodgotovitUnitKZapuskuUseCase;
use App\Unitman\Business\UseCase\Unit\SobratUnitUseCase;
use App\Unitman\Business\UseCase\Unit\UstanovitDefoltniiKonfigUseCase;
use App\Unitman\Business\UseCase\Unit\UstanovitOshibkuAvtosborkiUnitaSystemoiUseCase;
use App\Unitman\Business\UseCase\Unit\ZapustitUnitUseCase;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod as Am;

#[ActivityInterface(prefix:"")]
final class ProzesAvtosborkiUnitaSystemoiActivity
{
    public function __construct(
        private SobratUnitUseCase $sobratUnitUseCase,
        private PodgotovitUnitKZapuskuUseCase $podgotovitUnitKZapuskuUseCase,
        private ZapustitUnitUseCase $zapustitUnitUseCase,
        private UstanovitDefoltniiKonfigUseCase $ustanovitDefoltniiKonfigUseCase,
        private UstanovitOshibkuAvtosborkiUnitaSystemoiUseCase $ustanovitOshibkuAvtosborkiUnitaSystemoiUseCase
    )
    {
    }

    #[Am(name: "sobrat")]
    public function sobrat(string $unitId): bool {
        $this->sobratUnitUseCase->handleSystem(new SobratUnit($unitId));

        return true;
    }

    #[Am(name: "ustanovitDefoltniiKonfig")]
    public function ustanovitDefoltniiKonfig(string $unitId): bool {
        $this->ustanovitDefoltniiKonfigUseCase->handleSystem($unitId);

        return true;
    }


    #[Am(name: "podgotovit")]
    public function podgotovit(string $unitId): bool {
        $this->podgotovitUnitKZapuskuUseCase->handleTemporal(new PodgotovitUnitKZapusku($unitId));

        return true;
    }
    #[Am(name: "zapustit")]
    public function zapustit(string $unitId): bool {
        $this->zapustitUnitUseCase->handleTemporal(new ZapustitUnit($unitId));

        return true;
    }

    #[Am(name: "oshibkaProzesaSozdaniya")]
    public function oshibkaProzesaSozdaniya(string $unitId, string $error): bool {
        $this->ustanovitOshibkuAvtosborkiUnitaSystemoiUseCase->handle(new UstanovitOshibkuAvtosborkiUnita($unitId, $error));

        return true;
    }
}
