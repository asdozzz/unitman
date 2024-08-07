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
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Unitman\Business\ReadModel\Unit\SpisokUnitovReadModel;
use App\Unitman\Business\Utils\UnitmanClassNameMapEnum;
use App\Unitman\Infra\Repository\Unit\SpisokUnitovRepository;
use App\Unitman\Infra\Repository\Unit\SqlUnitEventsRepository;
use App\Utils\EventSauce\AbstractProjection;
use App\Utils\EventSauce\Model\StreamName;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

final class SpisokUnitovProjection extends AbstractProjection implements UnitmanProjection
{
    public function __construct(
        private SpisokUnitovRepository $repository,
        private ProjectRepository $projectRepository,
        private UnitmanSecurityService $securityService,
    )
    {
    }

    function isSyncProjection(): bool
    {
        return true;
    }
    function getProjectionName(): string
    {
        return 'spisok_unitov';
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
        $project = $this->projectRepository->getById($fact->projectId);
        $authorName = $this->securityService->getEmailByUserId($fact->authorId);
        $readModel = new SpisokUnitovReadModel(
            $fact->id,
            $fact->authorId,
            $authorName,
            $fact->name,
            $fact->projectId,
            $project->getName(),
            $fact->branch,
            $fact->stateAsArray['code'],
            false,
            $fact->stateAsArray['commands'],
        );

        $this->repository->insert($readModel);
    }

    function handleSborkaUnitNachalas(SborkaUnitNachalas $fact): void
    {
        $readModel =$this->repository->getById($fact->unitId);
        $readModel = $readModel->copyAndUpdateData([
            'state' => $fact->stateAsArray['code'],
            'commands' => $fact->stateAsArray['commands'],
            'waitResultFromRunner' => true,
        ]);
        $this->repository->update($readModel);
    }

    function handleOshibkaSborkiUnitaUstanovlena(OshibkaSborkiUnitaUstanovlena $fact): void
    {
        $readModel =$this->repository->getById($fact->unitId);
        $readModel = $readModel->copyAndUpdateData([
            'state' => $fact->stateAsArray['code'],
            'commands' => $fact->stateAsArray['commands'],
            'waitResultFromRunner' => false,
            'error' => true
        ]);
        $this->repository->update($readModel);
    }

    function handleUspehSborkiUnitaUstanovlen(UspehSborkiUnitaUstanovlen $fact): void
    {
        $readModel =$this->repository->getById($fact->unitId);

        $readModel = $readModel->copyAndUpdateData([
            'state' => $fact->stateAsArray['code'],
            'commands' => $fact->stateAsArray['commands'],
            'waitResultFromRunner' => false,
            'error' => false
        ]);
        $this->repository->update($readModel);
    }

    function handleIzmenenieVetkiNachalos(IzmenenieVetkiNachalos $fact): void
    {
        $readModel =$this->repository->getById($fact->unitId);
        $readModel = $readModel->copyAndUpdateData([
            'state' => $fact->stateAsArray['code'],
            'commands' => $fact->stateAsArray['commands'],
            'waitResultFromRunner' => true,
        ]);
        $this->repository->update($readModel);
    }

    function handleOshibkaIzmeneniyaVetkiUnitaUstanovlena(OshibkaIzmeneniyaVetkiUnitaUstanovlena $fact): void
    {
        $readModel =$this->repository->getById($fact->unitId);
        $readModel = $readModel->copyAndUpdateData([
            'state' => $fact->stateAsArray['code'],
            'commands' => $fact->stateAsArray['commands'],
            'waitResultFromRunner' => false,
            'error' => true
        ]);
        $this->repository->update($readModel);
    }

    function handleUspehIzmeneniyaVetkiUstanovlen(UspehIzmeneniyaVetkiUstanovlen $fact): void
    {
        $readModel =$this->repository->getById($fact->unitId);

        $readModel = $readModel->copyAndUpdateData([
            'state' => $fact->stateAsArray['code'],
            'commands' => $fact->stateAsArray['commands'],
            'waitResultFromRunner' => false,
            'error' => false,
            'branch' => $fact->newBranch
        ]);
        $this->repository->update($readModel);
    }

    function handleKonfigUnitaUstanovlen(KonfigUnitaUstanovlen $fact): void
    {
        $links = [];

        $readModel =$this->repository->getById($fact->unitId);

        if (!empty($fact->configUnita['services'])) {
            $project = $this->projectRepository->getById($readModel->projectId);

            $projectProxyHost = $project->getProxyHost();
            $projectName = $project->getName();
            $pathinfo = parse_url((string)$projectProxyHost);
            /** @var array|false $pathinfo*/

            if ($pathinfo === false) {
                throw new \DomainException('unit.invalid_proxy_host');
            }

            foreach ($fact->configUnita['services'] as $service) {
                foreach ($service['ports'] as $portData) {
                    if ($portData['type'] == 'http') {
                        $link = $pathinfo['scheme'].'://'.$portData['port'].'.'.$readModel->name.'.'.$projectName.'.'.$pathinfo['host'];
                        if (!empty($pathinfo['port'])) {
                            $link .= ':'.$pathinfo['port'];
                        }
                    } else {
                        $link = $portData['type'].'://'.$readModel->name.'.'.$projectName.':'.$portData['port'];
                    }
                    $links[] = $link;
                }
            }
        }

        $readModel = $readModel->copyAndUpdateData([
            'links' => $links,
        ]);
        $this->repository->update($readModel);

    }

