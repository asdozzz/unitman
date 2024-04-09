<?php

namespace App\Unitman\Infra\Temporal\Workflow;

use App\BackgroundJob\Infra\Service\BackgroundJobInterface;

final class UnitRunnerJobsWorkflowJob implements BackgroundJobInterface
{

    function getWorkflowClass(): string
    {
        return UnitRunnerJobsWorkflow::class;
    }

    function getMethodName(): string
    {
        return 'run';
    }

    function getName(): string
    {
        return 'unit_runner_jobs';
    }
}
