<?php

namespace App\Unitman\Business\Model;

use App\Unitman\Business\Command\AddRepo;
use App\Unitman\Business\Command\ChangeCredentialsOfRepo;
use App\Unitman\Business\Model\Repo\Event\AccessToRepoConfirmed;
use App\Unitman\Business\Model\Repo\Event\CredentialsOfRepoWasChanged;
use App\Unitman\Business\Model\Repo\Event\RepoWasAdded;
use App\Unitman\Business\Model\Repo\Event\RepoWasDeleted;
use App\Unitman\Business\Model\Repo\RepoCredentials;
use App\Unitman\Business\Model\Repo\RepoId;
use App\Unitman\Business\Model\Repo\RepoName;
use App\Unitman\Business\Model\Repo\RepoType;
use EventSauce\EventSourcing\AggregateRoot;
use EventSauce\EventSourcing\AggregateRootBehaviour;

final class Repo implements AggregateRoot
{
    /**
     * @template-use AggregateRootBehaviour<AccountId>
     * */
    use AggregateRootBehaviour;

    private ?RepoId $id;
    private ?RepoType $type;
    private ?RepoName $name;
    private ?RepoCredentials $credentials;
    private bool $isDeleted = false;
    private bool $accessConfirmed = false;
    public static function addRepo(string $id, AddRepo $command): static
    {
        $repoId = RepoId::fromString($id);
        $repo = new static($repoId);
        $repo->recordThat(new RepoWasAdded($repoId->toString(), $command->repoType, $command->repoName, $command->repoUrl, $command->repoLogin, $command->repoPassword));
        return $repo;
    }

    private function applyRepoWasAdded(RepoWasAdded $fact): void
    {
        $this->type = RepoType::from($fact->repoType);
        $this->name = new RepoName($fact->repoName);
        $this->credentials  = new RepoCredentials($fact->repoUrl, $fact->repoLogin, $fact->repoPassword);
    }

    public function changeCredentials(ChangeCredentialsOfRepo $command): void
    {
        if ($this->isDeleted) {
            throw new \DomainException('repo.deleted');
        }

        $newCredentials = new RepoCredentials($command->repoUrl, $command->repoLogin, $command->repoPassword);

        if ($newCredentials->getHash() == $this->credentials->getHash()) {
            throw new \DomainException('repo.new_credentials_equals_old');
        }

        $this->recordThat(new CredentialsOfRepoWasChanged($this->id->toString(), $command->repoUrl, $command->repoLogin, $command->repoPassword));
    }

    private function applyChangeCredentialsOfRepo(ChangeCredentialsOfRepo $fact): void
    {
        $this->credentials  = new RepoCredentials($fact->repoUrl, $fact->repoLogin, $fact->repoPassword);
        $this->accessConfirmed = false;
    }

    public function delete(): void
    {
        if ($this->isDeleted) {
            throw new \DomainException('repo.already_deleted');
        }

        $this->recordThat(new RepoWasDeleted($this->id->toString()));
    }

    private function applyRepoWasDeleted(RepoWasDeleted $fact): void
    {
        $this->isDeleted = true;
    }

    public function accessConfirm()
    {
        if ($this->isDeleted) {
            throw new \DomainException('repo.already_deleted');
        }

        $this->recordThat(new AccessToRepoConfirmed($this->id->toString()));
    }

    private function applyAccessToRepoConfirmed(AccessToRepoConfirmed $fact): void
    {
        $this->accessConfirmed = true;
    }

    /**
     * @return RepoType
     */
    public function getType(): RepoType
    {
        return $this->type;
    }

    public function getCredentials(): RepoCredentials
    {
        return $this->credentials;
    }
}
