<?php

namespace App\Unitman\Infra\Projection;

use App\Unitman\Business\Model\Repo\Event\AccessToRepoConfirmed;
use App\Unitman\Business\Model\Repo\Event\CredentialsOfRepoWasChanged;
use App\Unitman\Business\Model\Repo\Event\RepoWasAdded;
use App\Unitman\Business\Model\Repo\Event\RepoWasDeleted;
use App\Unitman\Business\ReadModel\RepoList;
use App\Unitman\Infra\Repository\RepoListRepository;
use App\Utils\EventSauce\AbstractProjection;
use EventSauce\EventSourcing\Message;

final class RepoListProjection extends AbstractProjection implements SyncProjectionForRepo
{
    public function __construct(
        private RepoListRepository $repoListRepository
    )
    {
    }

    public function handleRepoWasAdded(RepoWasAdded $event)
    {
        $this->repoListRepository->insert(new RepoList(
            $event->repoId,
            $event->repoType,
            $event->repoName,
            $event->repoUrl,
            $event->repoLogin,
            $event->repoPassword,
            false
        ));
    }

    public function handleRepoWasDeleted(RepoWasDeleted $event)
    {
        $this->repoListRepository->delete($event->repoId);
    }

    public function handleCredentialsOfRepoWasChanged(CredentialsOfRepoWasChanged $event)
    {
        $repo = $this->repoListRepository->getById($event->repoId);
        $repo->changeCredentials($event->repoUrl, $event->repoLogin, $event->repoPassword);
        $this->repoListRepository->update($repo);
    }

    public function handleAccessToRepoConfirmed(AccessToRepoConfirmed $event)
    {
        $repo = $this->repoListRepository->getById($event->repoId);
        $repo->confirmAccess();
        $this->repoListRepository->update($repo);
    }

}
