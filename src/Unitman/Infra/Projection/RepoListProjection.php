<?php

namespace App\Unitman\Infra\Projection;

use App\Unitman\Business\Model\Repo\Event\AccessToRepoConfirmed;
use App\Unitman\Business\Model\Repo\Event\CredentialsOfRepoWasChanged;
use App\Unitman\Business\Model\Repo\Event\RepoWasAdded;
use App\Unitman\Business\Model\Repo\Event\RepoWasDeleted;
use App\Unitman\Business\ReadModel\RepoList;
use App\Unitman\Infra\Repository\RepoListRepository;
use EventSauce\EventSourcing\Message;

final class RepoListProjection implements SyncProjectionForRepo
{
    public function __construct(
        private RepoListRepository $repoListRepository
    )
    {
    }

    public function RepoWasAdded(RepoWasAdded $event)
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

    public function RepoWasDeleted(RepoWasDeleted $event)
    {
        $this->repoListRepository->delete($event->repoId);
    }

    public function CredentialsOfRepoWasChanged(CredentialsOfRepoWasChanged $event)
    {
        $repo = $this->repoListRepository->getById($event->repoId);
        $repo->changeCredentials($event->repoUrl, $event->repoLogin, $event->repoPassword);
        $this->repoListRepository->update($repo);
    }

    public function AccessToRepoConfirmed(AccessToRepoConfirmed $event)
    {
        $repo = $this->repoListRepository->getById($event->repoId);
        $repo->confirmAccess();
        $this->repoListRepository->update($repo);
    }
    public function handle(Message $message): void
    {
        $event = $message->payload();

        if (method_exists($this, $event::class)) {
            $this->{$event::class}($event);
        } else {
            throw new \RuntimeException(sprintf('Handler for event=%s in %s not found', $event::class, __CLASS__));
        }
    }
}
