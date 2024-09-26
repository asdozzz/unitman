<?php

namespace App\Unitman\Infra\Temporal\Workflow;

use App\App\Infra\Workflow\WorkflowClientFactory;
use App\Unitman\Infra\Temporal\Activity\ProzesAvtosborkiUnitaSystemoiActivity;
use Carbon\CarbonInterval;
use Temporal\Activity\ActivityOptions;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

/**
 * @psalm-suppress TypeDoesNotContainType
 * */
#[WorkflowInterface]
final class ProzesAvtosborkiUnitaSystemoiWorkflow
{
    /** @psalm-suppress TypeDoesNotContainType */
    private bool $esliSozdan = false;
    /** @psalm-suppress TypeDoesNotContainType */
    private bool $esliSobran = false;
    /** @psalm-suppress TypeDoesNotContainType */
    private bool $esliPodgotovlen = false;
    /** @psalm-suppress TypeDoesNotContainType */
    private bool $esliOshibka = false;

    #[Workflow\SignalMethod]
    public function sozdan(): void
    {
        $this->esliSozdan = true;
    }
    #[Workflow\SignalMethod]
    public function sobran(): void
    {
        $this->esliSobran = true;
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
    public function execute(string $unitId)
    {
        $activity  = Workflow::newActivityStub(
            ProzesAvtosborkiUnitaSystemoiActivity::class,
            ActivityOptions::new()
                ->withScheduleToCloseTimeout(CarbonInterval::seconds(10))
                ->withTaskQueue(WorkflowClientFactory::monoQueueName)
        );

        try {
            yield Workflow::awaitWithTimeout(120, fn() => $this->esliSozdan || $this->esliOshibka);

            if (!$this->esliSozdan || $this->esliOshibka) {
                throw new \Exception('exit');
            }

            yield $activity->sobrat($unitId);

            yield Workflow::awaitWithTimeout(120, fn() => $this->esliSobran || $this->esliOshibka);

            if (!$this->esliSobran || $this->esliOshibka) {
                throw new \Exception('exit');
            }

            yield $activity->ustanovitDefoltniiKonfig($unitId);
            yield $activity->podgotovit($unitId);

            yield Workflow::awaitWithTimeout(600, fn() => $this->esliPodgotovlen || $this->esliOshibka);

            if (!$this->esliPodgotovlen || $this->esliOshibka) {
                throw new \Exception('exit');
            }

            yield $activity->zapustit($unitId);

            return 'OK';
        } catch (\Exception $e) {
            yield $activity->oshibkaProzesaSozdaniya($unitId, $e->getMessage());
            return $e->getMessage();
        }
    }


}
