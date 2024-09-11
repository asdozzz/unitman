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

#[ActivityInterface(prefix:"")]
final class ProzesObnovlenieKodaPosleZapuskaActivity
{
    public function __construct(
        private OstanovitUnitUseCase $ostanovitUnitUseCase,
        private SbrositPodgotovkuUnitaUseCase $sbrositPodgotovkuUnitaUseCase,
        private ObnovitKodUnitaUseCase $obnovitKodUnitaUseCase,
        private PodgotovitUnitKZapuskuUseCase $podgotovitUnitKZapuskuUseCase,
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
    #[Am(name: "sbrositPodgotovku")]
    public function sbrositPodgotovku(string $unitId): bool {
        $this->sbrositPodgotovkuUnitaUseCase->handleTemporal(new SbrositPodgotovkuUnita($unitId));

        return true;
    }
    #[Am(name: "obnovitKod")]
    public function obnovitKod(string $unitId): bool {
        $this->obnovitKodUnitaUseCase->handleSystem(new ObnovitKodUnita($unitId));

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

    #[Am(name: "oshibkaProzesaObnovleniya")]
    public function oshibkaProzesaObnovleniya(string $unitId, string $error): bool {
        $this->ustanovitOshibkuObnovleniyaUnitaPosleZapuskaUseCase->handle(new UstanovitOshibkuObnovleniyaUnitaPosleZapuska($unitId, $error));

        return true;
    }
}
