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
use App\Unitman\Business\ReadModel\Unit\OcheredDlyProzesaSozdaniyaUnitaSystemoi;
use App\Unitman\Business\Utils\UnitmanClassNameMapEnum;
use App\Unitman\Infra\Jobs\OcheredDlyProzesaSozdaniyaUnitaSystemoiJobHandler;
use App\Unitman\Infra\Jobs\ZadachaDlyOcherediService;
use App\Unitman\Infra\Repository\ZadachaDlyOcherediRepository;
use App\Utils\EventSauce\AbstractProjection;
use App\Utils\EventSauce\Model\StreamName;

final class OcheredDlyProzesaSozdaniyaUnitaSystemoiProjection extends AbstractProjection implements UnitmanProjection
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
        return 'prozess_sozdaniya_unita_systemoi';
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
     * @param OcheredDlyProzesaSozdaniyaUnitaSystemoi $model
     * @return void
     */
    public function sozdatZadachu(OcheredDlyProzesaSozdaniyaUnitaSystemoi $model): void
    {
        $this->zadachaDlyOcherediService
            ->dobavitZadachuVOchered(OcheredDlyProzesaSozdaniyaUnitaSystemoiJobHandler::QUEUE_NAME, $model, 2);
    }

    protected function getExceptionEvents(): array
    {
        return [
            UnitSozdanSystemoi::class,
            UnitSozdan::class,
            SborkaUnitNachalas::class,
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
            OshibkaSbrosaPodgotovkiUnitaUstanovlena::class,
            UspehSbrosaPodgotovkiUnitaUstanovlen::class,
            OstanovkaUnitaNachalas::class,
            OshibkaOstanovkiUnitaUstanovlena::class,
            UspehOstanovkiUnitaUstanovlen::class,
            UdalenieUnitaPosleZapuskaNachalos::class,
            OshibkaUdaleniyaUnitaPosleZapuskaUstanovlena::class,
            KodVetkiIzmenilsyaVHranilishe::class,
            UdalenieUnitaNachalos::class,
            OshibkaUdaleniyaUnitaUstanovlena::class,
            UspehUdaleniyaUnitaUstanovlen::class,
            SlomaniyUnitUdalen::class,
            ZapuskUnitNachalsya::class,
            OshibkaZapuskaUnitaUstanovlena::class,
            UspehZapuskaUnitaUstanovlen::class,
            OshibkaAvtosborkiUstanovlena::class,
            StatistikaPoKonteineruObnovlena::class,
            VipolnenieDeistviyaNachalos::class,
            UspehDeistviyaUstanovlen::class,
            OshibkaDeistviyaUstanovlena::class,
        ];
    }

    public function handleAvtosborkaUnitaNachalas(AvtosborkaUnitaNachalas $fact): void
    {
        $model = new OcheredDlyProzesaSozdaniyaUnitaSystemoi($fact->unitId, OcheredDlyProzesaSozdaniyaUnitaSystemoi::SOZDAN);
        $this->sozdatZadachu($model);
    }

    public function handleOshibkaSborkiUnitaUstanovlena(OshibkaSborkiUnitaUstanovlena $fact): void
    {
        $unit = $this->unitRepository->getById($fact->unitId);
        $workflowId = $unit->poluchitWorkflowIdDlySozdaniyaUnitaSystemoi();
        if (!empty($workflowId)) {
            $model = new OcheredDlyProzesaSozdaniyaUnitaSystemoi($fact->unitId, OcheredDlyProzesaSozdaniyaUnitaSystemoi::ERROR);
            $this->sozdatZadachu($model);
        }
    }

    public function handleUspehSborkiUnitaUstanovlen(UspehSborkiUnitaUstanovlen $fact): void
    {
        $unit = $this->unitRepository->getById($fact->unitId);
        $workflowId = $unit->poluchitWorkflowIdDlySozdaniyaUnitaSystemoi();
        if (!empty($workflowId)) {
            $model = new OcheredDlyProzesaSozdaniyaUnitaSystemoi($fact->unitId, OcheredDlyProzesaSozdaniyaUnitaSystemoi::SOBRAN);
            $this->sozdatZadachu($model);
        }
    }

    public function handleOshibkaPodgotovkiUnitaUstanovlena(OshibkaPodgotovkiUnitaUstanovlena $fact): void
    {
        $unit = $this->unitRepository->getById($fact->unitId);
        $workflowId = $unit->poluchitWorkflowIdDlySozdaniyaUnitaSystemoi();
        if (!empty($workflowId)) {
            $model = new OcheredDlyProzesaSozdaniyaUnitaSystemoi($fact->unitId, OcheredDlyProzesaSozdaniyaUnitaSystemoi::ERROR);
            $this->sozdatZadachu($model);
        }
    }

    public function handleUspehPodgotovkiUnitaUstanovlen(UspehPodgotovkiUnitaUstanovlen $fact): void
    {
        $unit = $this->unitRepository->getById($fact->unitId);
        $workflowId = $unit->poluchitWorkflowIdDlySozdaniyaUnitaSystemoi();
        if (!empty($workflowId)) {
            $model = new OcheredDlyProzesaSozdaniyaUnitaSystemoi($fact->unitId, OcheredDlyProzesaSozdaniyaUnitaSystemoi::PODGOTOVLEN);
            $this->sozdatZadachu($model);
        }
    }
}
