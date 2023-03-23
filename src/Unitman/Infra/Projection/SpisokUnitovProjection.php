<?php

namespace App\Unitman\Infra\Projection;

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
use App\Unitman\Business\ReadModel\Unit\SpisokUnitovReadModel;
use App\Unitman\Infra\Repository\Unit\SpisokUnitovRepository;
use App\Unitman\Infra\Repository\Unit\SqlUnitEventsRepository;
use App\Utils\EventSauce\AbstractProjection;

final class SpisokUnitovProjection extends AbstractProjection implements SyncProjectionForUnit
{
    public function __construct(
        private SqlUnitEventsRepository $eventsRepository,
        private SpisokUnitovRepository $repository
    )
    {
    }

    function handleUnitSozdan(UnitSozdan $fact): void
    {
        $unit = $this->eventsRepository->getById($fact->id);

        $readModel = new SpisokUnitovReadModel(
            $fact->id,
            $fact->name,
            $fact->projectId,
            $fact->branch,
            json_encode($unit->getStateAsArray()),
            '',
            $unit->isWaitResultFromRunner(),
            '',
            ''
        );

        $this->repository->insert($readModel);
    }

    function handleSborkaUnitNachalas(SborkaUnitNachalas $fact): void
    {
        $unit = $this->eventsRepository->getById($fact->unitId);
        $readModel =$this->repository->getById($fact->unitId);
        $readModel->copyAndUpdateData([
            'state' => json_encode($unit->getStateAsArray()),
            'waitResultFromRunner' => $unit->isWaitResultFromRunner(),
        ]);
        $this->repository->update($readModel);
    }

    function handleOshibkaSborkiUnitaUstanovlena(OshibkaSborkiUnitaUstanovlena $fact): void
    {
        $unit = $this->eventsRepository->getById($fact->unitId);
        $readModel =$this->repository->getById($fact->unitId);
        $readModel->copyAndUpdateData([
            'state' => json_encode($unit->getStateAsArray()),
            'waitResultFromRunner' => $unit->isWaitResultFromRunner(),
            'textOtRunnera' => $fact->textOtRunnera,
        ]);
        $this->repository->update($readModel);
    }

    function handleUspehSborkiUnitaUstanovlen(UspehSborkiUnitaUstanovlen $fact): void
    {
        $unit = $this->eventsRepository->getById($fact->unitId);
        $readModel =$this->repository->getById($fact->unitId);
        $readModel->copyAndUpdateData([
            'state' => json_encode($unit->getStateAsArray()),
            'waitResultFromRunner' => $unit->isWaitResultFromRunner(),
            'textOtRunnera' => $fact->textOtRunnera,
            'config' => $fact->configUnita,
        ]);
        $this->repository->update($readModel);
    }

    function handlePeremenieUnitaZapolneni(PeremenieUnitaZapolneni $fact): void
    {
        $unit = $this->eventsRepository->getById($fact->unitId);
        $readModel =$this->repository->getById($fact->unitId);
        $readModel->copyAndUpdateData([
            'configValues' => $fact->values
        ]);
        $this->repository->update($readModel);
    }

    function handlePodgotovkaUnitaNachalas(PodgotovkaUnitaNachalas $fact): void
    {
        $unit = $this->eventsRepository->getById($fact->unitId);
        $readModel =$this->repository->getById($fact->unitId);
        $readModel->copyAndUpdateData([
            'state' => json_encode($unit->getStateAsArray()),
            'waitResultFromRunner' => $unit->isWaitResultFromRunner(),
        ]);
        $this->repository->update($readModel);
    }

    function handleOshibkaPodgotovkiUnitaUstanovlena(OshibkaPodgotovkiUnitaUstanovlena $fact): void
    {
        $unit = $this->eventsRepository->getById($fact->unitId);
        $readModel =$this->repository->getById($fact->unitId);
        $readModel->copyAndUpdateData([
            'state' => json_encode($unit->getStateAsArray()),
            'waitResultFromRunner' => $unit->isWaitResultFromRunner(),
            'textOtRunnera' => $fact->textOtRunnera,
        ]);
        $this->repository->update($readModel);
    }

