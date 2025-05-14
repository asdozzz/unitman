<?php

namespace App\Unitman\Infra\Jobs;

use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\ReadModel\Unit\OcheredDlyProzesaObnovleniyaKodaPosleZapuska;
use App\Unitman\Infra\Temporal\Workflow\ProzesObnovlenieKodaPosleZapuskaWorkflow;
use App\Utils\Service\LockService;
use FluffyDiscord\RoadRunnerBundle\Worker\JobsWorker\JobsHandlerInterface;
use Spiral\RoadRunner\Jobs\Task\ReceivedTaskInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Temporal\Client\WorkflowClient;

final class OcheredDlyProzesaObnovleniyaPosleZapuskaJobHandler implements JobsHandlerInterface
{
    const QUEUE_NAME = 'prozess_obnovlenie_koda_posle_zapuska';

    public function __construct(
        private SerializerInterface $serializer,
        protected WorkflowClient $workflowClient,
        private UnitRepository $unitRepository,
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
            /** @var OcheredDlyProzesaObnovleniyaKodaPosleZapuska $model **/
            match ($model->state) {
                OcheredDlyProzesaObnovleniyaKodaPosleZapuska::OSTANOVLEN => $this->ostanovlen($model->unitId),
                OcheredDlyProzesaObnovleniyaKodaPosleZapuska::SBROSHENA_PODGOTOVKA => $this->podgotovkaSbroshena($model->unitId),
                OcheredDlyProzesaObnovleniyaKodaPosleZapuska::OBNOVLEN => $this->obnovlen($model->unitId),
                OcheredDlyProzesaObnovleniyaKodaPosleZapuska::PODGOTOVLEN => $this->podgotovlen($model->unitId),
                OcheredDlyProzesaObnovleniyaKodaPosleZapuska::ERROR => $this->setError($model->unitId),
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
        $workflowId = $unit->poluchitWorkflowIdDlyObnovleniyaKodaPosleZapuska();

        if (!empty($workflowId)) {
            $workflow = $this->getWorkflowById(ProzesObnovlenieKodaPosleZapuskaWorkflow::class, $workflowId);
            /** @var ProzesObnovlenieKodaPosleZapuskaWorkflow $workflow */
            $workflow->ostanovlen();
        }
    }

    private function podgotovkaSbroshena(string $unitId): void
    {
        $unit = $this->unitRepository->getById($unitId);
        $workflowId = $unit->poluchitWorkflowIdDlyObnovleniyaKodaPosleZapuska();
        if (!empty($workflowId)) {
            $workflow = $this->getWorkflowById(ProzesObnovlenieKodaPosleZapuskaWorkflow::class, $workflowId);
            /** @var ProzesObnovlenieKodaPosleZapuskaWorkflow $workflow */
            $workflow->podgotovkaSbroshena();
        }
    }

    private function obnovlen(string $unitId): void
    {
        $unit = $this->unitRepository->getById($unitId);
        $workflowId = $unit->poluchitWorkflowIdDlyObnovleniyaKodaPosleZapuska();
        if (!empty($workflowId)) {
            $workflow = $this->getWorkflowById(ProzesObnovlenieKodaPosleZapuskaWorkflow::class, $workflowId);
            /** @var ProzesObnovlenieKodaPosleZapuskaWorkflow $workflow */
            $workflow->obnovlen();
        }
    }

    private function podgotovlen(string $unitId): void
    {
        $unit = $this->unitRepository->getById($unitId);
        $workflowId = $unit->poluchitWorkflowIdDlyObnovleniyaKodaPosleZapuska();
        if (!empty($workflowId)) {
            $workflow = $this->getWorkflowById(ProzesObnovlenieKodaPosleZapuskaWorkflow::class, $workflowId);
            /** @var ProzesObnovlenieKodaPosleZapuskaWorkflow $workflow */
            $workflow->podgotovlen();
        }
    }

    private function setError(string $unitId): void
    {
        $unit = $this->unitRepository->getById($unitId);
        $workflowId = $unit->poluchitWorkflowIdDlyObnovleniyaKodaPosleZapuska();
        if (!empty($workflowId)) {
            $workflow = $this->getWorkflowById(ProzesObnovlenieKodaPosleZapuskaWorkflow::class, $workflowId);
            /** @var ProzesObnovlenieKodaPosleZapuskaWorkflow $workflow */
            $workflow->oshibka();
        }
    }
}
