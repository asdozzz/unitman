<?php

namespace App\Unitman\Infra\Jobs;

use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\ReadModel\Unit\OcheredDlyProzesaUdaleniyaUnitaPosleZapuska;
use App\Unitman\Infra\Temporal\Workflow\ProzesUdaleniyaUnitaPosleZapuskaWorkflow;
use App\Utils\Service\LockService;
use FluffyDiscord\RoadRunnerBundle\Worker\JobsWorker\JobsHandlerInterface;
use Psr\Log\LoggerInterface;
use Spiral\RoadRunner\Jobs\Task\ReceivedTaskInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Temporal\Client\WorkflowClient;

final class OcheredDlyProzesaUdaleniyaUnitaPosleZapuskaJobHandler implements JobsHandlerInterface
{
    const QUEUE_NAME = 'prozess_udaleniya_unita_posle_zapuska';

    public function __construct(
        protected WorkflowClient $workflowClient,
        private UnitRepository $unitRepository,
        private SerializerInterface $serializer,
        private LockService $lockService
    )
    {
    }

    public function isSupported(ReceivedTaskInterface $task): bool
    {
        return $task->getPipeline() === self::QUEUE_NAME;
    }

    public function handle(ReceivedTaskInterface $task): void
    {
        $lockFactory = $this->lockService->makeLockFactory();
        $lock = $lockFactory->createLock(self::QUEUE_NAME.'.'.$task->getId());

        if (!$lock->acquire()) {
            return;
        }

        try {
            $model = $this->serializer->deserialize($task->getPayload(), $task->getName(), 'json');
            /** @var OcheredDlyProzesaUdaleniyaUnitaPosleZapuska $model*/
            match ($model->state) {
                OcheredDlyProzesaUdaleniyaUnitaPosleZapuska::OSTANOVLEN => $this->ostanovlen($model->unitId),
                OcheredDlyProzesaUdaleniyaUnitaPosleZapuska::SBROSHENA_PODGOTOVKA => $this->podgotovkaSbroshena($model->unitId),
                OcheredDlyProzesaUdaleniyaUnitaPosleZapuska::ERROR => $this->setError($model->unitId),
            };
        } finally {
            $lock->release();
        }

    }

    /**
     * @template T
     * @param class-string<T> $className
     * @param string $workflowId
     * @return T
     * */
    protected function getWorkflowById(string $className,string $workflowId)
    {
        /** @psalm-suppress NoValue*/
        $workflow = $this->workflowClient->newRunningWorkflowStub($className, $workflowId);

        return $workflow;
    }

    private function ostanovlen(string $unitId): void
    {
        $unit = $this->unitRepository->getById($unitId);
        $workflowId = $unit->poluchitWorkflowIdDlyUdaleniyaUnitaPosleZapuska();

        if (!empty($workflowId)) {
            $workflow = $this->getWorkflowById(ProzesUdaleniyaUnitaPosleZapuskaWorkflow::class, $workflowId);
            /** @var ProzesUdaleniyaUnitaPosleZapuskaWorkflow $workflow */
            $workflow->ostanovlen();
        }
    }

    private function podgotovkaSbroshena(string $unitId): void
    {
        $unit = $this->unitRepository->getById($unitId);
        $workflowId = $unit->poluchitWorkflowIdDlyUdaleniyaUnitaPosleZapuska();

        if (!empty($workflowId)) {
            $workflow = $this->getWorkflowById(ProzesUdaleniyaUnitaPosleZapuskaWorkflow::class, $workflowId);
            /** @var ProzesUdaleniyaUnitaPosleZapuskaWorkflow $workflow */
            $workflow->podgotovkaSbroshena();
        }
    }

    private function setError(string $unitId): void
    {
        $unit = $this->unitRepository->getById($unitId);
        $workflowId = $unit->poluchitWorkflowIdDlyUdaleniyaUnitaPosleZapuska();

        if (!empty($workflowId)) {
            $workflow = $this->getWorkflowById(ProzesUdaleniyaUnitaPosleZapuskaWorkflow::class, $workflowId);
            /** @var ProzesUdaleniyaUnitaPosleZapuskaWorkflow $workflow */
            $workflow->oshibka();
        }
    }
}
