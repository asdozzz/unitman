<?php

namespace App\Unitman\Infra\Projection;

use App\Unitman\Business\Model\Project\Event\NastroikiHukaProektaUstanovleni;
use App\Unitman\Business\Model\Project\Event\OchistkaProektaNachalas;
use App\Unitman\Business\Model\Project\Event\OshibkaOchistkiProektaUstanovlena;
use App\Unitman\Business\Model\Project\Event\PeremenayaDobavlenaVProekt;
use App\Unitman\Business\Model\Project\Event\PeremenayaUdalenaIzProekta;
use App\Unitman\Business\Model\Project\Event\ProektPostavlenVOcheredNaUdalenie;
use App\Unitman\Business\Model\Project\Event\ProektPostavlenVOcheredNaSborku;
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
use App\Unitman\Business\Model\Project\ProjectVariableType;
use App\Unitman\Business\ReadModel\ProjectList;
use App\Unitman\Business\ReadModel\ProjectUsersList;
use App\Unitman\Business\Utils\UnitmanClassNameMapEnum;
use App\Unitman\Infra\Repository\Project\SqlProjectListRepository;
use App\Unitman\Infra\Repository\Unit\StatistikaPoProektuRepository;
use App\Utils\EventSauce\AbstractProjection;
use App\Utils\EventSauce\Model\StreamName;

final class ProjectListProjection extends AbstractProjection implements UnitmanProjection
{
    public function __construct(
        private SqlProjectListRepository $projectListRepository,
        private StatistikaPoProektuRepository $statistikaPoProektuRepository
    )
    {
    }

    function isAllowedRebuild(): bool
    {
        return true;
    }
    function isSyncProjection(): bool
    {
        return true;
    }
    function getProjectionName(): string
    {
        return 'project_list';
    }

    function reset(): void
    {
        $this->projectListRepository->truncate();
    }

    function resetById(string $id): void
    {
        $this->projectListRepository->resetById($id);
    }

    function getStreamName(): StreamName
    {
        return new StreamName(UnitmanClassNameMapEnum::Project->value);
    }

    function handleProjectWasAdded(ProjectWasAdded $fact): void
    {
        $projectList = new ProjectList(
            $fact->id,
            $fact->repoId,
            $fact->projectCode,
            $fact->projectName,
            $fact->mainBranch,
            false,
            ProjectList\ProjectListStateType::NEW,
            new ProjectList\NastroikiHukaProekta($fact->avtosozdanie, $fact->avtoobnovlenie, $fact->avtoudalenie),
            $fact->proxyHost
        );
        $this->projectListRepository->insert($projectList);
    }

    function handleNastroikiHukaProektaUstanovleni(NastroikiHukaProektaUstanovleni $fact): void
    {
        $this->projectListRepository->updateNastroikiHuka($fact->id,new ProjectList\NastroikiHukaProekta(
            $fact->avtosozdanie,
            $fact->avtoobnovlenie,
            $fact->avtoudalenie,
            $fact->obnovlenieBezSbrosaPodgotovki
        ));
    }

    function handleProektPostavlenVOcheredNaSborku(ProektPostavlenVOcheredNaSborku $fact): void
    {
        $this->projectListRepository->updateState($fact->id, ProjectList\ProjectListStateType::BUILD_PENDING);
    }

    function handleProjectWasBuilt(ProjectWasBuilt $fact): void
    {
        $this->projectListRepository->updateState($fact->id, ProjectList\ProjectListStateType::BUILD_SUCCESS, $fact->steps);
    }

    function handleProjectWasNotBuilt(ProjectWasNotBuilt $fact): void
    {
        $this->projectListRepository->updateState($fact->id, ProjectList\ProjectListStateType::BUILD_ERROR, $fact->steps);
    }

    function handleProjectWasEnabled(ProjectWasEnabled $fact): void
    {
        $this->projectListRepository->updateActive($fact->id, true);
    }

    function handleProjectWasDisabled(ProjectWasDisabled $fact): void
    {
        $this->projectListRepository->updateActive($fact->id, false);
    }

