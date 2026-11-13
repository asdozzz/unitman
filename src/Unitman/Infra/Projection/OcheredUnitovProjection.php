<?php

namespace App\Unitman\Infra\Projection;

use App\Unitman\Business\Model\Unit\Event\DeistviePrikreplenoKJobe;
use App\Unitman\Business\Model\Unit\Event\DobavlenProzesVUnit;
use App\Unitman\Business\Model\Unit\Event\KodVetkiIzmenilsyaVHranilishe;
use App\Unitman\Business\Model\Unit\Event\KonfigUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\ObnovlenieUnitaNachalos;
use App\Unitman\Business\Model\Unit\Event\OshibkaDeistviyaUstanovlena;
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
use App\Unitman\Business\Model\Unit\Event\PometitKakSlomanii;
use App\Unitman\Business\Model\Unit\Event\SborkaUnitNachalas;
use App\Unitman\Business\Model\Unit\Event\SbrosPodgotovkiNachalsya;
use App\Unitman\Business\Model\Unit\Event\SlomaniyUnitUdalen;
use App\Unitman\Business\Model\Unit\Event\StatistikaPoKonteineruObnovlena;
use App\Unitman\Business\Model\Unit\Event\UdalenieUnitaNachalos;
use App\Unitman\Business\Model\Unit\Event\UnitSbroshenDoSostoyaniyaSborki;
use App\Unitman\Business\Model\Unit\Event\UnitSozdan;
use App\Unitman\Business\Model\Unit\Event\UnitSozdanSystemoi;
use App\Unitman\Business\Model\Unit\Event\UspehDeistviyaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\UspehObnovleniyaUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\UspehOstanovkiUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\UspehPodgotovkiUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\UspehSborkiUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\UspehSbrosaPodgotovkiUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\UspehUdaleniyaUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\UspehZapuskaUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\VipolnenieDeistviyaNachalos;
use App\Unitman\Business\Model\Unit\Event\ZadachaUnitaOtmenena;
use App\Unitman\Business\Model\Unit\Event\ZapuskUnitNachalsya;
use App\Unitman\Business\Utils\UnitmanClassNameMapEnum;
use App\Unitman\Infra\Repository\Unit\OcheredUnitovRepository;
use App\Utils\EventSauce\AbstractProjection;
use App\Utils\EventSauce\Model\StreamName;
use Psr\Clock\ClockInterface;

final class OcheredUnitovProjection extends AbstractProjection implements UnitmanProjection
{
    public function __construct(private OcheredUnitovRepository $repository, private ClockInterface $clock)
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

