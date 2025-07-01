<?php

namespace App\Unitman\Infra\Temporal\Workflow;

use App\App\Infra\Workflow\WorkflowClientFactory;
use App\Unitman\Infra\Temporal\Activity\ProzesUdaleniyaUnitaPosleZapuskaActivity;
use Carbon\CarbonInterval;
use Temporal\Activity\ActivityOptions;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowMethod;

/**
 * @psalm-suppress TypeDoesNotContainType
 * */
#[Workflow\WorkflowInterface]
final class ProzesUdaleniyaUnitaPosleZapuskaWorkflow
{
    private bool $esliOstanovlen = false;

    private bool $esliPodgotovkaSbroshena = false;

    private bool $esliOshibka = false;

    #[Workflow\SignalMethod]
    public function ostanovlen(): void
    {
        $this->esliOstanovlen = true;
    }

    #[Workflow\SignalMethod]
    public function podgotovkaSbroshena(): void
    {
        $this->esliPodgotovkaSbroshena = true;
    }

    #[Workflow\SignalMethod]
    public function oshibka(): void
    {
        $this->esliOshibka = true;
    }

    /**
     * @psalm-suppress MissingReturnType
     * */
    #[WorkflowMethod]
    public function execute(string $unitId)
    {
        $activity  = Workflow::newActivityStub(
            ProzesUdaleniyaUnitaPosleZapuskaActivity::class,
            ActivityOptions::new()
                ->withScheduleToCloseTimeout(CarbonInterval::seconds(20))
                ->withTaskQueue(WorkflowClientFactory::monoQueueName)
        );

        try {
            yield Workflow::timer(CarbonInterval::seconds(2));
            yield $activity->ostanovit($unitId);

            yield Workflow::awaitWithTimeout(120, fn() => $this->esliOstanovlen || $this->esliOshibka);

            if (!$this->esliOstanovlen || $this->esliOshibka) {
                throw new \Exception('exit');
            }

            yield $activity->sbrositPodgotovku($unitId);

            yield Workflow::awaitWithTimeout(120, fn() => $this->esliPodgotovkaSbroshena || $this->esliOshibka);

            if (!$this->esliPodgotovkaSbroshena || $this->esliOshibka) {
                throw new \Exception('exit');
            }

            yield $activity->udalit($unitId);

        } catch (\Exception $e) {
            yield $activity->oshibkaProzesaUdaleniya($unitId, $e->getMessage());
        }
    }
}
