<?php

declare(strict_types=1);

namespace App\Runner\Infra\BackgroundJob;

use App\BackgroundJob\Infra\Service\AbstractBackgroundJob;
use App\Runner\Infra\Workflow\RunnerHealthCheckWorkflow;
use Psr\Log\LoggerInterface;
use Temporal\Client\WorkflowClient;
use Temporal\Client\WorkflowOptions;
use Temporal\Common\IdReusePolicy;

final class RunnerHealthCheckWorkflowJob extends AbstractBackgroundJob
{
    public function __construct(
        private WorkflowClient $workflowClient,
        private LoggerInterface $logger,
    ) {
    }

    public function getName(): string
    {
        return 'runner_health_check';
    }

    private function getWorkflowJobOptions(): WorkflowOptions
    {
        return WorkflowOptions::new()
            ->withWorkflowIdReusePolicy(IdReusePolicy::POLICY_UNSPECIFIED)
            ->withTaskQueue(\App\App\Infra\Workflow\WorkflowClientFactory::monoQueueName);
    }

    public function run(): bool
    {
        try {
            $options = $this->getWorkflowJobOptions();

            $workflow = $this->workflowClient->newWorkflowStub(
                RunnerHealthCheckWorkflow::class,
                $options->withWorkflowId($this->getName())
            );

            $workflow->run();

            return true;
        } catch (\Throwable $e) {
            // Ранее здесь ловился PharIo\Version\Exception, из-за чего ошибки
            // Temporal не перехватывались и сервис падал по счётчику ошибок.
            $this->logger->error(
                sprintf('job %s failed: %s', $this->getName(), $e->getMessage()),
                ['exception' => $e]
            );

            return false;
        }
    }

    public function getDelay(): int
    {
        return 300;
    }

    public function getProcessNum(): int
    {
        return 1;
    }
}
