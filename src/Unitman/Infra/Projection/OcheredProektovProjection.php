<?php

namespace App\Unitman\Infra\Projection;

use App\Unitman\Business\Model\Project\Event\NastroikiHukaProektaUstanovleni;
use App\Unitman\Business\Model\Project\Event\OchistkaProektaNachalas;
use App\Unitman\Business\Model\Project\Event\OshibkaOchistkiProektaUstanovlena;
use App\Unitman\Business\Model\Project\Event\PeremenayaDobavlenaVProekt;
use App\Unitman\Business\Model\Project\Event\PeremenayaUdalenaIzProekta;
use App\Unitman\Business\Model\Project\Event\ProektPostavlenVOcheredNaSborku;
use App\Unitman\Business\Model\Project\Event\ProektPostavlenVOcheredNaUdalenie;
use App\Unitman\Business\Model\Project\Event\ProjectDataWasChanged;
use App\Unitman\Business\Model\Project\Event\ProjectWasAdded;
use App\Unitman\Business\Model\Project\Event\ProjectWasBuilt;
use App\Unitman\Business\Model\Project\Event\ProjectWasDeleted;
use App\Unitman\Business\Model\Project\Event\ProjectWasDeletedManually;
use App\Unitman\Business\Model\Project\Event\ProjectWasDisabled;
use App\Unitman\Business\Model\Project\Event\ProjectWasEnabled;
use App\Unitman\Business\Model\Project\Event\ProjectWasNotBuilt;
use App\Unitman\Business\Model\Project\Event\ProjectWasNotDeleted;
use App\Unitman\Business\Model\Project\Event\UserAddedToProject;
use App\Unitman\Business\Model\Project\Event\UserRemovedFromProject;
use App\Unitman\Business\Model\Project\Event\UspehOchistkiProektaUstanovlen;
use App\Unitman\Business\Model\Project\Event\ZnacheniePeremnoiProektaIzmeneno;
use App\Unitman\Business\ReadModel\Project\OcheredProektovReadModel;
use App\Unitman\Business\Utils\UnitmanClassNameMapEnum;
use App\Unitman\Infra\Repository\Project\OcheredProektovRepository;
use App\Utils\EventSauce\AbstractProjection;
use App\Utils\EventSauce\Model\StreamName;

final class OcheredProektovProjection extends AbstractProjection implements UnitmanProjection
{
    public function __construct(private OcheredProektovRepository $repository)
    {
    }

    function getProjectionName(): string
    {
        return 'ochered_proektov';
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
        $this->repository->resetById($id);
    }

    function getStreamName(): StreamName
    {
        return new StreamName(UnitmanClassNameMapEnum::Project->value);
    }

    function isSyncProjection(): bool
    {
        return true;
    }

    protected function getExceptionEvents(): array
    {
        return [
            NastroikiHukaProektaUstanovleni::class,
            PeremenayaDobavlenaVProekt::class,
            PeremenayaUdalenaIzProekta::class,
            ProjectDataWasChanged::class,
            ProjectWasAdded::class,
            ProjectWasDisabled::class,
            ProjectWasEnabled::class,
            UserAddedToProject::class,
            UserRemovedFromProject::class,
            ZnacheniePeremnoiProektaIzmeneno::class
        ];
    }

    public function handleProektPostavlenVOcheredNaSborku(ProektPostavlenVOcheredNaSborku $fact): void
    {
        $this->repository->insert($fact->id, OcheredProektovReadModel::SBORKA_PROEKTA);
    }

    public function handleProjectWasNotBuilt(ProjectWasNotBuilt $fact): void
    {
        $this->repository->removeByProjectId($fact->id);
    }

    public function handleProjectWasBuilt(ProjectWasBuilt $fact): void
    {
        $this->repository->removeByProjectId($fact->id);
    }

    public function handleOchistkaProektaNachalas(OchistkaProektaNachalas $fact): void
    {
        $this->repository->insert($fact->id, OcheredProektovReadModel::OCHISTKA_PROEKTA);
    }

    public function handleOshibkaOchistkiProektaUstanovlena(OshibkaOchistkiProektaUstanovlena $fact): void
    {
        $this->repository->removeByProjectId($fact->id);
    }

    public function handleUspehOchistkiProektaUstanovlen(UspehOchistkiProektaUstanovlen $fact): void
    {
        $this->repository->removeByProjectId($fact->id);
    }

    public function handleProektPostavlenVOcheredNaUdalenie(ProektPostavlenVOcheredNaUdalenie $fact): void
    {
        $this->repository->insert($fact->id, OcheredProektovReadModel::UDALENIE_PROEKTA);
    }

    public function handleProjectWasNotDeleted(ProjectWasNotDeleted $fact): void
    {
        $this->repository->removeByProjectId($fact->id);
    }

    public function handleProjectWasDeleted(ProjectWasDeleted $fact): void
    {
        $this->repository->removeByProjectId($fact->id);
    }

    public function handleProjectWasDeletedManually(ProjectWasDeletedManually $fact): void
    {
        $this->repository->removeByProjectId($fact->id);
    }
}
