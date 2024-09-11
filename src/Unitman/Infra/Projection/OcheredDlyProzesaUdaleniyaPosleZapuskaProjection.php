<?php

namespace App\Unitman\Infra\Projection;

use App\Unitman\Business\Model\Unit\Event\IzmenenieVetkiNachalos;
use App\Unitman\Business\Model\Unit\Event\KodVetkiIzmenilsyaVHranilishe;
use App\Unitman\Business\Model\Unit\Event\KonfigUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\ObnovlenieKodaUnitaPosleZapuskaNachalos;
use App\Unitman\Business\Model\Unit\Event\ObnovlenieUnitaNachalos;
use App\Unitman\Business\Model\Unit\Event\OshibkaIzmeneniyaVetkiUnitaUstanovlena;
use App\Unitman\Business\Model\Unit\Event\OshibkaObnovleniyaUnitaPosleZapuskaUstanovlena;
use App\Unitman\Business\Model\Unit\Event\OshibkaObnovleniyaUnitaUstanovlena;
use App\Unitman\Business\Model\Unit\Event\OshibkaOstanovkiUnitaUstanovlena;
use App\Unitman\Business\Model\Unit\Event\OshibkaPodgotovkiUnitaUstanovlena;
use App\Unitman\Business\Model\Unit\Event\OshibkaSborkiUnitaUstanovlena;
use App\Unitman\Business\Model\Unit\Event\OshibkaSbrosaPodgotovkiUnitaUstanovlena;
use App\Unitman\Business\Model\Unit\Event\OshibkaUdaleniyaUnitaPosleZapuskaUstanovlena;
use App\Unitman\Business\Model\Unit\Event\OshibkaUdaleniyaUnitaUstanovlena;
use App\Unitman\Business\Model\Unit\Event\OshibkaZapuskaUnitaUstanovlena;
use App\Unitman\Business\Model\Unit\Event\OstanovkaUnitaNachalas;
use App\Unitman\Business\Model\Unit\Event\PeremenieUnitaZapolneni;
use App\Unitman\Business\Model\Unit\Event\PodgotovkaUnitaNachalas;
use App\Unitman\Business\Model\Unit\Event\SborkaUnitNachalas;
use App\Unitman\Business\Model\Unit\Event\SbrosPodgotovkiNachalsya;
use App\Unitman\Business\Model\Unit\Event\SlomaniyUnitUdalen;
use App\Unitman\Business\Model\Unit\Event\UdalenieUnitaNachalos;
use App\Unitman\Business\Model\Unit\Event\UdalenieUnitaPosleZapuskaNachalos;
use App\Unitman\Business\Model\Unit\Event\UnitSozdan;
use App\Unitman\Business\Model\Unit\Event\UspehIzmeneniyaVetkiUstanovlen;
use App\Unitman\Business\Model\Unit\Event\UspehObnovleniyaUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\UspehOstanovkiUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\UspehPodgotovkiUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\UspehSborkiUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\UspehSbrosaPodgotovkiUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\UspehUdaleniyaUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\UspehZapuskaUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\ZapuskUnitNachalsya;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\ReadModel\Unit\OcheredDlyProzesaUdaleniyaUnitaPosleZapuska;
use App\Unitman\Business\Utils\UnitmanClassNameMapEnum;
use App\Unitman\Infra\Repository\Unit\OcheredDlyProzesaUdaleniyaUnitaPosleZapuskaRepository;
use App\Utils\EventSauce\AbstractProjection;
use App\Utils\EventSauce\Model\StreamName;

final class OcheredDlyProzesaUdaleniyaPosleZapuskaProjection extends AbstractProjection implements UnitmanProjection
{
    public function __construct(private OcheredDlyProzesaUdaleniyaUnitaPosleZapuskaRepository $repository, private UnitRepository $unitRepository)
    {
    }

    function isSyncProjection(): bool
    {
        return true;
    }

    function getProjectionName(): string
    {
        return 'prozess_udaleniya_unita_posle_zapuska';
    }

    function init(): void
    {
        $this->repository->init();
    }

    function destroy(): void
    {
        $this->repository->destroy();
    }

    function reset(): void
    {
        $this->repository->truncate();
    }

    function getStreamName(): StreamName
    {
        return new StreamName(UnitmanClassNameMapEnum::Unit->value);
    }

