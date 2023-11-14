<?php

namespace App\BackgroundJob\Infra\Workflow;

use Temporal\Workflow\ReturnType;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
interface ChildWorkflowInterface
{
    #[WorkflowMethod(name: "Child.init")]
    #[ReturnType("int")]
    public function init(int $step);
}