    function handlePeremenieUnitaZapolneni(PeremenieUnitaZapolneni $fact): void
    {
        $readModel =$this->repository->getById($fact->unitId);
        $readModel = $readModel->copyAndUpdateData([
            'state' => $fact->stateAsArray['code'],
            'commands' => $fact->stateAsArray['commands'],
        ]);
        $this->repository->update($readModel);
    }

    function handlePodgotovkaUnitaNachalas(PodgotovkaUnitaNachalas $fact): void
    {
        $readModel =$this->repository->getById($fact->unitId);
        $readModel = $readModel->copyAndUpdateData([
            'state' => $fact->stateAsArray['code'],
            'commands' => $fact->stateAsArray['commands'],
            'waitResultFromRunner' => true,
        ]);
        $this->repository->update($readModel);
    }

    function handleOshibkaPodgotovkiUnitaUstanovlena(OshibkaPodgotovkiUnitaUstanovlena $fact): void
    {
        $readModel =$this->repository->getById($fact->unitId);
        $readModel = $readModel->copyAndUpdateData([
            'state' => $fact->stateAsArray['code'],
            'commands' => $fact->stateAsArray['commands'],
            'waitResultFromRunner' => false,
            'jdemObnovlenieKodaPosleZapuska' => false,
            'error' => true
        ]);
        $this->repository->update($readModel);
    }

    function handleUspehPodgotovkiUnitaUstanovlen(UspehPodgotovkiUnitaUstanovlen $fact): void
    {
        $readModel =$this->repository->getById($fact->unitId);
        $readModel = $readModel->copyAndUpdateData([
            'state' => $fact->stateAsArray['code'],
            'commands' => $fact->stateAsArray['commands'],
            'waitResultFromRunner' => false,
            'error' => false
        ]);
        $this->repository->update($readModel);
    }

    function handleObnovlenieUnitaNachalos(ObnovlenieUnitaNachalos $fact): void
    {
        $readModel =$this->repository->getById($fact->unitId);
        $readModel = $readModel->copyAndUpdateData([
            'state' => $fact->stateAsArray['code'],
            'commands' => $fact->stateAsArray['commands'],
            'waitResultFromRunner' => true,
        ]);
        $this->repository->update($readModel);
    }

    function handleOshibkaObnovleniyaUnitaUstanovlena(OshibkaObnovleniyaUnitaUstanovlena $fact): void
    {

        $readModel =$this->repository->getById($fact->unitId);
        $readModel = $readModel->copyAndUpdateData([
            'state' => $fact->stateAsArray['code'],
            'commands' => $fact->stateAsArray['commands'],
            'waitResultFromRunner' => false,
            'jdemObnovlenieKodaPosleZapuska' => false,
            'error' => true
        ]);
        $this->repository->update($readModel);
    }

    function handleUspehObnovleniyaUnitaUstanovlen(UspehObnovleniyaUnitaUstanovlen $fact): void
    {
        $readModel =$this->repository->getById($fact->unitId);
        $readModel = $readModel->copyAndUpdateData([
            'state' => $fact->stateAsArray['code'],
            'commands' => $fact->stateAsArray['commands'],
            'waitResultFromRunner' => false,
            'error' => false
        ]);
        $this->repository->update($readModel);
    }

    function handleSbrosPodgotovkiNachalsya(SbrosPodgotovkiNachalsya $fact): void
    {
        $readModel =$this->repository->getById($fact->unitId);
        $readModel = $readModel->copyAndUpdateData([
            'state' => $fact->stateAsArray['code'],
            'commands' => $fact->stateAsArray['commands'],
            'waitResultFromRunner' => true,
        ]);
        $this->repository->update($readModel);
    }

    function handleOshibkaSbrosaPodgotovkiUnitaUstanovlena(OshibkaSbrosaPodgotovkiUnitaUstanovlena $fact): void
    {
        $readModel =$this->repository->getById($fact->unitId);
        $readModel = $readModel->copyAndUpdateData([
            'state' => $fact->stateAsArray['code'],
            'commands' => $fact->stateAsArray['commands'],
            'waitResultFromRunner' => false,
            'jdemObnovlenieKodaPosleZapuska' => false,
            'error' => true
        ]);
        $this->repository->update($readModel);
    }

