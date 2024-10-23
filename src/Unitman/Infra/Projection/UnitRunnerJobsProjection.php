<?php

namespace App\Unitman\Infra\Projection;

use App\Unitman\Business\Model\Unit\Event\AvtosborkaUnitaNachalas;
use App\Unitman\Business\Model\Unit\Event\IzmenenieVetkiNachalos;
use App\Unitman\Business\Model\Unit\Event\KodVetkiIzmenilsyaVHranilishe;
use App\Unitman\Business\Model\Unit\Event\KonfigUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\ObnovlenieKodaUnitaPosleZapuskaNachalos;
use App\Unitman\Business\Model\Unit\Event\ObnovlenieUnitaNachalos;
use App\Unitman\Business\Model\Unit\Event\OshibkaAvtosborkiUstanovlena;
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

    protected function getExceptionEvents(): array
    {
        return [
            UdalenieUnitaPosleZapuskaNachalos::class,
            OshibkaUdaleniyaUnitaPosleZapuskaUstanovlena::class,
            ObnovlenieKodaUnitaPosleZapuskaNachalos::class,
            OshibkaObnovleniyaUnitaPosleZapuskaUstanovlena::class,
            UnitSozdan::class,
            UnitSozdanSystemoi::class,
            SborkaUnitNachalas::class,
            IzmenenieVetkiNachalos::class,
            PeremenieUnitaZapolneni::class,
            PodgotovkaUnitaNachalas::class,
            ObnovlenieUnitaNachalos::class,
            KonfigUnitaUstanovlen::class,
            SbrosPodgotovkiNachalsya::class,
            ZapuskUnitNachalsya::class,
            OstanovkaUnitaNachalas::class,
            UdalenieUnitaNachalos::class,
            SlomaniyUnitUdalen::class,
            KodVetkiIzmenilsyaVHranilishe::class,
            AvtosborkaUnitaNachalas::class,
            OshibkaAvtosborkiUstanovlena::class,
            StatistikaPoKonteineruObnovlena::class,
        ];
    }

    function handleOshibkaSborkiUnitaUstanovlena(OshibkaSborkiUnitaUstanovlena $fact): void
    {
        $job = new UnitRunnerJob('stub', $fact->unitId, UnitRunnerJob::SBORKA, false, $fact->steps);
        $this->repository->insert($job);
    }

    function handleUspehSborkiUnitaUstanovlen(UspehSborkiUnitaUstanovlen $fact): void
    {
        $job = new UnitRunnerJob('stub', $fact->unitId, UnitRunnerJob::SBORKA, true, $fact->steps);
        $this->repository->insert($job);
    }

    function handleOshibkaIzmeneniyaVetkiUnitaUstanovlena(OshibkaIzmeneniyaVetkiUnitaUstanovlena $fact): void
    {
        $job = new UnitRunnerJob('stub', $fact->unitId, UnitRunnerJob::IZMENENIYE_VETKI, false, $fact->steps);
        $this->repository->insert($job);
    }

    function handleUspehIzmeneniyaVetkiUstanovlen(UspehIzmeneniyaVetkiUstanovlen $fact): void
    {
        $job = new UnitRunnerJob('stub', $fact->unitId, UnitRunnerJob::IZMENENIYE_VETKI, true, $fact->steps);
        $this->repository->insert($job);
    }

    function handleOshibkaPodgotovkiUnitaUstanovlena(OshibkaPodgotovkiUnitaUstanovlena $fact): void
    {
        $job = new UnitRunnerJob('stub', $fact->unitId, UnitRunnerJob::PODGOTOVKA, false, $fact->steps);
        $this->repository->insert($job);
    }

    function handleUspehPodgotovkiUnitaUstanovlen(UspehPodgotovkiUnitaUstanovlen $fact): void
    {
        $job = new UnitRunnerJob('stub', $fact->unitId, UnitRunnerJob::PODGOTOVKA, true, $fact->steps);
        $this->repository->insert($job);
    }

    function handleOshibkaObnovleniyaUnitaUstanovlena(OshibkaObnovleniyaUnitaUstanovlena $fact): void
    {
        $job = new UnitRunnerJob('stub', $fact->unitId, UnitRunnerJob::OBNOVLENIE, false, $fact->steps);
        $this->repository->insert($job);
    }

    function handleUspehObnovleniyaUnitaUstanovlen(UspehObnovleniyaUnitaUstanovlen $fact): void
    {
        $job = new UnitRunnerJob('stub', $fact->unitId, UnitRunnerJob::OBNOVLENIE, true, $fact->steps);
        $this->repository->insert($job);
    }

    function handleOshibkaSbrosaPodgotovkiUnitaUstanovlena(OshibkaSbrosaPodgotovkiUnitaUstanovlena $fact): void
    {
        $job = new UnitRunnerJob('stub', $fact->unitId, UnitRunnerJob::SBROS_PODGOTOVKI, false, $fact->steps);
        $this->repository->insert($job);
    }

    function handleUspehSbrosaPodgotovkiUnitaUstanovlen(UspehSbrosaPodgotovkiUnitaUstanovlen $fact): void
    {
        $job = new UnitRunnerJob('stub', $fact->unitId, UnitRunnerJob::SBROS_PODGOTOVKI, true, $fact->steps);
        $this->repository->insert($job);
    }

    function handleOshibkaZapuskaUnitaUstanovlena(OshibkaZapuskaUnitaUstanovlena $fact): void
    {
        $job = new UnitRunnerJob('stub', $fact->unitId, UnitRunnerJob::ZAPUSK, false, $fact->steps);
        $this->repository->insert($job);
    }

    function handleUspehZapuskaUnitaUstanovlen(UspehZapuskaUnitaUstanovlen $fact): void
    {
        $job = new UnitRunnerJob('stub', $fact->unitId, UnitRunnerJob::ZAPUSK, true, $fact->steps);
        $this->repository->insert($job);
    }

    function handleOshibkaOstanovkiUnitaUstanovlena(OshibkaOstanovkiUnitaUstanovlena $fact): void
    {
        $job = new UnitRunnerJob('stub', $fact->unitId, UnitRunnerJob::OSTANOVKA, false, $fact->steps);
        $this->repository->insert($job);
    }

    function handleUspehOstanovkiUnitaUstanovlen(UspehOstanovkiUnitaUstanovlen $fact): void
    {
        $job = new UnitRunnerJob('stub', $fact->unitId, UnitRunnerJob::OSTANOVKA, true, $fact->steps);
        $this->repository->insert($job);
    }

    function handleOshibkaUdaleniyaUnitaUstanovlena(OshibkaUdaleniyaUnitaUstanovlena $fact): void
    {
        $job = new UnitRunnerJob('stub', $fact->unitId, UnitRunnerJob::UDALENIE, false, $fact->steps);
        $this->repository->insert($job);
    }

    function handleUspehUdaleniyaUnitaUstanovlen(UspehUdaleniyaUnitaUstanovlen $fact): void
    {
        $job = new UnitRunnerJob('stub', $fact->unitId, UnitRunnerJob::UDALENIE, true, $fact->steps);
        $this->repository->insert($job);
    }
}
