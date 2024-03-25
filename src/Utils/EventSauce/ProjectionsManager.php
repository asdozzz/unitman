<?php

namespace App\Utils\EventSauce;

use App\Utils\EventSauce\Model\StreamName;
use App\Utils\EventSauce\Repository\DoctrineStreamRepository;
use App\Utils\EventSauce\Repository\CheckpointStore;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

final class ProjectionsManager
{
    /**
     * @var iterable<int, CanProjectEvents>
     * */
    private iterable $projections;
    private Connection $connection;
    private CheckpointStore $checkpointStore;
    private DoctrineStreamRepository $eventsRepository;

    public function __construct(
        Connection               $connection,
        CheckpointStore          $checkpointStore,
        iterable                 $projections,
        DoctrineStreamRepository $eventsRepository
    )
    {
        $this->projections = $projections;
        $this->connection = $connection;
        $this->checkpointStore = $checkpointStore;
        $this->eventsRepository = $eventsRepository;
    }

    private function getProjectionByName(string $projectName): CanProjectEvents
    {
        $result = null;

        foreach ($this->projections as $projection) {
            if ($projection->getProjectionName() === $projectName) {
                $result = $projection;
            }
        }

        if (empty($result)) {
            throw new \DomainException('utils.projection_not_found_by_name');
        }

        return $result;
    }

    public function pullAllProjectionsByStreamName(StreamName $streamName)
    {
        $this->connection->beginTransaction();
        try {
            foreach ($this->projections as $projection) {
                if ($streamName->aggregateType !== $projection->getStreamName()->aggregateType) continue;

                $checkpoint = $this->checkpointStore->getCheckpoint($projection->getProjectionName());
                $this->handleEventsByCheckpoint($projection, $checkpoint);
            }

            $this->connection->commit();
        } catch (\Exception $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    public function pullAllProjections()
    {
        $this->connection->beginTransaction();
        try {
            foreach ($this->projections as $projection) {
                $checkpoint = $this->checkpointStore->getCheckpoint($projection->getProjectionName());
                $this->handleEventsByCheckpoint($projection, $checkpoint);
            }

            $this->connection->commit();
        } catch (\Exception $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    public function rebuild(string $projectionName): void
    {

        $this->connection->beginTransaction();
        try {
            $projection = $this->getProjectionByName($projectionName);
            $this->checkpointStore->resetCheckpoint($projectionName);
            $projection->reset();

            $this->handleEventsByCheckpoint($projection, 0);

            $this->connection->commit();
        } catch (\Exception $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    /**
     * @param CanProjectEvents $projection
     * @param int $checkpoint
     * @return void
     */
    private function handleEventsByCheckpoint(CanProjectEvents $projection, int $checkpoint): void
    {
        $events = $this->eventsRepository->getStream($projection->getStreamName(), $checkpoint);

        foreach ($events as $event) {
            $projection->handle($event);
            $checkpoint++;
        }

        $this->checkpointStore->saveCheckpoint($projection->getProjectionName(), $checkpoint);
    }
}