    protected function getExceptionEvents(): array
    {
        return [
            UnitSozdan::class,
            SborkaUnitNachalas::class,
            OshibkaSborkiUnitaUstanovlena::class,
            UspehSborkiUnitaUstanovlen::class,
            IzmenenieVetkiNachalos::class,
            OshibkaIzmeneniyaVetkiUnitaUstanovlena::class,
            UspehIzmeneniyaVetkiUstanovlen::class,
            PeremenieUnitaZapolneni::class,
            PodgotovkaUnitaNachalas::class,
            ObnovlenieKodaUnitaPosleZapuskaNachalos::class,
            OshibkaObnovleniyaUnitaPosleZapuskaUstanovlena::class,
            ObnovlenieUnitaNachalos::class,
            OshibkaObnovleniyaUnitaUstanovlena::class,
            UspehObnovleniyaUnitaUstanovlen::class,
            KonfigUnitaUstanovlen::class,
            SbrosPodgotovkiNachalsya::class,
            OstanovkaUnitaNachalas::class,
            OshibkaPodgotovkiUnitaUstanovlena::class,
            UspehPodgotovkiUnitaUstanovlen::class,
            ZapuskUnitNachalsya::class,
            OshibkaZapuskaUnitaUstanovlena::class,
            UspehZapuskaUnitaUstanovlen::class,
            UdalenieUnitaPosleZapuskaNachalos::class,
            KodVetkiIzmenilsyaVHranilishe::class,
        ];
    }

    function handleOshibkaSbrosaPodgotovkiUnitaUstanovlena(OshibkaSbrosaPodgotovkiUnitaUstanovlena $fact): void
    {
        $this->repository->removeByUnitId($fact->unitId);
        $unit = $this->unitRepository->getById($fact->unitId);
        $workflowId = $unit->poluchitWorkflowIdDlyUdaleniyaUnitaPosleZapuska();
        if (!empty($workflowId)) {
            $this->repository->insert($fact->unitId, OcheredDlyProzesaUdaleniyaUnitaPosleZapuska::ERROR);
        }
    }

    function handleUspehSbrosaPodgotovkiUnitaUstanovlen(UspehSbrosaPodgotovkiUnitaUstanovlen $fact): void
    {
        $this->repository->removeByUnitId($fact->unitId);
        $unit = $this->unitRepository->getById($fact->unitId);
        $workflowId = $unit->poluchitWorkflowIdDlyUdaleniyaUnitaPosleZapuska();
        if (!empty($workflowId)) {
            $this->repository->insert($fact->unitId, OcheredDlyProzesaUdaleniyaUnitaPosleZapuska::SBROSHENA_PODGOTOVKA);
        }
    }

    function handleOshibkaOstanovkiUnitaUstanovlena(OshibkaOstanovkiUnitaUstanovlena $fact): void
    {
        $this->repository->removeByUnitId($fact->unitId);
        $unit = $this->unitRepository->getById($fact->unitId);
        $workflowId = $unit->poluchitWorkflowIdDlyUdaleniyaUnitaPosleZapuska();
        if (!empty($workflowId)) {
            $this->repository->insert($fact->unitId, OcheredDlyProzesaUdaleniyaUnitaPosleZapuska::ERROR);
        }
    }

    function handleUspehOstanovkiUnitaUstanovlen(UspehOstanovkiUnitaUstanovlen $fact): void
    {
        $this->repository->removeByUnitId($fact->unitId);
        $unit = $this->unitRepository->getById($fact->unitId);
        $workflowId = $unit->poluchitWorkflowIdDlyUdaleniyaUnitaPosleZapuska();
        if (!empty($workflowId)) {
            $this->repository->insert($fact->unitId, OcheredDlyProzesaUdaleniyaUnitaPosleZapuska::OSTANOVLEN);
        }
    }

    function handleUdalenieUnitaNachalos(UdalenieUnitaNachalos $fact): void
    {
        $this->repository->removeByUnitId($fact->unitId);
    }

    function handleOshibkaUdaleniyaUnitaUstanovlena(OshibkaUdaleniyaUnitaUstanovlena $fact): void
    {
        $this->repository->removeByUnitId($fact->unitId);
    }

    function handleUspehUdaleniyaUnitaUstanovlen(UspehUdaleniyaUnitaUstanovlen $fact): void
    {
        $this->repository->removeByUnitId($fact->unitId);
    }
    function handleSlomaniyUnitUdalen(SlomaniyUnitUdalen $fact): void
    {
        $this->repository->removeByUnitId($fact->unitId);
    }

    function handleOshibkaUdaleniyaUnitaPosleZapuskaUstanovlena(OshibkaUdaleniyaUnitaPosleZapuskaUstanovlena $fact): void
    {
        $this->repository->removeByUnitId($fact->unitId);
    }
}
