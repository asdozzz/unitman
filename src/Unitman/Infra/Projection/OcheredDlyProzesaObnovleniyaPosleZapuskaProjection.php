<?php

namespace App\Unitman\Infra\Projection;

use App\Unitman\Business\Model\Unit\Event\AvtosborkaUnitaNachalas;
use App\Unitman\Business\Model\Unit\Event\IzmenenieVetkiNachalos;
use App\Unitman\Business\Model\Unit\Event\KodVetkiIzmenilsyaVHranilishe;
use App\Unitman\Business\Model\Unit\Event\KonfigUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\ObnovlenieKodaUnitaPosleZapuskaNachalos;
use App\Unitman\Business\Model\Unit\Event\ObnovlenieUnitaNachalos;
use App\Unitman\Business\Model\Unit\Event\OshibkaAvtosborkiUstanovlena;
use App\Unitman\Business\Model\Unit\Event\OshibkaDeistviyaUstanovlena;
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
use App\Unitman\Business\Model\Unit\Event\StatistikaPoKonteineruObnovlena;
use App\Unitman\Business\Model\Unit\Event\UdalenieUnitaNachalos;
use App\Unitman\Business\Model\Unit\Event\UdalenieUnitaPosleZapuskaNachalos;
use App\Unitman\Business\Model\Unit\Event\UnitSbroshenDoSostoyaniyaSborki;
use App\Unitman\Business\Model\Unit\Event\UnitSozdan;
use App\Unitman\Business\Model\Unit\Event\UnitSozdanSystemoi;
use App\Unitman\Business\Model\Unit\Event\UspehDeistviyaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\UspehIzmeneniyaVetkiUstanovlen;
use App\Unitman\Business\Model\Unit\Event\UspehObnovleniyaUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\UspehOstanovkiUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\UspehPodgotovkiUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\UspehSborkiUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\UspehSbrosaPodgotovkiUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\UspehUdaleniyaUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\UspehZapuskaUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\VipolnenieDeistviyaNachalos;
use App\Unitman\Business\Model\Unit\Event\ZapuskUnitNachalsya;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\ReadModel\Unit\OcheredDlyProzesaObnovleniyaKodaPosleZapuska;
use App\Unitman\Business\Utils\UnitmanClassNameMapEnum;
use App\Unitman\Infra\Jobs\OcheredDlyProzesaObnovleniyaPosleZapuskaJobHandler;
use App\Unitman\Infra\Jobs\ZadachaDlyOcherediService;
use App\Unitman\Infra\Repository\ZadachaDlyOcherediRepository;
use App\Utils\EventSauce\AbstractProjection;
use App\Utils\EventSauce\Model\StreamName;

final class OcheredDlyProzesaObnovleniyaPosleZapuskaProjection extends AbstractProjection implements UnitmanProjection
{
    public function __construct(private ZadachaDlyOcherediService $zadachaDlyOcherediService, private ZadachaDlyOcherediRepository $repository, private UnitRepository $unitRepository)
    {
    }

    function isAllowedRebuild(): bool
    {
        return false;
    }

    function isSyncProjection(): bool
    {
        return true;
    }

