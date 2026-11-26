<?php

namespace App\Unitman\Business\Model;

use App\Unitman\Business\Model\Repo\Event\AccessToRepoConfirmed;
use App\Unitman\Business\Model\Repo\Event\CredentialsOfRepoWasChanged;
use App\Unitman\Business\Model\Repo\Event\RepoWasAdded;
use App\Unitman\Business\Model\Repo\Event\RepoWasDeleted;
use App\Unitman\Business\Model\Repo\RepoCredentials;
use App\Unitman\Business\Model\Repo\RepoId;
use App\Unitman\Business\Model\Repo\RepoName;
use App\Unitman\Business\Model\Repo\RepoType;
use App\Unitman\Business\Port\UmeetShifrovatSekrety;
use EventSauce\EventSourcing\AggregateRoot;
use EventSauce\EventSourcing\AggregateRootBehaviour;

/**
 * Агрегат репозитория.
 *
 * Секрет доступа никогда не хранится в открытом виде: только шифротекст
 * (`tokenCipher`, AAD = repoId) и необратимый `tokenHint`. Открытый токен
 * доступен исключительно по запросу через {@see self::rasshifrovatToken()}
 * для исходящих операций и не участвует в сериализации событий.
 *
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

    public static function addRepo(
        string $id,
        string $repoType,
        string $repoName,
        string $url,
        string $tokenCipher,
        string $tokenHint,
    ): static
    {
        $repoId = RepoId::fromString($id);
        $repo = new static($repoId);
        $repo->recordThat(new RepoWasAdded(
            $repoId->toString(),
            $repoType,
            $repoName,
            $url,
            $tokenCipher,
            $tokenHint,
        ));
        return $repo;
    }

    private function applyRepoWasAdded(RepoWasAdded $fact): void
    {
        $this->type = RepoType::from($fact->repoType);
        $this->name = new RepoName($fact->repoName);

        $this->credentials = new RepoCredentials($fact->repoUrl, $fact->tokenCipher, $fact->tokenHint);
    }

    public function changeCredentials(string $url, string $tokenCipher, string $tokenHint): void
    {
        if ($this->isDeleted) {
            throw new \DomainException('repo.deleted');
        }

        $this->recordThat(new CredentialsOfRepoWasChanged($this->getId(), $url, $tokenCipher, $tokenHint));
    }

    private function applyCredentialsOfRepoWasChanged(CredentialsOfRepoWasChanged $fact): void
    {
        $this->credentials = new RepoCredentials($fact->repoUrl, $fact->tokenCipher, $fact->tokenHint);
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

    public function getType(): RepoType
    {
        return $this->type;
    }

    public function getCredentials(): RepoCredentials
    {
        return $this->credentials;
    }

    public function getTokenCipher(): string
    {
        return $this->credentials->getTokenCipher();
    }

    public function getTokenHint(): string
    {
        return $this->credentials->getTokenHint();
    }

    /**
     * Единственный санкционированный путь получения открытого токена.
     *
     * Используется только исходящими операциями (API хранилищ, клонирование).
     * Никогда не передавайте результат в логи, исключения, DTO или события.
     *
     * @throws \DomainException если шифротекст повреждён/подменён или ключ не
     *                          настроен — открытый токен при этом не
     *                          подставляется (fail closed).
     */
    public function rasshifrovatToken(UmeetShifrovatSekrety $shifr): string
    {
        return $shifr->rasshifrovat($this->getTokenCipher(), $this->getId());
    }
}