    function handleUspehPodgotovkiUnitaUstanovlen(UspehPodgotovkiUnitaUstanovlen $fact): void
    {
        $unit = $this->eventsRepository->getById($fact->unitId);
        $readModel =$this->repository->getById($fact->unitId);
        $readModel->copyAndUpdateData([
            'state' => json_encode($unit->getStateAsArray()),
            'waitResultFromRunner' => $unit->isWaitResultFromRunner(),
            'textOtRunnera' => $fact->textOtRunnera,
        ]);
        $this->repository->update($readModel);
    }

    function handleObnovlenieUnitaNachalos(ObnovlenieUnitaNachalos $fact): void
    {
        $unit = $this->eventsRepository->getById($fact->unitId);
        $readModel =$this->repository->getById($fact->unitId);
        $readModel->copyAndUpdateData([
            'state' => json_encode($unit->getStateAsArray()),
            'waitResultFromRunner' => $unit->isWaitResultFromRunner(),
        ]);
        $this->repository->update($readModel);
    }

    function handleOshibkaObnovleniyaUnitaUstanovlena(OshibkaObnovleniyaUnitaUstanovlena $fact): void
    {
        $unit = $this->eventsRepository->getById($fact->unitId);
        $readModel =$this->repository->getById($fact->unitId);
        $readModel->copyAndUpdateData([
            'state' => json_encode($unit->getStateAsArray()),
            'waitResultFromRunner' => $unit->isWaitResultFromRunner(),
            'textOtRunnera' => $fact->textOtRunnera,
        ]);
        $this->repository->update($readModel);
    }

    function handleUspehObnovleniyaUnitaUstanovlen(UspehObnovleniyaUnitaUstanovlen $fact): void
    {
        $unit = $this->eventsRepository->getById($fact->unitId);
        $readModel =$this->repository->getById($fact->unitId);
        $readModel->copyAndUpdateData([
            'state' => json_encode($unit->getStateAsArray()),
            'waitResultFromRunner' => $unit->isWaitResultFromRunner(),
            'textOtRunnera' => $fact->textOtRunnera,
            'config' => $fact->configUnita,
        ]);
        $this->repository->update($readModel);
    }

    function handleSbrosPodgotovkiNachalsya(SbrosPodgotovkiNachalsya $fact): void
    {
        $unit = $this->eventsRepository->getById($fact->unitId);
        $readModel =$this->repository->getById($fact->unitId);
        $readModel->copyAndUpdateData([
            'state' => json_encode($unit->getStateAsArray()),
            'waitResultFromRunner' => $unit->isWaitResultFromRunner(),
        ]);
        $this->repository->update($readModel);
    }

    function handleOshibkaSbrosaPodgotovkiUnitaUstanovlena(OshibkaSbrosaPodgotovkiUnitaUstanovlena $fact): void
    {
        $unit = $this->eventsRepository->getById($fact->unitId);
        $readModel =$this->repository->getById($fact->unitId);
        $readModel->copyAndUpdateData([
            'state' => json_encode($unit->getStateAsArray()),
            'waitResultFromRunner' => $unit->isWaitResultFromRunner(),
            'textOtRunnera' => $fact->textOtRunnera,
        ]);
        $this->repository->update($readModel);
    }

    function handleUspehSbrosaPodgotovkiUnitaUstanovlen(UspehSbrosaPodgotovkiUnitaUstanovlen $fact): void
    {
        $unit = $this->eventsRepository->getById($fact->unitId);
        $readModel =$this->repository->getById($fact->unitId);
        $readModel->copyAndUpdateData([
            'state' => json_encode($unit->getStateAsArray()),
            'waitResultFromRunner' => $unit->isWaitResultFromRunner(),
            'textOtRunnera' => $fact->textOtRunnera,
        ]);
        $this->repository->update($readModel);
    }

    function handleZapuskUnitNachalsya(ZapuskUnitNachalsya $fact): void
    {
        $unit = $this->eventsRepository->getById($fact->unitId);
        $readModel =$this->repository->getById($fact->unitId);
        $readModel->copyAndUpdateData([
            'state' => json_encode($unit->getStateAsArray()),
            'waitResultFromRunner' => $unit->isWaitResultFromRunner(),
        ]);
        $this->repository->update($readModel);
    }