    function handleUspehSbrosaPodgotovkiUnitaUstanovlen(UspehSbrosaPodgotovkiUnitaUstanovlen $fact): void
    {
        $readModel =$this->repository->getById($fact->unitId);
        $readModel = $readModel->copyAndUpdateData([
            'state' => $fact->stateAsArray['code'],
            'commands' => $fact->stateAsArray['commands'],
            'waitResultFromRunner' => false,
            'error' => false
        ]);
        $this->repository->update($readModel);
    }

    function handleZapuskUnitNachalsya(ZapuskUnitNachalsya $fact): void
    {
        $readModel =$this->repository->getById($fact->unitId);
        $readModel = $readModel->copyAndUpdateData([
            'state' => $fact->stateAsArray['code'],
            'commands' => $fact->stateAsArray['commands'],
            'waitResultFromRunner' => true,
        ]);
        $this->repository->update($readModel);
    }

    function handleOshibkaZapuskaUnitaUstanovlena(OshibkaZapuskaUnitaUstanovlena $fact): void
    {
        $readModel =$this->repository->getById($fact->unitId);
        $readModel = $readModel->copyAndUpdateData([
            'state' => $fact->stateAsArray['code'],
            'commands' => $fact->stateAsArray['commands'],
            'waitResultFromRunner' => false,
            'jdemObnovlenieKodaPosleZapuska' => false,
            'error' => true
        ]);
        $this->repository->update($readModel);
    }

    function handleUspehZapuskaUnitaUstanovlen(UspehZapuskaUnitaUstanovlen $fact): void
    {
        $readModel =$this->repository->getById($fact->unitId);

        $readModel = $readModel->copyAndUpdateData([
            'state' => $fact->stateAsArray['code'],
            'commands' => $fact->stateAsArray['commands'],
            'waitResultFromRunner' => false,
            'jdemObnovlenieKodaPosleZapuska' => false,
            'error' => false
        ]);
        $this->repository->update($readModel);
    }

    function handleOstanovkaUnitaNachalas(OstanovkaUnitaNachalas $fact): void
    {
        $readModel =$this->repository->getById($fact->unitId);
        $readModel = $readModel->copyAndUpdateData([
            'state' => $fact->stateAsArray['code'],
            'commands' => $fact->stateAsArray['commands'],
            'waitResultFromRunner' => true,
        ]);
        $this->repository->update($readModel);
    }

    function handleOshibkaOstanovkiUnitaUstanovlena(OshibkaOstanovkiUnitaUstanovlena $fact): void
    {
        $readModel =$this->repository->getById($fact->unitId);
        $readModel = $readModel->copyAndUpdateData([
            'state' => $fact->stateAsArray['code'],
            'commands' => $fact->stateAsArray['commands'],
            'waitResultFromRunner' => false,
            'jdemObnovlenieKodaPosleZapuska' => false,
            'error' => true
        ]);
        $this->repository->update($readModel);
    }

    function handleUspehOstanovkiUnitaUstanovlen(UspehOstanovkiUnitaUstanovlen $fact): void
    {
        $readModel =$this->repository->getById($fact->unitId);
        $readModel = $readModel->copyAndUpdateData([
            'state' => $fact->stateAsArray['code'],
            'commands' => $fact->stateAsArray['commands'],
            'waitResultFromRunner' => false,
            'error' => false
        ]);
        $this->repository->update($readModel);
    }

    function handleUdalenieUnitaNachalos(UdalenieUnitaNachalos $fact): void
    {
        $readModel =$this->repository->getById($fact->unitId);
        $readModel = $readModel->copyAndUpdateData([
            'state' => $fact->stateAsArray['code'],
            'commands' => $fact->stateAsArray['commands'],
            'waitResultFromRunner' => true,
        ]);
        $this->repository->update($readModel);
    }

    function handleOshibkaUdaleniyaUnitaUstanovlena(OshibkaUdaleniyaUnitaUstanovlena $fact): void
    {
        $readModel =$this->repository->getById($fact->unitId);
        $readModel = $readModel->copyAndUpdateData([
            'state' => $fact->stateAsArray['code'],
            'commands' => $fact->stateAsArray['commands'],
            'waitResultFromRunner' => false,
            'error' => true
        ]);
        $this->repository->update($readModel);
    }

    function handleObnovlenieKodaUnitaPosleZapuskaNachalos(ObnovlenieKodaUnitaPosleZapuskaNachalos $fact): void
    {
        $readModel =$this->repository->getById($fact->unitId);
        $readModel = $readModel->copyAndUpdateData([
            'jdemObnovlenieKodaPosleZapuska' => true,
        ]);
        $this->repository->update($readModel);
    }

    function handleOshibkaObnovleniyaUnitaPosleZapuskaUstanovlena(OshibkaObnovleniyaUnitaPosleZapuskaUstanovlena $fact): void
    {
        $readModel =$this->repository->getById($fact->unitId);
        $readModel = $readModel->copyAndUpdateData([
            'jdemObnovlenieKodaPosleZapuska' => false,
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

    function init(): void
    {
        $this->repository->init();
    }

    function destroy(): void
    {
        $this->repository->destroy();
    }
}
