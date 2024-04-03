<?php

namespace App\Utils\Cli\Command;

use App\Unitman\Infra\Temporal\Workflow\OcheredUnitovWorkflow;
use Carbon\CarbonInterval;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Temporal\Client\WorkflowClient;
use Temporal\Client\WorkflowOptions;

#[AsCommand(name: 'app:test')]
final class TestCommand extends Command
{
    public function __construct(private WorkflowClient $workflowClient)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $workflow = $this->workflowClient->newWorkflowStub(
            OcheredUnitovWorkflow::class,
            WorkflowOptions::new()
                ->withTaskQueue(\App\App\Infra\Workflow\WorkflowClientFactory::monoQueueName)
        );

        try {
            $result = $workflow->run();

            $output->writeln('success: '. var_export($result, true));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('error: '. $e->getMessage());
            return Command::FAILURE;
        }
    }


}
