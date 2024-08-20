<?php

namespace App\Unitman\Infra\BackgroundJob\ProzesUdaleniyaUnitaPosleZapuska;

use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\ReadModel\Unit\OcheredDlyProzesaUdaleniyaUnitaPosleZapuska;
use App\Unitman\Infra\Repository\Unit\OcheredDlyProzesaUdaleniyaUnitaPosleZapuskaRepository;
use App\Unitman\Infra\Temporal\Workflow\ProzesUdaleniyaUnitaPosleZapuskaWorkflow;
use Temporal\Client\WorkflowClient;

final class ProzesUdaleniyaUnitaPosleZapuskaHandler
{
    public function __construct(
        protected WorkflowClient $workflowClient,
        private UnitRepository $unitRepository,
        private OcheredDlyProzesaUdaleniyaUnitaPosleZapuskaRepository $ocheredRepository,
    )
    {
    }

    /**
     * @return OcheredDlyProzesaUdaleniyaUnitaPosleZapuska[]
     * */
    public function poluchitZadachiNaObrabotku(int $limit = 10): array
    {
        return $this->ocheredRepository->poluchitZadachiNaObrabotku($limit);
    }

    public function obrabotatZadachu(OcheredDlyProzesaUdaleniyaUnitaPosleZapuska $model): bool
    {
        match ($model->state) {
            OcheredDlyProzesaUdaleniyaUnitaPosleZapuska::OSTANOVLEN => $this->ostanovlen($model->unitId),
            OcheredDlyProzesaUdaleniyaUnitaPosleZapuska::SBROSHENA_PODGOTOVKA => $this->podgotovkaSbroshena($model->unitId),
            OcheredDlyProzesaUdaleniyaUnitaPosleZapuska::ERROR => $this->setError($model->unitId),
        };

        return true;
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
