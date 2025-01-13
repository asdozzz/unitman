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
use App\Unitman\Business\ReadModel\Unit\OcheredDlyProzesaUdaleniyaUnitaPosleZapuska;
use App\Unitman\Business\Utils\UnitmanClassNameMapEnum;
use App\Unitman\Infra\Jobs\OcheredDlyProzesaUdaleniyaUnitaPosleZapuskaJobHandler;
use App\Unitman\Infra\Jobs\ZadachaDlyOcherediService;
use App\Unitman\Infra\Repository\ZadachaDlyOcherediRepository;
use App\Utils\EventSauce\AbstractProjection;
use App\Utils\EventSauce\Model\StreamName;

final class OcheredDlyProzesaUdaleniyaPosleZapuskaProjection extends AbstractProjection implements UnitmanProjection
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
            UdalenieUnitaNachalos::class,
            OshibkaUdaleniyaUnitaUstanovlena::class,
            UspehUdaleniyaUnitaUstanovlen::class,
            SlomaniyUnitUdalen::class,
            OshibkaUdaleniyaUnitaPosleZapuskaUstanovlena::class,
            AvtosborkaUnitaNachalas::class,
            OshibkaAvtosborkiUstanovlena::class,
            StatistikaPoKonteineruObnovlena::class,
            VipolnenieDeistviyaNachalos::class,
            UspehDeistviyaUstanovlen::class,
            OshibkaDeistviyaUstanovlena::class,
        ];
    }

    /**
     * @param OcheredDlyProzesaUdaleniyaUnitaPosleZapuska $model
     * @return void
     */
    public function sozdatZadachu(OcheredDlyProzesaUdaleniyaUnitaPosleZapuska $model): void
    {
        $this->zadachaDlyOcherediService
            ->dobavitZadachuVOchered(OcheredDlyProzesaUdaleniyaUnitaPosleZapuskaJobHandler::QUEUE_NAME, $model, 2);
    }

    function handleOshibkaSbrosaPodgotovkiUnitaUstanovlena(OshibkaSbrosaPodgotovkiUnitaUstanovlena $fact): void
    {
        $unit = $this->unitRepository->getById($fact->unitId);
        $workflowId = $unit->poluchitWorkflowIdDlyUdaleniyaUnitaPosleZapuska();
        if (!empty($workflowId)) {
            $model = new OcheredDlyProzesaUdaleniyaUnitaPosleZapuska($fact->unitId, OcheredDlyProzesaUdaleniyaUnitaPosleZapuska::ERROR);
            $this->sozdatZadachu($model);
        }
    }

    function handleUspehSbrosaPodgotovkiUnitaUstanovlen(UspehSbrosaPodgotovkiUnitaUstanovlen $fact): void
    {
        $unit = $this->unitRepository->getById($fact->unitId);
        $workflowId = $unit->poluchitWorkflowIdDlyUdaleniyaUnitaPosleZapuska();
        if (!empty($workflowId)) {
            $model = new OcheredDlyProzesaUdaleniyaUnitaPosleZapuska($fact->unitId, OcheredDlyProzesaUdaleniyaUnitaPosleZapuska::SBROSHENA_PODGOTOVKA);
            $this->sozdatZadachu($model);
        }
    }

    function handleOshibkaOstanovkiUnitaUstanovlena(OshibkaOstanovkiUnitaUstanovlena $fact): void
    {
        $unit = $this->unitRepository->getById($fact->unitId);
        $workflowId = $unit->poluchitWorkflowIdDlyUdaleniyaUnitaPosleZapuska();
        if (!empty($workflowId)) {
            $model = new OcheredDlyProzesaUdaleniyaUnitaPosleZapuska($fact->unitId, OcheredDlyProzesaUdaleniyaUnitaPosleZapuska::ERROR);
            $this->sozdatZadachu($model);
        }
    }

    function handleUspehOstanovkiUnitaUstanovlen(UspehOstanovkiUnitaUstanovlen $fact): void
    {
        $unit = $this->unitRepository->getById($fact->unitId);
        $workflowId = $unit->poluchitWorkflowIdDlyUdaleniyaUnitaPosleZapuska();

        if (!empty($workflowId)) {
            $model = new OcheredDlyProzesaUdaleniyaUnitaPosleZapuska($fact->unitId, OcheredDlyProzesaUdaleniyaUnitaPosleZapuska::OSTANOVLEN);
            $this->sozdatZadachu($model);
        }
    }

}
