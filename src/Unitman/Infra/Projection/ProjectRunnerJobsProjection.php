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
use App\Unitman\Business\ReadModel\Project\ProjectRunnerJob;
use App\Unitman\Business\Utils\UnitmanClassNameMapEnum;
use App\Unitman\Infra\Repository\Project\ProjectRunnerJobRepository;
use App\Utils\EventSauce\AbstractProjection;
use App\Utils\EventSauce\Model\StreamName;

final class ProjectRunnerJobsProjection extends AbstractProjection implements UnitmanProjection
{
    const PROJECTION_NAME = 'project_runner_jobs';

    public function __construct(private ProjectRunnerJobRepository $repository)
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

    function resetById(string $id): void
    {
        $this->repository->resetById($id);
    }

    function getStreamName(): StreamName
    {
        return new StreamName(UnitmanClassNameMapEnum::Project->value);
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
            ZnacheniePeremnoiProektaIzmeneno::class,
            ProektPostavlenVOcheredNaSborku::class,
            OchistkaProektaNachalas::class,
            ProektPostavlenVOcheredNaUdalenie::class,
            ProjectWasDeletedManually::class
        ];
    }

    public function handleProjectWasNotBuilt(ProjectWasNotBuilt $fact): void
    {
        $job = new ProjectRunnerJob('stub', $fact->id, ProjectRunnerJob::SBORKA_PROEKTA, false, $fact->steps);
        $this->repository->insert($job);
    }

    public function handleProjectWasBuilt(ProjectWasBuilt $fact): void
    {
        $job = new ProjectRunnerJob('stub', $fact->id, ProjectRunnerJob::SBORKA_PROEKTA, true, $fact->steps);
        $this->repository->insert($job);
    }

    public function handleOshibkaOchistkiProektaUstanovlena(OshibkaOchistkiProektaUstanovlena $fact): void
    {
        $job = new ProjectRunnerJob('stub', $fact->id, ProjectRunnerJob::OCHISTKA_PROEKTA, false, $fact->steps);
        $this->repository->insert($job);
    }

    public function handleUspehOchistkiProektaUstanovlen(UspehOchistkiProektaUstanovlen $fact): void
    {
        $job = new ProjectRunnerJob('stub', $fact->id, ProjectRunnerJob::OCHISTKA_PROEKTA, true, $fact->steps);
        $this->repository->insert($job);
    }

    public function handleProjectWasNotDeleted(ProjectWasNotDeleted $fact): void
    {
        $job = new ProjectRunnerJob('stub', $fact->id, ProjectRunnerJob::UDALENIE_PROEKTA, false, $fact->steps);
        $this->repository->insert($job);
    }

    public function handleProjectWasDeleted(ProjectWasDeleted $fact): void
    {
        $job = new ProjectRunnerJob('stub', $fact->id, ProjectRunnerJob::UDALENIE_PROEKTA, true, $fact->steps);
        $this->repository->insert($job);
    }
}