    function getProjectionName(): string
    {
        return 'prozess_obnovlenie_koda_posle_zapuska';
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

    /**
     * @param OcheredDlyProzesaObnovleniyaKodaPosleZapuska $model
     * @return void
     */
    public function sozdatZadachu(OcheredDlyProzesaObnovleniyaKodaPosleZapuska $model): void
    {
        $this->zadachaDlyOcherediService
            ->dobavitZadachuVOchered(OcheredDlyProzesaObnovleniyaPosleZapuskaJobHandler::QUEUE_NAME, $model, 2);
    }

    protected function getExceptionEvents(): array
    {
        return [
            UnitSozdan::class,
            UnitSozdanSystemoi::class,
            SborkaUnitNachalas::class,
            OshibkaSborkiUnitaUstanovlena::class,
            UspehSborkiUnitaUstanovlen::class,
            IzmenenieVetkiNachalos::class,
            OshibkaIzmeneniyaVetkiUnitaUstanovlena::class,
            UspehIzmeneniyaVetkiUstanovlen::class,
            PeremenieUnitaZapolneni::class,
            PodgotovkaUnitaNachalas::class,
            ObnovlenieKodaUnitaPosleZapuskaNachalos::class,
            ObnovlenieUnitaNachalos::class,
            KonfigUnitaUstanovlen::class,
            SbrosPodgotovkiNachalsya::class,
            OstanovkaUnitaNachalas::class,
            UdalenieUnitaPosleZapuskaNachalos::class,
            OshibkaUdaleniyaUnitaPosleZapuskaUstanovlena::class,
            KodVetkiIzmenilsyaVHranilishe::class,
            AvtosborkaUnitaNachalas::class,
            OshibkaAvtosborkiUstanovlena::class,
            StatistikaPoKonteineruObnovlena::class,
            VipolnenieDeistviyaNachalos::class,
            UspehDeistviyaUstanovlen::class,
            OshibkaDeistviyaUstanovlena::class,
            UnitSbroshenDoSostoyaniyaSborki::class
        ];
    }

    function handleOshibkaPodgotovkiUnitaUstanovlena(OshibkaPodgotovkiUnitaUstanovlena $fact): void
    {
        $unit = $this->unitRepository->getById($fact->unitId);
        $workflowId = $unit->poluchitWorkflowIdDlyObnovleniyaKodaPosleZapuska();
        if (!empty($workflowId)) {
            $model = new OcheredDlyProzesaObnovleniyaKodaPosleZapuska($fact->unitId, OcheredDlyProzesaObnovleniyaKodaPosleZapuska::ERROR);
            $this->sozdatZadachu($model);
        }
    }

    function handleUspehPodgotovkiUnitaUstanovlen(UspehPodgotovkiUnitaUstanovlen $fact): void
    {
        $unit = $this->unitRepository->getById($fact->unitId);
        $workflowId = $unit->poluchitWorkflowIdDlyObnovleniyaKodaPosleZapuska();
        if (!empty($workflowId)) {
            $model = new OcheredDlyProzesaObnovleniyaKodaPosleZapuska($fact->unitId, OcheredDlyProzesaObnovleniyaKodaPosleZapuska::PODGOTOVLEN);
            $this->sozdatZadachu($model);
        }
    }

    function handleOshibkaObnovleniyaUnitaUstanovlena(OshibkaObnovleniyaUnitaUstanovlena $fact): void
    {
        $unit = $this->unitRepository->getById($fact->unitId);
        $workflowId = $unit->poluchitWorkflowIdDlyObnovleniyaKodaPosleZapuska();
        if (!empty($workflowId)) {
            $model = new OcheredDlyProzesaObnovleniyaKodaPosleZapuska($fact->unitId, OcheredDlyProzesaObnovleniyaKodaPosleZapuska::ERROR);
            $this->sozdatZadachu($model);
        }
    }

    function handleUspehObnovleniyaUnitaUstanovlen(UspehObnovleniyaUnitaUstanovlen $fact): void
    {
        $unit = $this->unitRepository->getById($fact->unitId);
        $workflowId = $unit->poluchitWorkflowIdDlyObnovleniyaKodaPosleZapuska();
        if (!empty($workflowId)) {
            $model = new OcheredDlyProzesaObnovleniyaKodaPosleZapuska($fact->unitId, OcheredDlyProzesaObnovleniyaKodaPosleZapuska::OBNOVLEN);
            $this->sozdatZadachu($model);
        }
    }

    function handleOshibkaSbrosaPodgotovkiUnitaUstanovlena(OshibkaSbrosaPodgotovkiUnitaUstanovlena $fact): void
    {
        $unit = $this->unitRepository->getById($fact->unitId);
        $workflowId = $unit->poluchitWorkflowIdDlyObnovleniyaKodaPosleZapuska();
        if (!empty($workflowId)) {
            $model = new OcheredDlyProzesaObnovleniyaKodaPosleZapuska($fact->unitId, OcheredDlyProzesaObnovleniyaKodaPosleZapuska::ERROR);
            $this->sozdatZadachu($model);
        }
    }

    function handleUspehSbrosaPodgotovkiUnitaUstanovlen(UspehSbrosaPodgotovkiUnitaUstanovlen $fact): void
    {
        $unit = $this->unitRepository->getById($fact->unitId);
        $workflowId = $unit->poluchitWorkflowIdDlyObnovleniyaKodaPosleZapuska();
        if (!empty($workflowId)) {
            $model = new OcheredDlyProzesaObnovleniyaKodaPosleZapuska($fact->unitId, OcheredDlyProzesaObnovleniyaKodaPosleZapuska::SBROSHENA_PODGOTOVKA);
            $this->sozdatZadachu($model);
        }
    }

    function handleZapuskUnitNachalsya(ZapuskUnitNachalsya $fact): void
    {
    }

    function handleOshibkaZapuskaUnitaUstanovlena(OshibkaZapuskaUnitaUstanovlena $fact): void
    {
    }

    function handleUspehZapuskaUnitaUstanovlen(UspehZapuskaUnitaUstanovlen $fact): void
    {
    }

    function handleOshibkaOstanovkiUnitaUstanovlena(OshibkaOstanovkiUnitaUstanovlena $fact): void
    {
        $unit = $this->unitRepository->getById($fact->unitId);
        $workflowId = $unit->poluchitWorkflowIdDlyObnovleniyaKodaPosleZapuska();
        if (!empty($workflowId)) {
            $model = new OcheredDlyProzesaObnovleniyaKodaPosleZapuska($fact->unitId, OcheredDlyProzesaObnovleniyaKodaPosleZapuska::ERROR);
            $this->sozdatZadachu($model);
        }
    }

    function handleUspehOstanovkiUnitaUstanovlen(UspehOstanovkiUnitaUstanovlen $fact): void
    {
        $unit = $this->unitRepository->getById($fact->unitId);
        $workflowId = $unit->poluchitWorkflowIdDlyObnovleniyaKodaPosleZapuska();
        if (!empty($workflowId)) {
            $model = new OcheredDlyProzesaObnovleniyaKodaPosleZapuska($fact->unitId, OcheredDlyProzesaObnovleniyaKodaPosleZapuska::OSTANOVLEN);
            $this->sozdatZadachu($model);
        }
    }

    function handleUdalenieUnitaNachalos(UdalenieUnitaNachalos $fact): void
    {
    }

    function handleOshibkaUdaleniyaUnitaUstanovlena(OshibkaUdaleniyaUnitaUstanovlena $fact): void
    {
    }

    function handleUspehUdaleniyaUnitaUstanovlen(UspehUdaleniyaUnitaUstanovlen $fact): void
    {
    }
    function handleSlomaniyUnitUdalen(SlomaniyUnitUdalen $fact): void
    {
    }

    function handleOshibkaObnovleniyaUnitaPosleZapuskaUstanovlena(OshibkaObnovleniyaUnitaPosleZapuskaUstanovlena $fact): void
    {
    }

}