    function handleProektPostavlenVOcheredNaUdalenie(ProektPostavlenVOcheredNaUdalenie $fact): void
    {
        $this->projectListRepository->updateState($fact->id, ProjectList\ProjectListStateType::REMOVE_PENDING);
    }

    function handleProjectWasDeleted(ProjectWasDeleted $fact): void
    {
        $this->projectListRepository->delete($fact->id);
        $this->statistikaPoProektuRepository->removeById($fact->id);
    }

    function handleProjectWasNotDeleted(ProjectWasNotDeleted $fact): void
    {
        $this->projectListRepository->handleProjectWasNotDeleted($fact);
    }

    function handleProjectWasDeletedManually(ProjectWasDeletedManually $fact): void
    {
        $this->projectListRepository->delete($fact->id);
        $this->statistikaPoProektuRepository->removeById($fact->id);
    }

    function handleProjectDataWasChanged(ProjectDataWasChanged $fact): void
    {
        $this->projectListRepository->handleProjectDataWasChanged($fact);
    }

    function handleUserAddedToProject(UserAddedToProject $fact): void
    {
        $project = $this->projectListRepository->getById($fact->projectId);
        $project->users[] = new ProjectUsersList($fact->userId, $fact->role);
        $this->projectListRepository->update($project);
    }

    function handleUserRemovedFromProject(UserRemovedFromProject $fact): void
    {
        $project = $this->projectListRepository->getById($fact->id);

        $users = $project->users;
        $project->users = [];
        foreach ($users as $user) {
            if ($user->userId == $fact->userId) continue;
            $project->users[] = $user;
        }
        $this->projectListRepository->update($project);
    }

    function handlePeremenayaDobavlenaVProekt(PeremenayaDobavlenaVProekt $fact): void
    {
        $project = $this->projectListRepository->getById($fact->projectId);
        $value = $fact->tip === ProjectVariableType::hidden->value ? '': $fact->value;
        $project->variables[] = new ProjectList\ProjectListVariable($fact->tip, $fact->code, $value);
        $this->projectListRepository->update($project);
    }

    function handlePeremenayaUdalenaIzProekta(PeremenayaUdalenaIzProekta $fact): void
    {
        $project = $this->projectListRepository->getById($fact->projectId);

        $variables = $project->variables;

        $project->variables = [];
        foreach ($variables as $variable) {
            if ($variable->code == $fact->code) continue;
            $project->variables[] = $variable;
        }
        $this->projectListRepository->update($project);
    }

    function handleZnacheniePeremnoiProektaIzmeneno(ZnacheniePeremnoiProektaIzmeneno $fact): void
    {
        $project = $this->projectListRepository->getById($fact->projectId);

        $variables = $project->variables;
        $project->variables = [];
        foreach ($variables as $variable) {
            if ($variable->code == $fact->code) {
                $project->variables[] = new ProjectList\ProjectListVariable($variable->tip, $variable->code, $fact->newValue);
            } else {
                $project->variables[] = $variable;
            }

        }
        $this->projectListRepository->update($project);
    }

    function handleOchistkaProektaNachalas(OchistkaProektaNachalas $fact): void
    {
        $project = $this->projectListRepository->getById($fact->id);
        $project->waitResultRunner = true;
        $this->projectListRepository->update($project);
    }

    function handleOshibkaOchistkiProektaUstanovlena(OshibkaOchistkiProektaUstanovlena $fact): void
    {
        $project = $this->projectListRepository->getById($fact->id);
        $project->waitResultRunner = false;
        $this->projectListRepository->update($project);
    }

    function handleUspehOchistkiProektaUstanovlen(UspehOchistkiProektaUstanovlen $fact): void
    {
        $project = $this->projectListRepository->getById($fact->id);
        $project->waitResultRunner = false;
        $this->projectListRepository->update($project);
    }

    function init(): void
    {
        $this->projectListRepository->init();
    }

    function destroy(): void
    {
        $this->projectListRepository->destroy();
    }
}
