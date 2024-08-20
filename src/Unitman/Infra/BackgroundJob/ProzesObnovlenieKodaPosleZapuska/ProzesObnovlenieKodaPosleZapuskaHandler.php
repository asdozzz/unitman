<?php

namespace App\Unitman\Infra\BackgroundJob\ProzesObnovlenieKodaPosleZapuska;

use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\ReadModel\Unit\OcheredDlyProzesaObnovleniyaKodaPosleZapuska;
use App\Unitman\Infra\Repository\Unit\OcheredDlyProzesaObnovleniyaKodaPosleZapuskaRepository;
use App\Unitman\Infra\Temporal\Workflow\ProzesObnovlenieKodaPosleZapuskaWorkflow;
use Temporal\Client\WorkflowClient;

final class ProzesObnovlenieKodaPosleZapuskaHandler
{
    public function __construct(protected WorkflowClient $workflowClient, private UnitRepository $unitRepository, private OcheredDlyProzesaObnovleniyaKodaPosleZapuskaRepository $ocheredRepository)
    {
    }

    /**
     * @return OcheredDlyProzesaObnovleniyaKodaPosleZapuska[]
     * */
    public function poluchitZadachiNaObrabotku(int $limit = 10): array
    {
        return $this->ocheredRepository->poluchitZadachiNaObrabotku($limit);
    }

    public function obrabotatZadachu(OcheredDlyProzesaObnovleniyaKodaPosleZapuska $model): bool
    {
        match ($model->state) {
            OcheredDlyProzesaObnovleniyaKodaPosleZapuska::OSTANOVLEN => $this->ostanovlen($model->unitId),
            OcheredDlyProzesaObnovleniyaKodaPosleZapuska::SBROSHENA_PODGOTOVKA => $this->podgotovkaSbroshena($model->unitId),
            OcheredDlyProzesaObnovleniyaKodaPosleZapuska::OBNOVLEN => $this->obnovlen($model->unitId),
            OcheredDlyProzesaObnovleniyaKodaPosleZapuska::PODGOTOVLEN => $this->podgotovlen($model->unitId),
            OcheredDlyProzesaObnovleniyaKodaPosleZapuska::ERROR => $this->setError($model->unitId),
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
