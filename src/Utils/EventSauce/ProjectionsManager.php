<?php

namespace App\Utils\EventSauce;

use App\Utils\EventSauce\Model\ProjectionPullResult;
use App\Utils\EventSauce\Repository\DoctrineStreamRepository;
use App\Utils\EventSauce\Repository\CheckpointStore;
use App\Utils\Service\LockService;
use Doctrine\DBAL\Connection;
use EventSauce\EventSourcing\AggregateRoot;
use EventSauce\EventSourcing\AggregateRootId;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Lock\Lock;

#[AutoconfigureTag('utils.event_store.projections_manager')]
final class ProjectionsManager
{
    /**
     * Размер выборки при догоняющем чтении потока. Ограничивает память на
     * один запрос, но НЕ суммарный объём работы за вызов: pull-цикл повторяет
     * выборку до исчерпания потока.
     */
    private const PULL_BATCH_SIZE = 200;

    /** Размер выборки на один проход полной пересборки проекции. */
    private const REBUILD_BATCH_SIZE = 5000;

    /** Пауза между проходами пересборки, секунды. */
    private const REBUILD_PAUSE_SECONDS = 3;

    /** Страховка от бесконечного цикла пересборки. */
    private const REBUILD_MAX_PASSES = 100;

    /** Значение флага --disable-reset, при котором checkpoint не сбрасывается. */
    private const REBUILD_DISABLE_RESET = 1;

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
        private LockService $lockService,
        private LoggerInterface $logger,
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
            if (!$projection->isSyncProjection() && $this->appEnv !== 'test') continue;
            $checkpoint = $this->checkpointStore->getCheckpoint($projection->getProjectionName());
            if ($checkpoint === 0) {
                $projection->init();
            }

            $this->drainEventsByCheckpoint($projection);
        }
    }

    /**
     * Дочитывает проекцию до конца потока событий.
     *
     * Занятая блокировка — не ошибка: проекцию прямо сейчас двигает другой
     * процесс, повторять попытку бессмысленно. Раньше такой случай возвращал
     * false, вызывающая джоба превращала его в исключение, срабатывал
     * предохранитель трёх подряд ошибок, сервис падал и перезапускался
     * RoadRunner'ом — то есть на каждом конфликте блокировки мы получали
     * перезапуск целого воркера.
     */
    public function pullProjectionByName(string $projectionName): ProjectionPullResult
    {
        $lockFactory = $this->lockService->makeLockFactory();
        $lock = $lockFactory->createLock('pullProjectionByName.'.$projectionName);

        if (!$lock->acquire()) {
            $this->logger->debug('projections_manager.pull_skipped_lock_not_acquired', [
                'projection' => $projectionName,
            ]);

            return ProjectionPullResult::Skipped;
        }

        try {
            $this->connection->beginTransaction();
            try {
                $projection = $this->getProjectionByName($projectionName);
                $checkpoint = $this->checkpointStore->getCheckpoint($projectionName);

                if ($checkpoint === 0) {
                    $projection->init();
                }

                $this->drainEventsByCheckpoint($projection);

                $this->connection->commit();
            } catch (\Exception $e) {
                $this->connection->rollBack();
                throw $e;
            }
        } finally {
            $this->releaseLock($lock, $projectionName);
        }

        return ProjectionPullResult::Pulled;
    }

    /**
     * Освобождает блокировку, не давая сбою маскировать исходную ошибку.
     *
     * Lock::release() бросает LockReleasingException, если после delete()
     * ресурс всё ещё числится занятым. В finally это исключение заменяло
     * собой исключение из тела метода, а при удачном исходе — ломало
     * уже выполненную работу. Плюс несостоявшийся release оставлял замок
     * висеть до истечения TTL, поэтому пробуем ещё раз.
     */
    private function releaseLock(Lock $lock, string $projectionName): void
    {
        try {
            $lock->release();

            return;
        } catch (\Throwable $e) {
            $this->logger->warning('projections_manager.lock_release_failed', [
                'projection' => $projectionName,
                'exception' => $e,
            ]);
        }

        try {
            $lock->release();
        } catch (\Throwable $e) {
            $this->logger->error('projections_manager.lock_release_failed_twice', [
                'projection' => $projectionName,
                'exception' => $e,
            ]);
        }
    }

    public function rebuildAll(): void
    {
        $this->connection->beginTransaction();
        try {
            foreach ($this->projections as $projection) {
                if (!$projection->isAllowedRebuild()) continue;
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

        if ($disableReset !== self::REBUILD_DISABLE_RESET) {
            $this->checkpointStore->resetCheckpoint($projectionName);
            $projection->init();
            $projection->reset();
        }

        $passes = 0;
        while (true) {
            $oldCheckpoint = $this->checkpointStore->getCheckpoint($projectionName);

            $this->connection->beginTransaction();
            try {
                $this->handleEventsByCheckpoint($projection, $oldCheckpoint, self::REBUILD_BATCH_SIZE);
                $this->connection->commit();
            } catch (\Exception $e) {
                $this->connection->rollBack();
                throw $e;
            }

            $newCheckpoint = $this->checkpointStore->getCheckpoint($projectionName);

            if ($newCheckpoint === $oldCheckpoint || $passes >= self::REBUILD_MAX_PASSES) {
                break;
            }

            sleep(self::REBUILD_PAUSE_SECONDS);
            $passes++;
        }
    }

    /**
     * Обрабатывает один батч событий, начиная с checkpoint.
     *
     * @return int новый checkpoint (равен исходному, если событий не было)
     */
    private function handleEventsByCheckpoint(CanProjectEvents $projection, int $checkpoint, int $limit = 0): int
    {
        $events = $this->eventsRepository->getStream($projection->getStreamName(), $checkpoint, $limit);

        $oldCheckpoint = $checkpoint;

        foreach ($events as $event) {
            $projection->handle($event);

            $checkpoint++;
        }

        if ($oldCheckpoint === $checkpoint) {
            return $oldCheckpoint;
        }

        $this->checkpointStore->saveCheckpoint($projection->getProjectionName(), $checkpoint);

        return $checkpoint;
    }

    /**
     * Дочитывает поток до исчерпания.
     *
     * PULL_BATCH_SIZE ограничивает размер одной выборки (память), но не общий
     * объём за вызов. Раньше за проход обрабатывалось не более 200 событий,
     * поэтому отставшая sync-проекция оставалась частично обновлённой, хотя
     * вызывающий код считал работу выполненной.
     *
     * @return int количество обработанных батчей
     */
    private function drainEventsByCheckpoint(CanProjectEvents $projection): int
    {
        $checkpoint = $this->checkpointStore->getCheckpoint($projection->getProjectionName());
        $batches = 0;

        while (true) {
            $newCheckpoint = $this->handleEventsByCheckpoint($projection, $checkpoint, self::PULL_BATCH_SIZE);

            if ($newCheckpoint === $checkpoint) {
                break;
            }

            $checkpoint = $newCheckpoint;
            $batches++;
        }

        return $batches;
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
