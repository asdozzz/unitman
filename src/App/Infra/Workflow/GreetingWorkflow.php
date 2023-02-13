<?php

namespace App\App\Infra\Workflow;

use App\App\Business\Command\InitProjectCommand;
use Carbon\CarbonInterval;
use Temporal\Activity\ActivityMethod;
use Temporal\Activity\ActivityOptions;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;
#[WorkflowInterface]
final class GreetingWorkflow implements AppWorkflowInterface
{
    #[WorkflowMethod(name: "InitProject")]
    public function greet(string $unitId)
    {
        $url = "https://github.com/asdozzz/exunit.git";
        $command = new InitProjectCommand('uwin', $unitId, $url);
        return yield Workflow::executeActivity(
            'InitProjectActivity',
            [$command],
            ActivityOptions::new()
                ->withStartToCloseTimeout(3)
                ->withTaskQueue('sample')
        );
    }
}