    function resetById(string $id): void
    {
        $this->repository->removeByUnitId($id);
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
            KonfigUnitaUstanovlen::class,
            PeremenieUnitaZapolneni::class,
            KodVetkiIzmenilsyaVHranilishe::class,
            StatistikaPoKonteineruObnovlena::class,
        ];
    }

    function handleUnitSozdan(UnitSozdan $fact): void
    {
        $this->repository->insert($fact->id, $this->clock->now());
    }

    function handleUnitSozdanSystemoi(UnitSozdanSystemoi $fact): void
    {
        $this->repository->insert($fact->id, $this->clock->now());
    }

    function handleSborkaUnitNachalas(SborkaUnitNachalas $fact): void
    {
        $this->repository->update($fact->unitId, $this->clock->now());
    }

    function handleDeistviePrikreplenoKJobe(DeistviePrikreplenoKJobe $fact): void
    {
        $this->repository->update($fact->unitId, $this->clock->now());
    }

    /**
     * Новый процесс = новая работа для воркера очереди.
     *
     * Раньше обработчик был пустым: юнит, у которого воркер уже снял строку
     * из очереди (все задания терминальны), после добавления процесса
     * в очередь не возвращался и его задания никто не запускал.
     * update() работает как upsert, поэтому событие и двигает метку уже
     * стоящего юнита, и возвращает снятого.
     */
    function handleDobavlenProzesVUnit(DobavlenProzesVUnit $fact): void
    {
        $this->repository->update($fact->unitId, $this->clock->now());
    }

    function handleOshibkaSborkiUnitaUstanovlena(OshibkaSborkiUnitaUstanovlena $fact): void
    {
        $this->repository->update($fact->unitId, $this->clock->now());
    }

    function handleUspehSborkiUnitaUstanovlen(UspehSborkiUnitaUstanovlen $fact): void
    {
        $this->repository->update($fact->unitId, $this->clock->now());
    }

    function handlePodgotovkaUnitaNachalas(PodgotovkaUnitaNachalas $fact): void
    {
        $this->repository->update($fact->unitId, $this->clock->now());
    }

    function handleOshibkaPodgotovkiUnitaUstanovlena(OshibkaPodgotovkiUnitaUstanovlena $fact): void
    {
       $this->repository->update($fact->unitId, $this->clock->now());
    }

    function handleUspehPodgotovkiUnitaUstanovlen(UspehPodgotovkiUnitaUstanovlen $fact): void
    {
       $this->repository->update($fact->unitId, $this->clock->now());
    }

    function handleObnovlenieUnitaNachalos(ObnovlenieUnitaNachalos $fact): void
    {
        $this->repository->update($fact->unitId, $this->clock->now());
    }

    function handleOshibkaObnovleniyaUnitaUstanovlena(OshibkaObnovleniyaUnitaUstanovlena $fact): void
    {
       $this->repository->update($fact->unitId, $this->clock->now());
    }

    function handleUspehObnovleniyaUnitaUstanovlen(UspehObnovleniyaUnitaUstanovlen $fact): void
    {
       $this->repository->update($fact->unitId, $this->clock->now());
    }

    function handleSbrosPodgotovkiNachalsya(SbrosPodgotovkiNachalsya $fact): void
    {
        $this->repository->update($fact->unitId, $this->clock->now());
    }

    function handleOshibkaSbrosaPodgotovkiUnitaUstanovlena(OshibkaSbrosaPodgotovkiUnitaUstanovlena $fact): void
    {
       $this->repository->update($fact->unitId, $this->clock->now());
    }

    function handleUspehSbrosaPodgotovkiUnitaUstanovlen(UspehSbrosaPodgotovkiUnitaUstanovlen $fact): void
    {
       $this->repository->update($fact->unitId, $this->clock->now());
    }

    function handleZapuskUnitNachalsya(ZapuskUnitNachalsya $fact): void
    {
        $this->repository->update($fact->unitId, $this->clock->now());
    }

    function handleOshibkaZapuskaUnitaUstanovlena(OshibkaZapuskaUnitaUstanovlena $fact): void
    {
       $this->repository->update($fact->unitId, $this->clock->now());
    }

    function handleUspehZapuskaUnitaUstanovlen(UspehZapuskaUnitaUstanovlen $fact): void
    {
       $this->repository->update($fact->unitId, $this->clock->now());
    }

    function handleOstanovkaUnitaNachalas(OstanovkaUnitaNachalas $fact): void
    {
        $this->repository->update($fact->unitId, $this->clock->now());
    }

    function handleOshibkaOstanovkiUnitaUstanovlena(OshibkaOstanovkiUnitaUstanovlena $fact): void
    {
       $this->repository->update($fact->unitId, $this->clock->now());
    }

    function handleUspehOstanovkiUnitaUstanovlen(UspehOstanovkiUnitaUstanovlen $fact): void
    {
       $this->repository->update($fact->unitId, $this->clock->now());
    }

    function handleUdalenieUnitaNachalos(UdalenieUnitaNachalos $fact): void
    {
        $this->repository->update($fact->unitId, $this->clock->now());
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
        $this->repository->update($fact->unitId, $this->clock->now());
    }

    function handleOshibkaDeistviyaUstanovlena(OshibkaDeistviyaUstanovlena $fact): void
    {
        $this->repository->update($fact->unitId, $this->clock->now());
    }

    function handleUspehDeistviyaUstanovlen(UspehDeistviyaUstanovlen $fact): void
    {
        $this->repository->update($fact->unitId, $this->clock->now());
    }

    function handleSlomaniyUnitUdalen(SlomaniyUnitUdalen $fact): void
    {
        $this->repository->removeByUnitId($fact->unitId);
    }

    function handleUnitSbroshenDoSostoyaniyaSborki(UnitSbroshenDoSostoyaniyaSborki $fact): void
    {
        $this->repository->update($fact->unitId, $this->clock->now());
    }

    function handleZadachaUnitaOtmenena(ZadachaUnitaOtmenena $fact): void
    {
        $this->repository->update($fact->unitId, $this->clock->now());
    }

    function handlePometitKakSlomanii(PometitKakSlomanii $fact): void
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
