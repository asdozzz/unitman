<?php

namespace App\Unitman\Infra\Temporal\Workflow;

use App\App\Infra\Workflow\WorkflowClientFactory;
use App\Unitman\Infra\Temporal\Activity\OcheredUnitovActivity;
use App\Unitman\Infra\Temporal\Activity\UnitRunnerJobsProjectionActivity;
use Carbon\CarbonInterval;
use Temporal\Activity\ActivityOptions;
use Temporal\Promise;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
final class UnitRunnerJobsWorkflow
{
    /**
     * @psalm-suppress MissingPropertyType
     * */
    private $activity;

    public function __construct()
    {
        $this->activity = Workflow::newActivityStub(
            UnitRunnerJobsProjectionActivity::class,
            ActivityOptions::new()
                ->withScheduleToCloseTimeout(CarbonInterval::seconds(10))
                ->withTaskQueue(WorkflowClientFactory::monoQueueName)
        );
    }

    /**
     * @psalm-suppress MissingReturnType
     * */
    #[WorkflowMethod('ObrabotkaProekzii')]
    function run(): \Generator
    {
        yield $this->activity->handleNewEvents();

        return 'OK';
    }
}
