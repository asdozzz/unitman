<?php

namespace App\Unitman\Infra\Repository;

use App\Unitman\Business\Model\Repo;
use Doctrine\DBAL\Connection;
use EventSauce\EventSourcing\EventSourcedAggregateRootRepository;
use EventSauce\EventSourcing\MessageDecorator;
use EventSauce\EventSourcing\MessageDispatcher;
use EventSauce\EventSourcing\MessageRepository;

final class SqlRepoRepository implements \App\Unitman\Business\Port\RepoRepository
{
    private Connection $connection;

    public function __construct(Connection $connection, MessageRepository $messageRepository, MessageDispatcher $messageDispatcher, MessageDecorator $messageDecorator)
    {
        $this->esRepository = new EventSourcedAggregateRootRepository(
            Repo::class,
            $messageRepository,
            $messageDispatcher,
            $messageDecorator
        );
        $this->connection = $connection;
    }
    public function getById(string $repoId): Repo
    {
        $repo = $this->esRepository->retrieve(Repo\RepoId::fromString($repoId));
        /** @var Repo $repo*/
        if ($repo->aggregateRootVersion() === 0) {
            throw new \DomainException('repo.not_found');
        }
        return $repo;
    }

    public function save(Repo $repo): void
    {
        $this->connection->transactional(fn() => $this->esRepository->persist($repo));
    }
}
