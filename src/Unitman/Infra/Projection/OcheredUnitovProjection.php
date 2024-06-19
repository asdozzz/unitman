<?php

namespace App\Unitman\Infra\Projection;

use App\Unitman\Business\Model\Unit\Event\KonfigUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\ObnovlenieUnitaNachalos;
use App\Unitman\Business\Model\Unit\Event\OshibkaObnovleniyaUnitaUstanovlena;
use App\Unitman\Business\Model\Unit\Event\OshibkaOstanovkiUnitaUstanovlena;
use App\Unitman\Business\Model\Unit\Event\OshibkaPodgotovkiUnitaUstanovlena;
use App\Unitman\Business\Model\Unit\Event\OshibkaSborkiUnitaUstanovlena;
use App\Unitman\Business\Model\Unit\Event\OshibkaSbrosaPodgotovkiUnitaUstanovlena;
use App\Unitman\Business\Model\Unit\Event\OshibkaUdaleniyaUnitaUstanovlena;
use App\Unitman\Business\Model\Unit\Event\OshibkaZapuskaUnitaUstanovlena;
use App\Unitman\Business\Model\Unit\Event\OstanovkaUnitaNachalas;
use App\Unitman\Business\Model\Unit\Event\PeremenieUnitaZapolneni;
use App\Unitman\Business\Model\Unit\Event\PodgotovkaUnitaNachalas;
use App\Unitman\Business\Model\Unit\Event\SborkaUnitNachalas;
use App\Unitman\Business\Model\Unit\Event\SbrosPodgotovkiNachalsya;
use App\Unitman\Business\Model\Unit\Event\SlomaniyUnitUdalen;
use App\Unitman\Business\Model\Unit\Event\UdalenieUnitaNachalos;
use App\Unitman\Business\Model\Unit\Event\UnitSozdan;
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

    function handleUnitSozdan(UnitSozdan $fact): void
    {

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

    function handlePeremenieUnitaZapolneni(PeremenieUnitaZapolneni $fact): void
    {

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

    function handleKonfigUnitaUstanovlen(KonfigUnitaUstanovlen $fact): void
    {

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
        $this->repository->insert($fact->unitId, OcheredUnitovReadModel::UDALENIE);
    }

    function handleOshibkaUdaleniyaUnitaUstanovlena(OshibkaUdaleniyaUnitaUstanovlena $fact): void
    {
        $this->repository->removeByUnitIdAndQueueName($fact->unitId, OcheredUnitovReadModel::UDALENIE);
    }

    function handleUspehUdaleniyaUnitaUstanovlen(UspehUdaleniyaUnitaUstanovlen $fact): void
    {
        $this->repository->removeByUnitIdAndQueueName($fact->unitId, OcheredUnitovReadModel::UDALENIE);
    }
    function handleSlomaniyUnitUdalen(SlomaniyUnitUdalen $fact): void
    {
        $this->repository->removeByUnitId($fact->unitId);
    }

    function isSyncProjection(): bool
    {
        return true;
    }
}
