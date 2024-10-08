<?php

namespace App\Runner\Infra\Workflow;
use App\App\Infra\Workflow\WorkflowClientFactory;
use App\Runner\Business\Command\NachatOchistkuProekta;
use App\Runner\Business\Model\GolangRunner\Project\ResultatOchistkiProekta;
use Temporal\Activity\ActivityOptions;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
final class NachatOchistkuProektaWorkflow
{
    /**
     * @psalm-suppress MissingReturnType
     * */
    #[WorkflowMethod(name: "NachatOchistkuProekta")]
    #[Workflow\ReturnType(ResultatOchistkiProekta::class)]
    public function removeProject(NachatOchistkuProekta $command)
    {
        return yield Workflow::executeActivity(
            'NachatOchistkuProektaActivity',
            [$command],
            ActivityOptions::new()
                ->withStartToCloseTimeout(60*60)
                ->withTaskQueue(WorkflowClientFactory::runnerQueueName)
        );
    }
}
