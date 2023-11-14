<?php

namespace App\BackgroundJob\Infra\Workflow;
use Temporal\Workflow\ReturnType;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
final class ChildWorkflow implements ChildWorkflowInterface
{
    #[WorkflowMethod(name: "Child.init")]
    #[ReturnType("int")]
    public function init(int $step)
    {
        return $step;
    }
}
