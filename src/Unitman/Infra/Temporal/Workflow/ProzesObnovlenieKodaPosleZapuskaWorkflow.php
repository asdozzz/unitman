<?php

namespace App\Unitman\Infra\Temporal\Workflow;

use App\App\Infra\Workflow\WorkflowClientFactory;
use App\Unitman\Infra\Temporal\Activity\ProzesObnovlenieKodaPosleZapuskaActivity;
use Carbon\CarbonInterval;
use Temporal\Activity\ActivityOptions;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

/**
 * @psalm-suppress TypeDoesNotContainType
 * */
#[WorkflowInterface]
final class ProzesObnovlenieKodaPosleZapuskaWorkflow
{
    private bool $esliOstanovlen = false;

    private bool $esliPodgotovkaSbroshena = false;

    private bool $esliKodObnovlen = false;

    private bool $esliPodgotovlen = false;
    /** @psalm-suppress TypeDoesNotContainType */
    private bool $esliOshibka = false;

    /**
     * @var ProzesObnovlenieKodaPosleZapuskaActivity
     * @psalm-suppress MissingPropertyType
     * */
    private $activity;

    public function __construct()
    {
        $this->activity  = Workflow::newActivityStub(
            ProzesObnovlenieKodaPosleZapuskaActivity::class,
            ActivityOptions::new()
                ->withScheduleToCloseTimeout(CarbonInterval::seconds(10))
                ->withTaskQueue(WorkflowClientFactory::monoQueueName)
        );
    }


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
    public function obnovlen(): void
    {
        $this->esliKodObnovlen = true;
    }

    #[Workflow\SignalMethod]
    public function podgotovlen(): void
    {
        $this->esliPodgotovlen = true;
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
        try {
            yield Workflow::timer(CarbonInterval::seconds(2));
            if ($zapushen) {
                yield $this->activity->ostanovit($unitId);

                yield Workflow::awaitWithTimeout(120, fn() => $this->esliOstanovlen || $this->esliOshibka);

                if (!$this->esliOstanovlen || $this->esliOshibka) {
                    throw new \Exception('exit');
                }
            }

            if ($podgotovlen) {
                yield $this->activity->sbrositPodgotovku($unitId);

                yield Workflow::awaitWithTimeout(120, fn() => $this->esliPodgotovkaSbroshena || $this->esliOshibka);

                if (!$this->esliPodgotovkaSbroshena || $this->esliOshibka) {
                    throw new \Exception('exit');
                }
            }

            yield $this->activity->obnovitKod($unitId);

            yield Workflow::awaitWithTimeout(120, fn() => $this->esliKodObnovlen || $this->esliOshibka);

            if (!$this->esliKodObnovlen || $this->esliOshibka) {
                throw new \Exception('exit');
            }

            yield $this->activity->podgotovit($unitId);

            yield Workflow::awaitWithTimeout(600, fn() => $this->esliPodgotovlen || $this->esliOshibka);

            if (!$this->esliPodgotovlen || $this->esliOshibka) {
                throw new \Exception('exit');
            }

            yield $this->activity->zapustit($unitId);

        } catch (\Exception $e) {
            yield $this->activity->oshibkaProzesaObnovleniya($unitId, $e->getMessage());
        }
    }


}
