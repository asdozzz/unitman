<?php

namespace App\Runner\Infra\Workflow;
use App\App\Infra\Workflow\WorkflowClientFactory;
use App\Runner\Business\Model\GolangRunner\Runner\RunnerHealthCheckResult;
use Carbon\CarbonInterval;
use Temporal\Activity\ActivityOptions;
use Temporal\Common\RetryOptions;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
final class OchistkaDockeraWorkflow
{
    /**
     * @psalm-suppress MissingReturnType
     * */
    #[WorkflowMethod(name: "OchistkaDockera")]
    public function run(): \Generator
    {
        yield Workflow::executeActivity(
            'OchistkaDockeraActivity',
            [],
            ActivityOptions::new()
                ->withStartToCloseTimeout(60*60)
                ->withTaskQueue(WorkflowClientFactory::runnerQueueName)
        );
    }
}
