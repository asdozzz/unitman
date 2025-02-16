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
use App\Unitman\Business\ReadModel\Unit\OcheredUnitovReadModel;
use App\Unitman\Business\Utils\UnitmanClassNameMapEnum;
use App\Unitman\Infra\Repository\Unit\OcheredUnitovRepository;
use App\Utils\EventSauce\AbstractProjection;
use App\Utils\EventSauce\Model\StreamName;

final class OcheredUnitovProjection extends AbstractProjection implements UnitmanProjection
{
    public function __construct(private OcheredUnitovRepository $repository)
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

    protected function getExceptionEvents(): array
    {
        return [
            UnitSozdan::class,
            UnitSozdanSystemoi::class,
            UdalenieUnitaPosleZapuskaNachalos::class,
            OshibkaUdaleniyaUnitaPosleZapuskaUstanovlena::class,
            ObnovlenieKodaUnitaPosleZapuskaNachalos::class,
            OshibkaObnovleniyaUnitaPosleZapuskaUstanovlena::class,
            KonfigUnitaUstanovlen::class,
            PeremenieUnitaZapolneni::class,
            KodVetkiIzmenilsyaVHranilishe::class,
            AvtosborkaUnitaNachalas::class,
            OshibkaAvtosborkiUstanovlena::class,
            StatistikaPoKonteineruObnovlena::class,
        ];
    }

    function handleSborkaUnitNachalas(SborkaUnitNachalas $fact): void
    {
        $this->repository->insert($fact->unitId, OcheredUnitovReadModel::SBORKA);
    }

    function handleOshibkaSborkiUnitaUstanovlena(OshibkaSborkiUnitaUstanovlena $fact): void
    {
        $this->repository->removeByUnitIdAndQueueName($fact->unitId, OcheredUnitovReadModel::SBORKA);
    }

    function handleUspehSborkiUnitaUstanovlen(UspehSborkiUnitaUstanovlen $fact): void
    {
        $this->repository->removeByUnitIdAndQueueName($fact->unitId, OcheredUnitovReadModel::SBORKA);
    }

    function handleIzmenenieVetkiNachalos(IzmenenieVetkiNachalos $fact): void
    {
        $this->repository->insert($fact->unitId, OcheredUnitovReadModel::IZMENENIYE_VETKI);
    }

    function handleOshibkaIzmeneniyaVetkiUnitaUstanovlena(OshibkaIzmeneniyaVetkiUnitaUstanovlena $fact): void
    {
        $this->repository->removeByUnitIdAndQueueName($fact->unitId, OcheredUnitovReadModel::IZMENENIYE_VETKI);
    }

    function handleUspehIzmeneniyaVetkiUstanovlen(UspehIzmeneniyaVetkiUstanovlen $fact): void
    {
        $this->repository->removeByUnitIdAndQueueName($fact->unitId, OcheredUnitovReadModel::IZMENENIYE_VETKI);
    }

    function handlePodgotovkaUnitaNachalas(PodgotovkaUnitaNachalas $fact): void
    {
        $this->repository->insert($fact->unitId, OcheredUnitovReadModel::PODGOTOVKA);
    }

    function handleOshibkaPodgotovkiUnitaUstanovlena(OshibkaPodgotovkiUnitaUstanovlena $fact): void
    {
        $this->repository->removeByUnitIdAndQueueName($fact->unitId, OcheredUnitovReadModel::PODGOTOVKA);
    }

    function handleUspehPodgotovkiUnitaUstanovlen(UspehPodgotovkiUnitaUstanovlen $fact): void
    {
        $this->repository->removeByUnitIdAndQueueName($fact->unitId, OcheredUnitovReadModel::PODGOTOVKA);
    }

    function handleObnovlenieUnitaNachalos(ObnovlenieUnitaNachalos $fact): void
    {
        $this->repository->insert($fact->unitId, OcheredUnitovReadModel::OBNOVLENIE);
    }

    function handleOshibkaObnovleniyaUnitaUstanovlena(OshibkaObnovleniyaUnitaUstanovlena $fact): void
    {
        $this->repository->removeByUnitIdAndQueueName($fact->unitId, OcheredUnitovReadModel::OBNOVLENIE);
    }

