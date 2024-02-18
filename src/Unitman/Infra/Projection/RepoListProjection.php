<?php

namespace App\Unitman\Infra\Projection;

use App\Unitman\Business\Model\Repo\Event\AccessToRepoConfirmed;
use App\Unitman\Business\Model\Repo\Event\CredentialsOfRepoWasChanged;
use App\Unitman\Business\Model\Repo\Event\RepoWasAdded;
use App\Unitman\Business\Model\Repo\Event\RepoWasDeleted;
use App\Unitman\Business\ReadModel\RepoList;
use App\Unitman\Infra\Repository\Repo\RepoListRepository;
use App\Utils\EventSauce\AbstractProjection;

final class RepoListProjection extends AbstractProjection implements SyncProjectionForRepo
{
    public function __construct(
        private RepoListRepository $repoListRepository
    )
    {
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
