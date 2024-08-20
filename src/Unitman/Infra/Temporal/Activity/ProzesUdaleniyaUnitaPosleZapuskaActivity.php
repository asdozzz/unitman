<?php

namespace App\Unitman\Infra\Temporal\Activity;

use App\Unitman\Business\Command\Unit\ObnovitKodUnita;
use App\Unitman\Business\Command\Unit\OstanovitUnit;
use App\Unitman\Business\Command\Unit\PodgotovitUnitKZapusku;
use App\Unitman\Business\Command\Unit\SbrositPodgotovkuUnita;
use App\Unitman\Business\Command\Unit\UdalitUnit;
use App\Unitman\Business\Command\Unit\UstanovitOshibkuObnovleniyaUnitaPosleZapuska;
use App\Unitman\Business\Command\Unit\UstanovitOshibkuUdaleniyaUnitaPosleZapuska;
use App\Unitman\Business\Command\Unit\ZapustitUnit;
use App\Unitman\Business\UseCase\Unit\ObnovitKodUnitaUseCase;
use App\Unitman\Business\UseCase\Unit\OstanovitUnitUseCase;
use App\Unitman\Business\UseCase\Unit\PodgotovitUnitKZapuskuUseCase;
use App\Unitman\Business\UseCase\Unit\SbrositPodgotovkuUnitaUseCase;
use App\Unitman\Business\UseCase\Unit\UdalitUnitUseCase;
use App\Unitman\Business\UseCase\Unit\UstanovitOshibkuObnovleniyaUnitaPosleZapuskaUseCase;
use App\Unitman\Business\UseCase\Unit\UstanovitOshibkuUdaleniyaUnitaPosleZapuskaUseCase;
use App\Unitman\Business\UseCase\Unit\ZapustitUnitUseCase;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod as Am;

#[ActivityInterface(prefix:"")]
final class ProzesUdaleniyaUnitaPosleZapuskaActivity
{
    public function __construct(
        private OstanovitUnitUseCase $ostanovitUnitUseCase,
        private SbrositPodgotovkuUnitaUseCase $sbrositPodgotovkuUnitaUseCase,
        private UdalitUnitUseCase $udalitUnitUseCase,
        private UstanovitOshibkuUdaleniyaUnitaPosleZapuskaUseCase $ustanovitOshibkuUdaleniyaUnitaPosleZapuskaUseCase
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
    #[Am(name: "udalit")]
    public function udalit(string $unitId): bool {
        $this->udalitUnitUseCase->handleTemporal(new UdalitUnit($unitId));

        return true;
    }

    #[Am(name: "oshibkaProzesaUdaleniya")]
    public function oshibkaProzesaUdaleniya(string $unitId, string $error): bool {
        $this->ustanovitOshibkuUdaleniyaUnitaPosleZapuskaUseCase->handle(new UstanovitOshibkuUdaleniyaUnitaPosleZapuska($unitId, $error));

        return true;
    }
}
