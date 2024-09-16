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
use App\Unitman\Business\ReadModel\Unit\OcheredUnitovReadModel;
use App\Unitman\Business\Utils\UnitmanClassNameMapEnum;
use App\Unitman\Infra\Jobs\OcheredUnitovJobHandler;
use App\Unitman\Infra\Jobs\ZadachaDlyOcherediService;
use App\Unitman\Infra\Repository\ZadachaDlyOcherediRepository;
use App\Utils\EventSauce\AbstractProjection;
use App\Utils\EventSauce\Model\StreamName;

final class OcheredUnitovProjection extends AbstractProjection implements UnitmanProjection
{
    public function __construct(private ZadachaDlyOcherediService $zadachaDlyOcherediService, private ZadachaDlyOcherediRepository $repository)
    {
    }

    function getProjectionName(): string
    {
        return 'ochered_unitov';
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
     * @param OcheredUnitovReadModel $ocheredUnitovReadModel
     * @return void
     */
    public function dobavitZadach(OcheredUnitovReadModel $ocheredUnitovReadModel, $delay = 2, $attempts = 2): void
    {
        $this->zadachaDlyOcherediService->dobavitZadachuVOchered(OcheredUnitovJobHandler::QUEUE_NAME, $ocheredUnitovReadModel, $attempts, $delay);
    }

    protected function getExceptionEvents(): array
    {
        return [
            UdalenieUnitaPosleZapuskaNachalos::class,
            OshibkaUdaleniyaUnitaPosleZapuskaUstanovlena::class,
            ObnovlenieKodaUnitaPosleZapuskaNachalos::class,
            OshibkaObnovleniyaUnitaPosleZapuskaUstanovlena::class,
            KonfigUnitaUstanovlen::class,
            PeremenieUnitaZapolneni::class,
            KodVetkiIzmenilsyaVHranilishe::class,
            OshibkaSborkiUnitaUstanovlena::class,
            UspehSborkiUnitaUstanovlen::class,
            OshibkaIzmeneniyaVetkiUnitaUstanovlena::class,
            UspehIzmeneniyaVetkiUstanovlen::class,
            UnitSozdan::class,
            OshibkaPodgotovkiUnitaUstanovlena::class,
            UspehPodgotovkiUnitaUstanovlen::class,
            OshibkaObnovleniyaUnitaUstanovlena::class,
            UspehObnovleniyaUnitaUstanovlen::class,
            OshibkaSbrosaPodgotovkiUnitaUstanovlena::class,
            UspehSbrosaPodgotovkiUnitaUstanovlen::class,
            OshibkaZapuskaUnitaUstanovlena::class,
            UspehZapuskaUnitaUstanovlen::class,
            OshibkaUdaleniyaUnitaUstanovlena::class,
            UspehUdaleniyaUnitaUstanovlen::class,
            SlomaniyUnitUdalen::class,
            OshibkaOstanovkiUnitaUstanovlena::class,
            UspehOstanovkiUnitaUstanovlen::class,
        ];
    }

    function handleSborkaUnitNachalas(SborkaUnitNachalas $fact): void
    {
        $ocheredUnitovReadModel = new OcheredUnitovReadModel($fact->unitId, OcheredUnitovReadModel::SBORKA);
        $this->dobavitZadach($ocheredUnitovReadModel, 10, 20);
    }

    function handleIzmenenieVetkiNachalos(IzmenenieVetkiNachalos $fact): void
    {
        $ocheredUnitovReadModel = new OcheredUnitovReadModel($fact->unitId, OcheredUnitovReadModel::IZMENENIYE_VETKI);
        $this->dobavitZadach($ocheredUnitovReadModel);
    }

    function handlePodgotovkaUnitaNachalas(PodgotovkaUnitaNachalas $fact): void
    {
        $ocheredUnitovReadModel = new OcheredUnitovReadModel($fact->unitId, OcheredUnitovReadModel::PODGOTOVKA);
        $this->dobavitZadach($ocheredUnitovReadModel);
    }

    function handleObnovlenieUnitaNachalos(ObnovlenieUnitaNachalos $fact): void
    {
        $ocheredUnitovReadModel = new OcheredUnitovReadModel($fact->unitId, OcheredUnitovReadModel::OBNOVLENIE);
        $this->dobavitZadach($ocheredUnitovReadModel);
    }

    function handleSbrosPodgotovkiNachalsya(SbrosPodgotovkiNachalsya $fact): void
    {
        $ocheredUnitovReadModel = new OcheredUnitovReadModel($fact->unitId, OcheredUnitovReadModel::SBROS_PODGOTOVKI);
        $this->dobavitZadach($ocheredUnitovReadModel);
    }

    function handleZapuskUnitNachalsya(ZapuskUnitNachalsya $fact): void
    {
        $ocheredUnitovReadModel = new OcheredUnitovReadModel($fact->unitId, OcheredUnitovReadModel::ZAPUSK);
        $this->dobavitZadach($ocheredUnitovReadModel);
    }

    function handleOstanovkaUnitaNachalas(OstanovkaUnitaNachalas $fact): void
    {
        $ocheredUnitovReadModel = new OcheredUnitovReadModel($fact->unitId, OcheredUnitovReadModel::OSTANOVKA);
        $this->dobavitZadach($ocheredUnitovReadModel);
    }

    function handleUdalenieUnitaNachalos(UdalenieUnitaNachalos $fact): void
    {
        $ocheredUnitovReadModel = new OcheredUnitovReadModel($fact->unitId, OcheredUnitovReadModel::UDALENIE);
        $this->dobavitZadach($ocheredUnitovReadModel);
    }

    function isSyncProjection(): bool
    {
        return true;
    }

    function isAllowedRebuild(): bool
    {
        return false;
    }
}
