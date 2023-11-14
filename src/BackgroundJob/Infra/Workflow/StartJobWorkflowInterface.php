<?php

namespace App\BackgroundJob\Infra\Workflow;

use Temporal\Workflow\QueryMethod;
use Temporal\Workflow\ReturnType;
use Temporal\Workflow\SignalMethod;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
interface StartJobWorkflowInterface
{
    #[WorkflowMethod(name: "startJob")]
    #[ReturnType("int")]
    function startJob();

    #[SignalMethod]
    function pause();

    #[SignalMethod]
    function unpause();

    #[SignalMethod]
    function stop();

    #[QueryMethod]
    function getCounter(): array;
}
