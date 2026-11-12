<?php

declare(strict_types=1);

namespace App\Runner\Infra\BackgroundJob;

use App\BackgroundJob\Infra\Service\AbstractBackgroundJob;
use App\Runner\Infra\Workflow\OchistkaDockeraWorkflow;
use Psr\Log\LoggerInterface;
use Temporal\Client\WorkflowClient;
use Temporal\Client\WorkflowOptions;
use Temporal\Common\IdReusePolicy;

final class OchistkaDockerJob extends AbstractBackgroundJob
{
    public function __construct(
        private WorkflowClient $workflowClient,
        private LoggerInterface $logger,
    ) {
    }

    public function getName(): string
    {
        return 'ochistka_dockera';
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
                OchistkaDockeraWorkflow::class,
                $options->withWorkflowId($this->getName())
            );

            $workflow->run();

            return true;
        } catch (\Throwable $e) {
            // Ранее здесь ловился PharIo\Version\Exception — внутренний класс
            // транзитивной зависимости, который никогда не возникает при
            // обращении к Temporal, поэтому сбои уходили в цикл сервиса.
            $this->logger->error(
                sprintf('job %s failed: %s', $this->getName(), $e->getMessage()),
                ['exception' => $e]
            );

            return false;
        }
    }

    public function getDelay(): int
    {
        return 60 * 60 * 24;
    }

    public function getProcessNum(): int
    {
        return 1;
    }
}
