<?php

namespace App\Unitman\Infra\Projection;

use App\Unitman\Business\Model\Unit\Event\IzmenenieVetkiNachalos;
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
use App\Unitman\Business\ReadModel\Unit\UnitRunnerJob;
use App\Unitman\Business\Utils\UnitmanClassNameMapEnum;
use App\Unitman\Infra\Repository\Unit\UnitRunnerJobRepository;
use App\Utils\EventSauce\AbstractProjection;
use App\Utils\EventSauce\Model\StreamName;

final class UnitRunnerJobsProjection extends AbstractProjection implements UnitmanProjection
{
    const PROJECTION_NAME = 'unit_runner_jobs';

    public function __construct(private UnitRunnerJobRepository $repository)
    {
    }

    function isSyncProjection(): bool
    {
        return false;
    }

    function getProjectionName(): string
    {
        return self::PROJECTION_NAME;
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

    }

    function handleOshibkaSborkiUnitaUstanovlena(OshibkaSborkiUnitaUstanovlena $fact): void
    {
        $job = new UnitRunnerJob('stub', $fact->unitId, OcheredUnitovReadModel::SBORKA, false, $fact->steps);
        $this->repository->insert($job);
    }

    function handleUspehSborkiUnitaUstanovlen(UspehSborkiUnitaUstanovlen $fact): void
    {
        $job = new UnitRunnerJob('stub', $fact->unitId, OcheredUnitovReadModel::SBORKA, true, $fact->steps);
        $this->repository->insert($job);
    }

    function handleIzmenenieVetkiNachalos(IzmenenieVetkiNachalos $fact): void
    {

    }

    function handleOshibkaIzmeneniyaVetkiUnitaUstanovlena(OshibkaIzmeneniyaVetkiUnitaUstanovlena $fact): void
    {
        $job = new UnitRunnerJob('stub', $fact->unitId, OcheredUnitovReadModel::IZMENENIYE_VETKI, false, $fact->steps);
        $this->repository->insert($job);
    }

    function handleUspehIzmeneniyaVetkiUstanovlen(UspehIzmeneniyaVetkiUstanovlen $fact): void
    {
        $job = new UnitRunnerJob('stub', $fact->unitId, OcheredUnitovReadModel::IZMENENIYE_VETKI, true, $fact->steps);
        $this->repository->insert($job);
    }



    function handlePeremenieUnitaZapolneni(PeremenieUnitaZapolneni $fact): void
    {

    }

    function handlePodgotovkaUnitaNachalas(PodgotovkaUnitaNachalas $fact): void
    {

    }

    function handleOshibkaPodgotovkiUnitaUstanovlena(OshibkaPodgotovkiUnitaUstanovlena $fact): void
    {
        $job = new UnitRunnerJob('stub', $fact->unitId, OcheredUnitovReadModel::PODGOTOVKA, false, $fact->steps);
        $this->repository->insert($job);
    }

    function handleUspehPodgotovkiUnitaUstanovlen(UspehPodgotovkiUnitaUstanovlen $fact): void
    {
        $job = new UnitRunnerJob('stub', $fact->unitId, OcheredUnitovReadModel::PODGOTOVKA, true, $fact->steps);
        $this->repository->insert($job);
    }

    function handleObnovlenieUnitaNachalos(ObnovlenieUnitaNachalos $fact): void
    {

    }

    function handleOshibkaObnovleniyaUnitaUstanovlena(OshibkaObnovleniyaUnitaUstanovlena $fact): void
    {
        $job = new UnitRunnerJob('stub', $fact->unitId, OcheredUnitovReadModel::OBNOVLENIE, false, $fact->steps);
        $this->repository->insert($job);
    }

    function handleUspehObnovleniyaUnitaUstanovlen(UspehObnovleniyaUnitaUstanovlen $fact): void
    {
        $job = new UnitRunnerJob('stub', $fact->unitId, OcheredUnitovReadModel::OBNOVLENIE, true, $fact->steps);
        $this->repository->insert($job);
    }

    function handleKonfigUnitaUstanovlen(KonfigUnitaUstanovlen $fact): void
    {

    }

    function handleSbrosPodgotovkiNachalsya(SbrosPodgotovkiNachalsya $fact): void
    {

    }

    function handleOshibkaSbrosaPodgotovkiUnitaUstanovlena(OshibkaSbrosaPodgotovkiUnitaUstanovlena $fact): void
    {
        $job = new UnitRunnerJob('stub', $fact->unitId, OcheredUnitovReadModel::SBROS_PODGOTOVKI, false, $fact->steps);
        $this->repository->insert($job);
    }

    function handleUspehSbrosaPodgotovkiUnitaUstanovlen(UspehSbrosaPodgotovkiUnitaUstanovlen $fact): void
    {
        $job = new UnitRunnerJob('stub', $fact->unitId, OcheredUnitovReadModel::SBROS_PODGOTOVKI, true, $fact->steps);
        $this->repository->insert($job);
    }

    function handleZapuskUnitNachalsya(ZapuskUnitNachalsya $fact): void
    {

    }

    function handleOshibkaZapuskaUnitaUstanovlena(OshibkaZapuskaUnitaUstanovlena $fact): void
    {
        $job = new UnitRunnerJob('stub', $fact->unitId, OcheredUnitovReadModel::ZAPUSK, false, $fact->steps);
        $this->repository->insert($job);
    }

    function handleUspehZapuskaUnitaUstanovlen(UspehZapuskaUnitaUstanovlen $fact): void
    {
        $job = new UnitRunnerJob('stub', $fact->unitId, OcheredUnitovReadModel::ZAPUSK, true, $fact->steps);
        $this->repository->insert($job);
    }

    function handleOstanovkaUnitaNachalas(OstanovkaUnitaNachalas $fact): void
    {

    }

    function handleOshibkaOstanovkiUnitaUstanovlena(OshibkaOstanovkiUnitaUstanovlena $fact): void
    {
        $job = new UnitRunnerJob('stub', $fact->unitId, OcheredUnitovReadModel::OSTANOVKA, false, $fact->steps);
        $this->repository->insert($job);
    }

    function handleUspehOstanovkiUnitaUstanovlen(UspehOstanovkiUnitaUstanovlen $fact): void
    {
        $job = new UnitRunnerJob('stub', $fact->unitId, OcheredUnitovReadModel::OSTANOVKA, true, $fact->steps);
        $this->repository->insert($job);
    }

    function handleUdalenieUnitaNachalos(UdalenieUnitaNachalos $fact): void
    {

    }

    function handleOshibkaUdaleniyaUnitaUstanovlena(OshibkaUdaleniyaUnitaUstanovlena $fact): void
    {
        $job = new UnitRunnerJob('stub', $fact->unitId, OcheredUnitovReadModel::UDALENIE, false, $fact->steps);
        $this->repository->insert($job);
    }

    function handleUspehUdaleniyaUnitaUstanovlen(UspehUdaleniyaUnitaUstanovlen $fact): void
    {
        $job = new UnitRunnerJob('stub', $fact->unitId, OcheredUnitovReadModel::UDALENIE, true, $fact->steps);
        $this->repository->insert($job);
    }
    function handleSlomaniyUnitUdalen(SlomaniyUnitUdalen $fact): void
    {
    }

    function handleObnovlenieKodaUnitaPosleZapuskaNachalos(ObnovlenieKodaUnitaPosleZapuskaNachalos $fact): void
    {

    }

    function handleOshibkaObnovleniyaUnitaPosleZapuskaUstanovlena(OshibkaObnovleniyaUnitaPosleZapuskaUstanovlena $fact): void
    {

    }
}
