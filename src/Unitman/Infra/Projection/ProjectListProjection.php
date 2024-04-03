<?php

namespace App\Unitman\Infra\Projection;

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
use App\Unitman\Business\ReadModel\ProjectList;
use App\Unitman\Business\ReadModel\ProjectUsersList;
use App\Unitman\Business\Utils\UnitmanClassNameMapEnum;
use App\Unitman\Infra\Repository\Project\SqlProjectListRepository;
use App\Utils\EventSauce\AbstractProjection;
use App\Utils\EventSauce\Model\StreamName;

final class ProjectListProjection extends AbstractProjection implements UnitmanProjection
{
    public function __construct(
        private SqlProjectListRepository $projectListRepository,
    )
    {
    }

    function getProjectionName(): string
    {
        return 'project_list';
    }

    function reset(): void
    {
        $this->projectListRepository->truncate();
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
            null,
            null,
            $fact->proxyHost
        );
        $this->projectListRepository->insert($projectList);
    }

    function handleProektPostavlenVOcheredNaSborku(ProektPostavlenVOcheredNaSborku $fact): void
    {
        $this->projectListRepository->updateState($fact->id, ProjectList\ProjectListStateType::BUILD_PENDING);
    }

    function handleProjectWasBuilt(ProjectWasBuilt $fact): void
    {
        $this->projectListRepository->updateState($fact->id, ProjectList\ProjectListStateType::BUILD_SUCCESS, $fact->buildInfo);
    }

    function handleProjectWasNotBuilt(ProjectWasNotBuilt $fact): void
    {
        $this->projectListRepository->updateState($fact->id, ProjectList\ProjectListStateType::BUILD_ERROR, $fact->buildInfo);
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
    }

    function handleProjectWasNotDeleted(ProjectWasNotDeleted $fact): void
    {
        $this->projectListRepository->handleProjectWasNotDeleted($fact);
    }

    function handleProjectWasDeletedManually(ProjectWasDeletedManually $fact): void
    {
        $this->projectListRepository->delete($fact->id);
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

    function init(): void
    {
        $this->projectListRepository->init();
    }

    function destroy(): void
    {
        $this->projectListRepository->destroy();
    }
}