    function handleOshibkaZapuskaUnitaUstanovlena(OshibkaZapuskaUnitaUstanovlena $fact): void
    {
        $unit = $this->eventsRepository->getById($fact->unitId);
        $readModel =$this->repository->getById($fact->unitId);
        $readModel->copyAndUpdateData([
            'state' => json_encode($unit->getStateAsArray()),
            'waitResultFromRunner' => $unit->isWaitResultFromRunner(),
            'textOtRunnera' => $fact->textOtRunnera,
        ]);
        $this->repository->update($readModel);
    }

    function handleUspehZapuskaUnitaUstanovlen(UspehZapuskaUnitaUstanovlen $fact): void
    {
        $unit = $this->eventsRepository->getById($fact->unitId);
        $readModel =$this->repository->getById($fact->unitId);
        $readModel->copyAndUpdateData([
            'state' => json_encode($unit->getStateAsArray()),
            'waitResultFromRunner' => $unit->isWaitResultFromRunner(),
            'textOtRunnera' => $fact->textOtRunnera,
        ]);
        $this->repository->update($readModel);
    }

    function handleOstanovkaUnitaNachalas(OstanovkaUnitaNachalas $fact): void
    {
        $unit = $this->eventsRepository->getById($fact->unitId);
        $readModel =$this->repository->getById($fact->unitId);
        $readModel->copyAndUpdateData([
            'state' => json_encode($unit->getStateAsArray()),
            'waitResultFromRunner' => $unit->isWaitResultFromRunner(),
        ]);
        $this->repository->update($readModel);
    }

    function handleOshibkaOstanovkiUnitaUstanovlena(OshibkaOstanovkiUnitaUstanovlena $fact): void
    {
        $unit = $this->eventsRepository->getById($fact->unitId);
        $readModel =$this->repository->getById($fact->unitId);
        $readModel->copyAndUpdateData([
            'state' => json_encode($unit->getStateAsArray()),
            'waitResultFromRunner' => $unit->isWaitResultFromRunner(),
            'textOtRunnera' => $fact->textOtRunnera,
        ]);
        $this->repository->update($readModel);
    }

    function handleUspehOstanovkiUnitaUstanovlen(UspehOstanovkiUnitaUstanovlen $fact): void
    {
        $unit = $this->eventsRepository->getById($fact->unitId);
        $readModel =$this->repository->getById($fact->unitId);
        $readModel->copyAndUpdateData([
            'state' => json_encode($unit->getStateAsArray()),
            'waitResultFromRunner' => $unit->isWaitResultFromRunner(),
            'textOtRunnera' => $fact->textOtRunnera,
        ]);
        $this->repository->update($readModel);
    }

    function handleUdalenieUnitaNachalos(UdalenieUnitaNachalos $fact): void
    {
        $unit = $this->eventsRepository->getById($fact->unitId);
        $readModel =$this->repository->getById($fact->unitId);
        $readModel->copyAndUpdateData([
            'state' => json_encode($unit->getStateAsArray()),
            'waitResultFromRunner' => $unit->isWaitResultFromRunner(),
        ]);
        $this->repository->update($readModel);
    }

    function handleOshibkaUdaleniyaUnitaUstanovlena(OshibkaUdaleniyaUnitaUstanovlena $fact): void
    {
        $unit = $this->eventsRepository->getById($fact->unitId);
        $readModel =$this->repository->getById($fact->unitId);
        $readModel->copyAndUpdateData([
            'state' => json_encode($unit->getStateAsArray()),
            'waitResultFromRunner' => $unit->isWaitResultFromRunner(),
            'textOtRunnera' => $fact->textOtRunnera,
        ]);
        $this->repository->update($readModel);
    }

    function handleUspehUdaleniyaUnitaUstanovlen(UspehUdaleniyaUnitaUstanovlen $fact): void
    {
        $this->repository->delete($fact->unitId);
    }
    function handleSlomaniyUnitUdalen(SlomaniyUnitUdalen $fact): void
    {
        $this->repository->delete($fact->unitId);
    }
}
