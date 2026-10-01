<?php

namespace App\Utils\EventSauce;

use App\Unitman\Infra\Projection\ProzesUnitaProjection;
use App\Utils\EventSauce\Repository\DoctrineStreamRepository;
use App\Utils\EventSauce\Repository\CheckpointStore;
use App\Utils\Service\LockService;
use Doctrine\DBAL\Connection;
use EventSauce\EventSourcing\AggregateRoot;
use EventSauce\EventSourcing\AggregateRootId;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('utils.event_store.projections_manager')]
final class ProjectionsManager
{
    /**
     * @var iterable<int, CanProjectEvents>
     * */
    private iterable $projections;
    private Connection $connection;
    private CheckpointStore $checkpointStore;
    private DoctrineStreamRepository $eventsRepository;
    private string $appEnv;

    public function __construct(
        Connection               $connection,
        CheckpointStore          $checkpointStore,
        iterable                 $projections,
        DoctrineStreamRepository $eventsRepository,
        string $appEnv,
        private LockService $lockService
    )
    {
        $this->projections = $projections;
        $this->connection = $connection;
        $this->checkpointStore = $checkpointStore;
        $this->eventsRepository = $eventsRepository;
        $this->appEnv = $appEnv;
    }

    private function getProjectionByName(string $projectName): CanProjectEvents
    {
        $result = $this->findProjectionByName($projectName);

        if (empty($result)) {
            throw new \DomainException('utils.projection_not_found_by_name');
        }

        return $result;
    }

    /**
     * @template T of AggregateRoot
     * @param class-string<T> $aggregateClass
     *
     * @return T
     * */
    public function retrieve(string $aggregateClass, AggregateRootId $aggregateRootId)
    {
        return $this->eventsRepository->retrieve($aggregateClass, $aggregateRootId);
    }

    public function persistAndPullProjections(AggregateRoot $aggregateRoot): void
    {
        $this->connection->beginTransaction();

        try {

            $this->eventsRepository->persist($aggregateRoot);
            $this->pullAllProjectionsByAggregateRoot($aggregateRoot);

            $this->connection->commit();
        } catch (\Exception $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    public function pullAllProjectionsByAggregateRoot(AggregateRoot $aggregateRoot): void
    {
        $streamName = $this->eventsRepository->getEventStreamByAggregateName($aggregateRoot::class);
        foreach ($this->projections as $projection) {
            if ($streamName->aggregateType !== $projection->getStreamName()->aggregateType) continue;
            if (!$projection->isSyncProjection() && $this->appEnv != 'test') continue;
            $checkpoint = $this->checkpointStore->getCheckpoint($projection->getProjectionName());
            if ($checkpoint === 0) {
                $projection->init();
            }

            $this->handleEventsByCheckpoint($projection, $checkpoint, 200);
        }
    }

    public function pullProjectionByName(string $projectionName): void
    {
        $lockFactory = $this->lockService->makeLockFactory();
        $lock = $lockFactory->createLock('pullProjectionByName.'.$projectionName);

        if (!$lock->acquire()) {
            return;
        }

        $this->connection->beginTransaction();
        try {
            $projection = $this->getProjectionByName($projectionName);
            $checkpoint = $this->checkpointStore->getCheckpoint($projectionName);

            if ($checkpoint === 0) {
                $projection->init();
            }

            $this->handleEventsByCheckpoint($projection, $checkpoint, 200);

            $this->connection->commit();
        } catch (\Exception $e) {
            $this->connection->rollBack();
            throw $e;
        } finally {
            $lock->release();
        }
    }

    public function rebuildAll(): void
    {
        $this->connection->beginTransaction();
        try {
            foreach ($this->projections as $projection) {
                if (!$projection->isAllowedRebuild()) return;
                $this->checkpointStore->resetCheckpoint($projection->getProjectionName());
                $projection->init();
                $projection->reset();
                $this->handleEventsByCheckpoint($projection, 0);
            }

            $this->connection->commit();
        } catch (\Exception $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    public function rebuildById(string $projectionName, string $id): void
    {
        $projection = $this->getProjectionByName($projectionName);

        if (!$projection->isAllowedRebuild()) {
            throw new \DomainException('projections_manager.rebuild_not_allowed');
        }

        $this->connection->beginTransaction();
        try {
            $projection->resetById($id);
            $events = $this->eventsRepository->getStreamById($projection->getStreamName(), $id);

            foreach ($events as $event) {
                $projection->handle($event);
            }
            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    public function rebuild(string $projectionName, int $disableReset = 0): void
    {
        $projection = $this->getProjectionByName($projectionName);

        if (!$projection->isAllowedRebuild()) {
            throw new \DomainException('projections_manager.rebuild_not_allowed');
        }

        if ($disableReset !== 1) {
            $this->checkpointStore->resetCheckpoint($projectionName);
            $projection->init();
            $projection->reset();
        }

        $deep = 100;
        $cnt = 0;
        while (true) {
            $oldCheckpoint = $this->checkpointStore->getCheckpoint($projectionName);

            $this->connection->beginTransaction();
            try {
                $this->handleEventsByCheckpoint($projection, $oldCheckpoint, 5000);
                $this->connection->commit();
            } catch (\Exception $e) {
                $this->connection->rollBack();
                throw $e;
            }

            $newCheckpoint = $this->checkpointStore->getCheckpoint($projectionName);

            if ($newCheckpoint === $oldCheckpoint || $cnt >= $deep) {
                break;
            }

            sleep(3);
            $cnt++;
        }



    }

    /**
     * @param CanProjectEvents $projection
     * @param int $checkpoint
     * @return void
     */
    private function handleEventsByCheckpoint(CanProjectEvents $projection, int $checkpoint, int $limit = 0): void
    {
        $events = $this->eventsRepository->getStream($projection->getStreamName(), $checkpoint, $limit);

        $oldCheckpoint = $checkpoint;

        foreach ($events as $event) {
            try {
                $projection->handle($event);
            } catch (\Throwable) {}

            $checkpoint++;
        }

        if ($oldCheckpoint == $checkpoint) {
            return;
        }

        $this->checkpointStore->saveCheckpoint($projection->getProjectionName(), $checkpoint);
    }

    /**
     * @param string $projectName
     * @return CanProjectEvents|null
     */
    private function findProjectionByName(string $projectName): ?CanProjectEvents
    {
        $result = null;

        foreach ($this->projections as $projection) {
            if ($projection->getProjectionName() === $projectName) {
                $result = $projection;
            }
        }
        return $result;
    }

    function isExistProjection(string $projectName): bool
    {
        $result = $this->findProjectionByName($projectName);

        return !empty($result);
    }
}
