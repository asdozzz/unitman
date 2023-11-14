<?php

namespace App\Unitman\Infra\Projection;

use App\Unitman\Business\Model\Project\Event\PostavlenVOcheredNaUdalenie;
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
use App\Unitman\Infra\Repository\SqlProjectListRepository;
use App\Unitman\Infra\Repository\SqlProjectUsersRepository;
use App\Utils\EventSauce\AbstractProjection;

final class ProjectListProjection extends AbstractProjection implements SyncProjectionForProject
{
    public function __construct(
        private SqlProjectListRepository $projectListRepository,
        private SqlProjectUsersRepository $projectUsersRepository
    )
    {
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
            null
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

    function handlePostavlenVOcheredNaUdalenie(PostavlenVOcheredNaUdalenie $fact): void
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
        $this->projectUsersRepository->insert(new ProjectUsersList($fact->projectId, $fact->userId, $fact->role));
    }

    function handleUserRemovedFromProject(UserRemovedFromProject $fact): void
    {
        $this->projectUsersRepository->delete($fact->id, $fact->userId);
    }
}
