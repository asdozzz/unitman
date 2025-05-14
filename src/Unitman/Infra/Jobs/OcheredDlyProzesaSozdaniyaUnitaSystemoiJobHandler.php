<?php

namespace App\Unitman\Infra\Jobs;

use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\ReadModel\Unit\OcheredDlyProzesaSozdaniyaUnitaSystemoi;
use App\Unitman\Infra\Temporal\Workflow\ProzesAvtosborkiUnitaSystemoiWorkflow;
use App\Utils\Service\LockService;
use FluffyDiscord\RoadRunnerBundle\Worker\JobsWorker\JobsHandlerInterface;
use Spiral\RoadRunner\Jobs\Task\ReceivedTaskInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Temporal\Client\WorkflowClient;

final class OcheredDlyProzesaSozdaniyaUnitaSystemoiJobHandler implements JobsHandlerInterface
{
    public function __construct(
        private SerializerInterface $serializer,
        protected WorkflowClient $workflowClient,
        private UnitRepository $unitRepository,
        private LockService $lockService
    )
    {
    }
    const QUEUE_NAME = 'prozess_sozdaniya_unita_systemoi';
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
            /** @var OcheredDlyProzesaSozdaniyaUnitaSystemoi $model **/
            match ($model->state) {
                OcheredDlyProzesaSozdaniyaUnitaSystemoi::SOZDAN => $this->sozdan($model->unitId),
                OcheredDlyProzesaSozdaniyaUnitaSystemoi::SOBRAN => $this->sobran($model->unitId),
                OcheredDlyProzesaSozdaniyaUnitaSystemoi::PODGOTOVLEN => $this->podgotovlen($model->unitId),
                OcheredDlyProzesaSozdaniyaUnitaSystemoi::ERROR => $this->setError($model->unitId),
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

    private function sozdan(string $unitId): void
    {
        $unit = $this->unitRepository->getById($unitId);
        $workflowId = $unit->poluchitWorkflowIdDlySozdaniyaUnitaSystemoi();

        if (!empty($workflowId)) {
            $workflow = $this->getWorkflowById(ProzesAvtosborkiUnitaSystemoiWorkflow::class, $workflowId);
            /** @var ProzesAvtosborkiUnitaSystemoiWorkflow $workflow */
            $workflow->sozdan();
        }
    }

    private function sobran(string $unitId): void
    {
        $unit = $this->unitRepository->getById($unitId);
        $workflowId = $unit->poluchitWorkflowIdDlySozdaniyaUnitaSystemoi();

        if (!empty($workflowId)) {
            $workflow = $this->getWorkflowById(ProzesAvtosborkiUnitaSystemoiWorkflow::class, $workflowId);
            /** @var ProzesAvtosborkiUnitaSystemoiWorkflow $workflow */
            $workflow->sobran();
        }
    }

    private function podgotovlen(string $unitId): void
    {
        $unit = $this->unitRepository->getById($unitId);
        $workflowId = $unit->poluchitWorkflowIdDlySozdaniyaUnitaSystemoi();
        if (!empty($workflowId)) {
            $workflow = $this->getWorkflowById(ProzesAvtosborkiUnitaSystemoiWorkflow::class, $workflowId);
            /** @var ProzesAvtosborkiUnitaSystemoiWorkflow $workflow */
            $workflow->podgotovlen();
        }
    }

    private function setError(string $unitId): void
    {
        $unit = $this->unitRepository->getById($unitId);
        $workflowId = $unit->poluchitWorkflowIdDlySozdaniyaUnitaSystemoi();
        if (!empty($workflowId)) {
            $workflow = $this->getWorkflowById(ProzesAvtosborkiUnitaSystemoiWorkflow::class, $workflowId);
            /** @var ProzesAvtosborkiUnitaSystemoiWorkflow $workflow */
            $workflow->oshibka();
        }
    }
}
