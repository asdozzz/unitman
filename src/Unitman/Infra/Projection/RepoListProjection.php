<?php

namespace App\Unitman\Infra\Projection;

use App\Unitman\Business\Model\Repo\Event\AccessToRepoConfirmed;
use App\Unitman\Business\Model\Repo\Event\CredentialsOfRepoWasChanged;
use App\Unitman\Business\Model\Repo\Event\RepoWasAdded;
use App\Unitman\Business\Model\Repo\Event\RepoWasDeleted;
use App\Unitman\Business\ReadModel\RepoList;
use App\Unitman\Business\Utils\UnitmanClassNameMapEnum;
use App\Unitman\Infra\Repository\Repo\RepoListRepository;
use App\Utils\EventSauce\AbstractProjection;
use App\Utils\EventSauce\Model\StreamName;

final class RepoListProjection extends AbstractProjection implements UnitmanProjection
{
    public function __construct(
        private RepoListRepository $repoListRepository
    )
    {
    }

    function isSyncProjection(): bool
    {
        return true;
    }
    function init(): void
    {
       $this->repoListRepository->init();
    }

    function destroy(): void
    {
        $this->repoListRepository->destroy();
    }

    function getProjectionName(): string
    {
        return 'repo_list';
    }

    function reset(): void
    {
        $this->repoListRepository->truncate();
    }

    function getStreamName(): StreamName
    {
        return new StreamName(UnitmanClassNameMapEnum::Repo->value);
    }

    public function handleRepoWasAdded(RepoWasAdded $event): void
    {
        $this->repoListRepository->insert(new RepoList(
            $event->repoId,
            $event->repoType,
            $event->repoName,
            $event->repoUrl,
            $event->token,
            false
        ));
    }

    public function handleRepoWasDeleted(RepoWasDeleted $event): void
    {
        $this->repoListRepository->delete($event->repoId);
    }

    public function handleCredentialsOfRepoWasChanged(CredentialsOfRepoWasChanged $event): void
    {
        $repo = $this->repoListRepository->getById($event->repoId);
        $repo->changeCredentials($event->repoUrl, $event->token);
        $this->repoListRepository->update($repo);
    }

    public function handleAccessToRepoConfirmed(AccessToRepoConfirmed $event): void
    {
        $repo = $this->repoListRepository->getById($event->repoId);
        $repo->confirmAccess();
        $this->repoListRepository->update($repo);
    }

}
