<?php

namespace App\Unitman\Infra\Repository\Project;

use App\Unitman\Business\Model\Project;
use App\Unitman\Business\Port\Project\ProjectRepository;
use Doctrine\DBAL\Connection;
use EventSauce\EventSourcing\EventSourcedAggregateRootRepository;
use EventSauce\EventSourcing\MessageDecorator;
use EventSauce\EventSourcing\MessageDispatcher;
use EventSauce\EventSourcing\MessageRepository;

final class SqlProjectEventsRepository implements ProjectRepository
{
    private Connection $connection;
    private EventSourcedAggregateRootRepository $esRepository;

    public function __construct(Connection $connection, MessageRepository $messageRepository, MessageDispatcher $messageDispatcher, MessageDecorator $messageDecorator)
    {
        $this->esRepository = new EventSourcedAggregateRootRepository(
            Project::class,
            $messageRepository,
            $messageDispatcher,
            $messageDecorator
        );
        $this->connection = $connection;
    }
    public function getById(string $id): Project
    {
        $repo = $this->esRepository->retrieve(Project\ProjectId::fromString($id));
        /** @var Project $repo*/
        if ($repo->aggregateRootVersion() === 0) {
            throw new \DomainException('project.not_found');
        }
        return $repo;
    }

    public function save(Project $project): void
    {
        $this->connection->transactional(fn() => $this->esRepository->persist($project));
    }
}