    function handleUspehObnovleniyaUnitaUstanovlen(UspehObnovleniyaUnitaUstanovlen $fact): void
    {
        $this->repository->removeByUnitIdAndQueueName($fact->unitId, OcheredUnitovReadModel::OBNOVLENIE);
    }

    function handleSbrosPodgotovkiNachalsya(SbrosPodgotovkiNachalsya $fact): void
    {
        $this->repository->insert($fact->unitId, OcheredUnitovReadModel::SBROS_PODGOTOVKI);
    }

    function handleOshibkaSbrosaPodgotovkiUnitaUstanovlena(OshibkaSbrosaPodgotovkiUnitaUstanovlena $fact): void
    {
        $this->repository->removeByUnitIdAndQueueName($fact->unitId, OcheredUnitovReadModel::SBROS_PODGOTOVKI);
    }

    function handleUspehSbrosaPodgotovkiUnitaUstanovlen(UspehSbrosaPodgotovkiUnitaUstanovlen $fact): void
    {
        $this->repository->removeByUnitIdAndQueueName($fact->unitId, OcheredUnitovReadModel::SBROS_PODGOTOVKI);
    }

    function handleZapuskUnitNachalsya(ZapuskUnitNachalsya $fact): void
    {
        $this->repository->insert($fact->unitId, OcheredUnitovReadModel::ZAPUSK);
    }

    function handleOshibkaZapuskaUnitaUstanovlena(OshibkaZapuskaUnitaUstanovlena $fact): void
    {
        $this->repository->removeByUnitIdAndQueueName($fact->unitId, OcheredUnitovReadModel::ZAPUSK);
    }

    function handleUspehZapuskaUnitaUstanovlen(UspehZapuskaUnitaUstanovlen $fact): void
    {
        $this->repository->removeByUnitIdAndQueueName($fact->unitId, OcheredUnitovReadModel::ZAPUSK);
    }

    function handleOstanovkaUnitaNachalas(OstanovkaUnitaNachalas $fact): void
    {
        $this->repository->insert($fact->unitId, OcheredUnitovReadModel::OSTANOVKA);
    }

    function handleOshibkaOstanovkiUnitaUstanovlena(OshibkaOstanovkiUnitaUstanovlena $fact): void
    {
        $this->repository->removeByUnitIdAndQueueName($fact->unitId, OcheredUnitovReadModel::OSTANOVKA);
    }

    function handleUspehOstanovkiUnitaUstanovlen(UspehOstanovkiUnitaUstanovlen $fact): void
    {
        $this->repository->removeByUnitIdAndQueueName($fact->unitId, OcheredUnitovReadModel::OSTANOVKA);
    }

    function handleUdalenieUnitaNachalos(UdalenieUnitaNachalos $fact): void
    {
        $this->repository->removeByUnitId($fact->unitId);
        $this->repository->insert($fact->unitId, OcheredUnitovReadModel::UDALENIE);
    }

    function handleOshibkaUdaleniyaUnitaUstanovlena(OshibkaUdaleniyaUnitaUstanovlena $fact): void
    {
        $this->repository->removeByUnitId($fact->unitId);
    }

    function handleUspehUdaleniyaUnitaUstanovlen(UspehUdaleniyaUnitaUstanovlen $fact): void
    {
        $this->repository->removeByUnitId($fact->unitId);
    }

    function handleVipolnenieDeistviyaNachalos(VipolnenieDeistviyaNachalos $fact): void
    {
        $this->repository->insert($fact->unitId, OcheredUnitovReadModel::DEISTVIE);
    }

    function handleOshibkaDeistviyaUstanovlena(OshibkaDeistviyaUstanovlena $fact): void
    {
        $this->repository->removeByUnitId($fact->unitId);
    }

    function handleUspehDeistviyaUstanovlen(UspehDeistviyaUstanovlen $fact): void
    {
        $this->repository->removeByUnitId($fact->unitId);
    }

    function handleSlomaniyUnitUdalen(SlomaniyUnitUdalen $fact): void
    {
        $this->repository->removeByUnitId($fact->unitId);
    }

    function handleUnitSbroshenDoSostoyaniyaSborki(UnitSbroshenDoSostoyaniyaSborki $fact): void
    {
        $this->repository->removeByUnitId($fact->unitId);
    }

    function isSyncProjection(): bool
    {
        return true;
    }

    function isAllowedRebuild(): bool
    {
        return true;
    }
}
