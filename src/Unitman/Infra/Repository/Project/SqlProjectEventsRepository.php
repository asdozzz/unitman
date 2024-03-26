<?php

namespace App\Unitman\Infra\Repository\Project;

use App\Unitman\Business\Model\Project;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Utils\UnitmanClassNameMapEnum;
use App\Utils\EventSauce\Model\StreamName;
use App\Utils\EventSauce\ProjectionsManager;
use Doctrine\DBAL\Connection;
use EventSauce\EventSourcing\ClassNameInflector;
use EventSauce\EventSourcing\EventSourcedAggregateRootRepository;
use EventSauce\EventSourcing\MessageDecorator;
use EventSauce\EventSourcing\MessageDispatcher;
use EventSauce\EventSourcing\MessageRepository;

final class SqlProjectEventsRepository implements ProjectRepository
{
    public function __construct(
        private ProjectionsManager $projectionsManager
    )
    {}
    public function getById(string $id): Project
    {
        $repo = $this->projectionsManager->retrieve(Project::class, Project\ProjectId::fromString($id));
        /** @var Project $repo*/
        if ($repo->aggregateRootVersion() === 0) {
            throw new \DomainException('project.not_found');
        }
        return $repo;
    }

    public function save(Project $project): void
    {
        $this->projectionsManager->persistAndPullProjections($project);
    }
}
