<?php

namespace App\Runner\Infra\Workflow;

use App\BackgroundJob\Infra\Service\BackgroundJobInterface;
use Temporal\Client\WorkflowClient;
use Temporal\Client\WorkflowOptions;
use Temporal\Common\IdReusePolicy;

final class RunnerHealthCheckWorkflowJob implements BackgroundJobInterface
{
    public function __construct(private WorkflowClient $workflowClient)
    {
    }

    function getName(): string
    {
        return 'runner_health_check';
    }

    private function getWorkflowJobOptions(): WorkflowOptions
    {
        return WorkflowOptions::new()
            ->withWorkflowIdReusePolicy(IdReusePolicy::POLICY_UNSPECIFIED)
            ->withTaskQueue(\App\App\Infra\Workflow\WorkflowClientFactory::monoQueueName);
    }

    function run(): bool
    {
        $options = $this->getWorkflowJobOptions();

        $workflow = $this->workflowClient->newWorkflowStub(
            RunnerHealthCheckWorkflow::class,
            $options->withWorkflowId($this->getName())
        );

        $workflow->run();

        return true;
    }

    function getDelay(): int
    {
        return 5;
    }
}
