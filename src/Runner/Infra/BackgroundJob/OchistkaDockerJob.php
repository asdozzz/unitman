<?php

namespace App\Runner\Infra\BackgroundJob;

use App\BackgroundJob\Infra\Service\BackgroundJobInterface;
use App\Runner\Infra\Workflow\OchistkaDockeraWorkflow;
use App\Runner\Infra\Workflow\RunnerHealthCheckWorkflow;
use PharIo\Version\Exception;
use Temporal\Client\WorkflowClient;
use Temporal\Client\WorkflowOptions;
use Temporal\Common\IdReusePolicy;

final class OchistkaDockerJob implements BackgroundJobInterface
{
    public function __construct(private WorkflowClient $workflowClient)
    {
    }

    function getName(): string
    {
        return 'ochistka_dockera';
    }

    private function getWorkflowJobOptions(): WorkflowOptions
    {
        return WorkflowOptions::new()
            ->withWorkflowIdReusePolicy(IdReusePolicy::POLICY_UNSPECIFIED)
            ->withTaskQueue(\App\App\Infra\Workflow\WorkflowClientFactory::monoQueueName);
    }

    function run(): bool
    {
        try {
            $options = $this->getWorkflowJobOptions();

            $workflow = $this->workflowClient->newWorkflowStub(
                OchistkaDockeraWorkflow::class,
                $options->withWorkflowId($this->getName())
            );

            $workflow->run();

            return true;
        } catch (Exception) {
            return false;
        }

    }

    function getDelay(): int
    {
        return 60*60*24;
    }

    function getProcessNum(): int
    {
        return 1;
    }
}
