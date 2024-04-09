<?php

namespace App\BackgroundJob\Infra\Service;

use App\BackgroundJob\Infra\Repository\JobRepository;
use App\BackgroundJob\Infra\Workflow\StartJobWorkflow;
use Temporal\Client\WorkflowClient;
use Temporal\Client\WorkflowOptions;
use Temporal\Common\IdReusePolicy;
use Temporal\Exception\Client\WorkflowExecutionAlreadyStartedException;

final class BackgroundJobService
{
    public function __construct(private WorkflowClient $workflowClient, private JobRepository $jobRepository)
    {
    }

    function init(): void
    {
        $this->jobRepository->init();
    }

    private function getWorkflowJobOptions(): WorkflowOptions
    {
        return WorkflowOptions::new()
            ->withWorkflowIdReusePolicy(IdReusePolicy::POLICY_UNSPECIFIED)
            ->withTaskQueue(\App\App\Infra\Workflow\WorkflowClientFactory::monoQueueName);
    }

    function isAlreadyRun(string $name): bool
    {
        $oldRunId = $this->jobRepository->findRunIdByName($name);

        if (empty($oldRunId)) {
            return false;
        }

        try {
            /** @psalm-suppress NoValue*/
            $workflow = $this->workflowClient->newRunningWorkflowStub(
                StartJobWorkflow::class,
                $oldRunId
            );

            $counter = $workflow->getCounter();
            /** @var int $counter*/
        } catch (\Exception $e) {
            return false;
        }

        return isset($counter);
    }

    function start(BackgroundJobInterface $job): void
    {
        $options = $this->getWorkflowJobOptions();

        try {
            $workflow = $this->workflowClient->newWorkflowStub(
                StartJobWorkflow::class,
                $options->withWorkflowId($job->getName())
            );

            $run = $this->workflowClient->start($workflow, $job->getWorkflowClass(), $job->getMethodName());
            $this->jobRepository->start($job->getName(), $run->getExecution()->getID());
        } catch (WorkflowExecutionAlreadyStartedException $e) {
            $workflow = $this->workflowClient->newUntypedRunningWorkflowStub($job->getName());
            $this->jobRepository->start($job->getName(), $workflow->getExecution()->getID());
        }
    }

    function stop(BackgroundJobInterface $job): void
    {
        $runId = $this->jobRepository->findRunIdByName($job->getName());

        if (empty($runId)) {
            $this->jobRepository->stop($job->getName());
            return;
        }

        try {
            /** @psalm-suppress NoValue*/
            $workflow = $this->workflowClient->newRunningWorkflowStub(
                StartJobWorkflow::class,
                $runId
            );

            $workflow->stop();
        } catch (\Exception $e) {

        }


        $this->jobRepository->stop($job->getName());
    }
}
