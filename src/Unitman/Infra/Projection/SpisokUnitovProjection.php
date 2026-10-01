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
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Unitman\Business\ReadModel\Unit\ProjectListContainerStats;
use App\Unitman\Business\ReadModel\Unit\SpisokUnitovReadModel;
use App\Unitman\Business\Utils\UnitmanClassNameMapEnum;
use App\Unitman\Infra\Repository\Unit\SpisokUnitovRepository;
use App\Unitman\Infra\Repository\Unit\SqlUnitEventsRepository;
use App\Utils\EventSauce\AbstractProjection;
use App\Utils\EventSauce\Model\StreamName;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

final class SpisokUnitovProjection extends AbstractProjection implements UnitmanProjection
{
    const CODE = 'spisok_unitov';

    public function __construct(
        private SpisokUnitovRepository $repository,
        private ProjectRepository $projectRepository,
        private UnitmanSecurityService $securityService,
    )
    {
    }

    public function getPriority(): int
    {
        return 1000;
    }


    function isSyncProjection(): bool
    {
        return true;
    }
    function getProjectionName(): string
    {
        return self::CODE;
    }

    function reset(): void
    {
        $this->repository->truncate();
    }

    function resetById(string $id): void
    {
        $this->repository->resetById($id);
    }

    function getStreamName(): StreamName
    {
        return new StreamName(UnitmanClassNameMapEnum::Unit->value);
    }

    protected function getExceptionEvents(): array
    {
        return [
            StatistikaPoKonteineruObnovlena::class,
        ];
    }

    function handleDobavlenProzesVUnit(DobavlenProzesVUnit $fact): void
    {
        $readModel =$this->repository->getById($fact->unitId);
        $prozesi = [];
        $prozesi[] = $fact->prozes;
        $readModel = $readModel->copyAndUpdateData([
            'prozesi' => $prozesi
        ]);
        $this->repository->update($readModel);
    }

    function handleUnitSozdan(UnitSozdan $fact): void
    {
        $project = $this->projectRepository->getById($fact->projectId);
        $authorName = $this->securityService->getEmailOrNicknameByUserId($fact->authorId);

        $peremenie = [];

        foreach ($fact->values as $v) {
            $peremenie[] = ['id' => $v['id'], 'value' => $v['value']];
        }

        $readModel = new SpisokUnitovReadModel(
            $fact->id,
            $fact->authorId,
            $authorName,
            $fact->name,
            $fact->projectId,
            $project->getName(),
            $fact->branch,
            commands: $fact->stateAsArray['commands'],
            prozesi:[],
            peremenie: $peremenie
        );
        $this->repository->insert($readModel);
    }

    function handleUnitSozdanSystemoi(UnitSozdanSystemoi $fact): void
    {
        $project = $this->projectRepository->getById($fact->projectId);
        $authorName = $this->securityService->getEmailOrNicknameByUserId($fact->authorId);
        $readModel = new SpisokUnitovReadModel(
            $fact->id,
            $fact->authorId,
            $authorName,
            $fact->name,
            $fact->projectId,
            $project->getName(),
            $fact->branch,
            $fact->stateAsArray['commands'],
            prozesi:[],
            unitSozdanSystemoi: true
        );

        $this->repository->insert($readModel);
    }

    function handleSborkaUnitNachalas(SborkaUnitNachalas $fact): void
    {
        $readModel =$this->repository->getById($fact->unitId);
        $readModel = $readModel->copyAndUpdateData([
            'commands' => $fact->stateAsArray['commands'],
            'unixtimePoslednegoObnovleniyaUnita' => $fact->unixtime,
            'prozes' => $fact->prozess
        ]);
        $this->repository->update($readModel);
    }

    function handleOshibkaSborkiUnitaUstanovlena(OshibkaSborkiUnitaUstanovlena $fact): void
    {
        $readModel =$this->repository->getById($fact->unitId);
        $readModel = $readModel->copyAndUpdateData([
            'commands' => $fact->stateAsArray['commands'],
            'error' => true,
            'prozes' => $fact->prozess
        ]);
        $this->repository->update($readModel);
    }

