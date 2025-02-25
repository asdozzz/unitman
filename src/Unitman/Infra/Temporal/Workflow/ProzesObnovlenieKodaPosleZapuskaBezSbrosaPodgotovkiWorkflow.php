<?php

namespace App\Unitman\Infra\Temporal\Workflow;

use App\App\Infra\Workflow\WorkflowClientFactory;
use App\Unitman\Infra\Temporal\Activity\ProzesObnovlenieKodaPosleZapuskaActivity;
use App\Unitman\Infra\Temporal\Activity\ProzesObnovlenieKodaPosleZapuskaBezSbrosaPodgotovkiActivity;
use Carbon\CarbonInterval;
use Temporal\Activity\ActivityOptions;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

/**
 * @psalm-suppress TypeDoesNotContainType
 * */
#[WorkflowInterface]
final class ProzesObnovlenieKodaPosleZapuskaBezSbrosaPodgotovkiWorkflow
{
    private bool $esliOstanovlen = false;

    private bool $esliKodObnovlen = false;

    /** @psalm-suppress TypeDoesNotContainType */
    private bool $esliOshibka = false;

    #[Workflow\SignalMethod]
    public function ostanovlen(): void
    {
        $this->esliOstanovlen = true;
    }

    #[Workflow\SignalMethod]
    public function obnovlen(): void
    {
        $this->esliKodObnovlen = true;
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
    public function execute(string $unitId, bool $zapushen = true, bool $podgotovlen = true)
    {
        $activity  = Workflow::newActivityStub(
            ProzesObnovlenieKodaPosleZapuskaBezSbrosaPodgotovkiActivity::class,
            ActivityOptions::new()
                ->withScheduleToCloseTimeout(CarbonInterval::seconds(10))
                ->withTaskQueue(WorkflowClientFactory::monoQueueName)
        );

        try {
            if ($zapushen) {
                yield $activity->ostanovit($unitId);

                yield Workflow::awaitWithTimeout(120, fn() => $this->esliOstanovlen || $this->esliOshibka);

                if (!$this->esliOstanovlen || $this->esliOshibka) {
                    throw new \Exception('exit');
                }
            }

            yield $activity->obnovitKod($unitId);

            yield Workflow::awaitWithTimeout(120, fn() => $this->esliKodObnovlen || $this->esliOshibka);

            if (!$this->esliKodObnovlen || $this->esliOshibka) {
                throw new \Exception('exit');
            }

            if ($this->esliOshibka) {
                throw new \Exception('exit');
            }

            yield $activity->zapustit($unitId);

        } catch (\Exception $e) {
            yield $activity->oshibkaProzesaObnovleniya($unitId, $e->getMessage());
        }
    }


}
