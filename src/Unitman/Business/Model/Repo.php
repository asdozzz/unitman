<?php

namespace App\Unitman\Business\Model;

use App\Unitman\Business\Command\Repo\AddRepo;
use App\Unitman\Business\Command\Repo\ChangeCredentialsOfRepo;
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

/**
 * @template-implements AggregateRoot<RepoId>
 * */
final class Repo implements AggregateRoot
{
    /**
     * @template-use AggregateRootBehaviour<RepoId>
     * */
    use AggregateRootBehaviour;

    /** @psalm-suppress PropertyNotSetInConstructor*/
    private RepoType $type;
    /** @psalm-suppress PropertyNotSetInConstructor*/
    private RepoName $name;
    /** @psalm-suppress PropertyNotSetInConstructor*/
    private RepoCredentials $credentials;
    private bool $isDeleted = false;
    private bool $accessConfirmed = false;

    public function getId(): string
    {
        return $this->aggregateRootId->toString();
    }
    public static function addRepo(string $id, AddRepo $command, string $url): static
    {
        $repoId = RepoId::fromString($id);
        $repo = new static($repoId);
        $repo->recordThat(new RepoWasAdded($repoId->toString(), $command->repoType, $command->repoName, $url, $command->token));
        return $repo;
    }

    private function applyRepoWasAdded(RepoWasAdded $fact): void
    {
        $this->type = RepoType::from($fact->repoType);
        $this->name = new RepoName($fact->repoName);

        $this->credentials  = new RepoCredentials($fact->repoUrl, $fact->token);
    }

    public function changeCredentials(ChangeCredentialsOfRepo $command, string $url): void
    {
        if ($this->isDeleted) {
            throw new \DomainException('repo.deleted');
        }

        $newCredentials = new RepoCredentials($url, $command->token);

        if ($newCredentials->getHash() == $this->credentials->getHash()) {
            throw new \DomainException('repo.new_credentials_equals_old');
        }

        $this->recordThat(new CredentialsOfRepoWasChanged($this->getId(), $url, $command->token));
    }

    private function applyCredentialsOfRepoWasChanged(CredentialsOfRepoWasChanged $fact): void
    {
        $this->credentials  = new RepoCredentials($fact->repoUrl, $fact->token);
        $this->accessConfirmed = false;
    }

    public function delete(): void
    {
        if ($this->isDeleted) {
            throw new \DomainException('repo.already_deleted');
        }

        $this->recordThat(new RepoWasDeleted($this->getId()));
    }

    private function applyRepoWasDeleted(RepoWasDeleted $fact): void
    {
        $this->isDeleted = true;
    }

    public function accessConfirm(): void
    {
        if ($this->isDeleted) {
            throw new \DomainException('repo.already_deleted');
        }

        $this->recordThat(new AccessToRepoConfirmed($this->getId()));
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

    public function getRepoUrlWithCredentials(): string
    {
        $url = $this->credentials->url;
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $urlWithoutScheme = preg_replace("/https?:\/\//misu", "", $url);
        $newUrl = $scheme.'://'.$this->credentials->token.'@'.$urlWithoutScheme;
        return $newUrl;
    }
}