    function handleDeistviePrikreplenoKJobe(DeistviePrikreplenoKJobe $fact): void
    {

    }

    function handleUspehSborkiUnitaUstanovlen(UspehSborkiUnitaUstanovlen $fact): void
    {
        $readModel =$this->repository->getById($fact->unitId);

        $readModel = $readModel->copyAndUpdateData([
            'commands' => $fact->stateAsArray['commands'],
            'error' => false,
            'prozes' => $fact->prozess
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

            foreach ($fact->configUnita['services'] as $name => $service) {
                if (!isset($service['ports'])) continue;
                foreach ($service['ports'] as $portData) {
                    if ($portData['type'] == 'http') {
                        $link = $pathinfo['scheme'].'://'.$portData['port'].'.'.$readModel->name.'.'.$projectName.'.'.$pathinfo['host'];
                        if (!empty($pathinfo['port'])) {
                            $link .= ':'.$pathinfo['port'];
                        }
                    } else {
                        $link = $portData['type'].'://'.$readModel->name.'.'.$projectName.':'.$portData['port'];
                    }
                    if (!empty($portData['startUri'])) {
                        $link .= $portData['startUri'];
                    }
                    $links[] = [
                        'service' => $name,
                        'path' => $link,
                        'port' => $portData['port'],
                        'protocol' => $portData['type'],
                        'startUri' => $portData['startUri'] ?? null
                    ];
                }
            }
        }

        $deistviya = [];
        if (!empty($fact->configUnita['actions'])) {
            $deistviya = $fact->configUnita['actions'];
        }

        $readModel = $readModel->copyAndUpdateData([
            'links' => $links,
            'deistviya' => $deistviya
        ]);
        $this->repository->update($readModel);

    }

    function handlePeremenieUnitaZapolneni(PeremenieUnitaZapolneni $fact): void
    {
        $peremenie = [];

        foreach ($fact->values as $k => $v) {
            $peremenie[] = ['id' => $k, 'value' => $v];
        }

        $readModel =$this->repository->getById($fact->unitId);
        $readModel = $readModel->copyAndUpdateData([
            'commands' => $fact->stateAsArray['commands'],
            'peremenie' => $peremenie
        ]);
        $this->repository->update($readModel);
    }

    function handlePodgotovkaUnitaNachalas(PodgotovkaUnitaNachalas $fact): void
    {
        $readModel =$this->repository->getById($fact->unitId);
        $readModel = $readModel->copyAndUpdateData([
            'commands' => $fact->stateAsArray['commands'],
            'prozes' => $fact->prozess
        ]);
        $this->repository->update($readModel);
    }

    function handleOshibkaPodgotovkiUnitaUstanovlena(OshibkaPodgotovkiUnitaUstanovlena $fact): void
    {
        $readModel =$this->repository->getById($fact->unitId);
        $readModel = $readModel->copyAndUpdateData([
            'commands' => $fact->stateAsArray['commands'],
            'error' => true,
            'prozes' => $fact->prozess
        ]);
        $this->repository->update($readModel);
    }

    function handleUspehPodgotovkiUnitaUstanovlen(UspehPodgotovkiUnitaUstanovlen $fact): void
    {
        $readModel =$this->repository->getById($fact->unitId);
        $readModel = $readModel->copyAndUpdateData([
            'commands' => $fact->stateAsArray['commands'],
            'error' => false,
            'prozes' => $fact->prozess
        ]);
        $this->repository->update($readModel);
    }

    function handleObnovlenieUnitaNachalos(ObnovlenieUnitaNachalos $fact): void
    {
        $readModel =$this->repository->getById($fact->unitId);
        $readModel = $readModel->copyAndUpdateData([
            'commands' => $fact->stateAsArray['commands'],
            'unixtimePoslednegoObnovleniyaUnita' => $fact->unixtime,
            'prozes' => $fact->prozess
        ]);
        $this->repository->update($readModel);
    }

    function handleOshibkaObnovleniyaUnitaUstanovlena(OshibkaObnovleniyaUnitaUstanovlena $fact): void
    {

        $readModel =$this->repository->getById($fact->unitId);
        $readModel = $readModel->copyAndUpdateData([
            'commands' => $fact->stateAsArray['commands'],
            'error' => true,
            'prozes' => $fact->prozess
        ]);
        $this->repository->update($readModel);
    }

    function handleUspehObnovleniyaUnitaUstanovlen(UspehObnovleniyaUnitaUstanovlen $fact): void
    {
        $readModel =$this->repository->getById($fact->unitId);
        $readModel = $readModel->copyAndUpdateData([
            'commands' => $fact->stateAsArray['commands'],
            'error' => false,
            'prozes' => $fact->prozess
        ]);
        $this->repository->update($readModel);
    }

    function handleSbrosPodgotovkiNachalsya(SbrosPodgotovkiNachalsya $fact): void
    {
        $readModel =$this->repository->getById($fact->unitId);
        $readModel = $readModel->copyAndUpdateData([
            'commands' => $fact->stateAsArray['commands'],
            'prozes' => $fact->prozess
        ]);
        $this->repository->update($readModel);
    }

    function handleOshibkaSbrosaPodgotovkiUnitaUstanovlena(OshibkaSbrosaPodgotovkiUnitaUstanovlena $fact): void
    {
        $readModel =$this->repository->getById($fact->unitId);
        $readModel = $readModel->copyAndUpdateData([
            'commands' => $fact->stateAsArray['commands'],
            'error' => true,
            'prozes' => $fact->prozess
        ]);
        $this->repository->update($readModel);
    }

    function handleUspehSbrosaPodgotovkiUnitaUstanovlen(UspehSbrosaPodgotovkiUnitaUstanovlen $fact): void
    {
        $readModel =$this->repository->getById($fact->unitId);
        $readModel = $readModel->copyAndUpdateData([
            'commands' => $fact->stateAsArray['commands'],
            'error' => false,
            'prozes' => $fact->prozess
        ]);
        $this->repository->update($readModel);
    }

    function handleZapuskUnitNachalsya(ZapuskUnitNachalsya $fact): void
    {
        $readModel =$this->repository->getById($fact->unitId);
        $readModel = $readModel->copyAndUpdateData([
            'commands' => $fact->stateAsArray['commands'],
            'prozes' => $fact->prozess
        ]);
        $this->repository->update($readModel);
    }

    function handleOshibkaZapuskaUnitaUstanovlena(OshibkaZapuskaUnitaUstanovlena $fact): void
    {
        $readModel =$this->repository->getById($fact->unitId);
        $readModel = $readModel->copyAndUpdateData([
            'commands' => $fact->stateAsArray['commands'],
            'error' => true,
            'prozes' => $fact->prozess
        ]);
        $this->repository->update($readModel);
    }

    function handleUspehZapuskaUnitaUstanovlen(UspehZapuskaUnitaUstanovlen $fact): void
    {
        $readModel =$this->repository->getById($fact->unitId);
        $readModel = $readModel->copyAndUpdateData([
            'commands' => $fact->stateAsArray['commands'],
            'error' => false,
            'prozes' => $fact->prozess,
            'zapushen' => true
        ]);
        $this->repository->update($readModel);
    }

    function handleOstanovkaUnitaNachalas(OstanovkaUnitaNachalas $fact): void
    {
        $readModel =$this->repository->getById($fact->unitId);
        $readModel = $readModel->copyAndUpdateData([
            'commands' => $fact->stateAsArray['commands'],
            'prozes' => $fact->prozess
        ]);
        $this->repository->update($readModel);
    }

    function handleOshibkaOstanovkiUnitaUstanovlena(OshibkaOstanovkiUnitaUstanovlena $fact): void
    {
        $readModel =$this->repository->getById($fact->unitId);
        $readModel = $readModel->copyAndUpdateData([
            'commands' => $fact->stateAsArray['commands'],
            'prozes' => $fact->prozess,
            'error' => true
        ]);
        $this->repository->update($readModel);
    }

    function handleUspehOstanovkiUnitaUstanovlen(UspehOstanovkiUnitaUstanovlen $fact): void
    {
        $readModel =$this->repository->getById($fact->unitId);
        $readModel = $readModel->copyAndUpdateData([
            'commands' => $fact->stateAsArray['commands'],
            'error' => false,
            'prozes' => $fact->prozess,
            'zapushen' => false
        ]);
        $this->repository->update($readModel);
    }

    function handleUdalenieUnitaNachalos(UdalenieUnitaNachalos $fact): void
    {
        $readModel =$this->repository->getById($fact->unitId);
        $readModel = $readModel->copyAndUpdateData([
            'commands' => $fact->stateAsArray['commands'],
            'prozes' => $fact->prozess
        ]);
        $this->repository->update($readModel);
    }

    function handleOshibkaUdaleniyaUnitaUstanovlena(OshibkaUdaleniyaUnitaUstanovlena $fact): void
    {
        $readModel =$this->repository->getById($fact->unitId);
        $readModel = $readModel->copyAndUpdateData([
            'commands' => $fact->stateAsArray['commands'],
            'error' => true,
            'prozes' => $fact->prozess
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

    function handleKodVetkiIzmenilsyaVHranilishe(KodVetkiIzmenilsyaVHranilishe $fact): void
    {
        $readModel =$this->repository->getById($fact->unitId);
        $readModel = $readModel->copyAndUpdateData([
            'unixtimePoslednegoObnovleniyaVHranilishe' => $fact->unixtime,
        ]);
        $this->repository->update($readModel);
    }

    function handleVipolnenieDeistviyaNachalos(VipolnenieDeistviyaNachalos $fact): void
    {
        $readModel =$this->repository->getById($fact->unitId);
        $readModel = $readModel->copyAndUpdateData([
            'commands' => $fact->stateAsArray['commands'],
            'prozes' => $fact->prozess
        ]);
        $this->repository->update($readModel);
    }

    function handleUspehDeistviyaUstanovlen(UspehDeistviyaUstanovlen $fact): void
    {
        $readModel =$this->repository->getById($fact->unitId);
        $readModel = $readModel->copyAndUpdateData([
            'commands' => $fact->stateAsArray['commands'],
            'prozes' => $fact->prozess

        ]);
        $this->repository->update($readModel);
    }

    function handleOshibkaDeistviyaUstanovlena(OshibkaDeistviyaUstanovlena $fact): void
    {
        $readModel =$this->repository->getById($fact->unitId);
        $readModel = $readModel->copyAndUpdateData([
            'error' => true,
            'commands' => $fact->stateAsArray['commands'],
            'prozes' => $fact->prozess
        ]);
        $this->repository->update($readModel);
    }

    function handleUnitSbroshenDoSostoyaniyaSborki(UnitSbroshenDoSostoyaniyaSborki $fact): void
    {
        $readModel =$this->repository->getById($fact->unitId);
        $readModel = $readModel->copyAndUpdateData([
            'error' => false,
            'commands' => $fact->stateAsArray['commands'],
            'zapushen' => false
        ]);
        $this->repository->update($readModel);
    }

    function handleZadachaUnitaOtmenena(ZadachaUnitaOtmenena $fact): void
    {
        $readModel =$this->repository->getById($fact->unitId);
        $data = [
            'error' => true,
            'prozes' => $fact->prozess
        ];

        if ($fact->stateAsArray) {
            $data['commands'] = $fact->stateAsArray['commands'];
        }
        $readModel = $readModel->copyAndUpdateData($data);
        $this->repository->update($readModel);
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
