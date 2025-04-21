<?php

namespace App\Unitman\Infra\Temporal\Activity;

use App\Unitman\Business\Command\Unit\ObnovitKodUnita;
use App\Unitman\Business\Command\Unit\OstanovitUnit;
use App\Unitman\Business\Command\Unit\PodgotovitUnitKZapusku;
use App\Unitman\Business\Command\Unit\SbrositPodgotovkuUnita;
use App\Unitman\Business\Command\Unit\UstanovitOshibkuObnovleniyaUnitaPosleZapuska;
use App\Unitman\Business\Command\Unit\ZapustitUnit;
use App\Unitman\Business\UseCase\Unit\ObnovitKodUnitaUseCase;
use App\Unitman\Business\UseCase\Unit\OstanovitUnitUseCase;
use App\Unitman\Business\UseCase\Unit\PodgotovitUnitKZapuskuUseCase;
use App\Unitman\Business\UseCase\Unit\SbrositPodgotovkuUnitaUseCase;
use App\Unitman\Business\UseCase\Unit\UstanovitOshibkuObnovleniyaUnitaPosleZapuskaUseCase;
use App\Unitman\Business\UseCase\Unit\ZapustitUnitUseCase;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod as Am;

#[ActivityInterface(prefix:"ProzesObnovlenieKodaPosleZapuskaBezSbrosaPodgotovkiActivity.")]
final class ProzesObnovlenieKodaPosleZapuskaBezSbrosaPodgotovkiActivity
{
    public function __construct(
        private OstanovitUnitUseCase $ostanovitUnitUseCase,
        private ObnovitKodUnitaUseCase $obnovitKodUnitaUseCase,
        private ZapustitUnitUseCase $zapustitUnitUseCase,
        private UstanovitOshibkuObnovleniyaUnitaPosleZapuskaUseCase $ustanovitOshibkuObnovleniyaUnitaPosleZapuskaUseCase
    )
    {
    }

    #[Am(name: "ostanovit")]
    public function ostanovit(string $unitId): bool {
        $this->ostanovitUnitUseCase->handleTemporal(new OstanovitUnit($unitId));

        return true;
    }
    #[Am(name: "obnovitKod")]
    public function obnovitKod(string $unitId): bool {
        $this->obnovitKodUnitaUseCase->handleSystem(new ObnovitKodUnita($unitId));

        return true;
    }
    #[Am(name: "zapustit")]
    public function zapustit(string $unitId): bool {
        $this->zapustitUnitUseCase->handleTemporal(new ZapustitUnit($unitId));

        return true;
    }

    #[Am(name: "oshibkaProzesaObnovleniya")]
    public function oshibkaProzesaObnovleniya(string $unitId, string $error): bool {
        $this->ustanovitOshibkuObnovleniyaUnitaPosleZapuskaUseCase->handle(new UstanovitOshibkuObnovleniyaUnitaPosleZapuska($unitId, $error));

        return true;
    }
}
