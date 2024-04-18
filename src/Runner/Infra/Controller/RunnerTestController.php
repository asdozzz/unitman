<?php

namespace App\Runner\Infra\Controller;

use App\Runner\Business\Model\RunnerState;
use App\Runner\Infra\Repository\SqlRunnerStateRepository;
use App\Runner\Infra\Workflow\RunnerHealthCheckWorkflow;
use App\Unitman\Infra\Temporal\Workflow\OcheredUnitovWorkflow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Temporal\Client\WorkflowClient;
use Temporal\Client\WorkflowOptions;
use Temporal\Workflow;

#[Route('/runner')]
final class RunnerTestController extends AbstractController
{
   /* #[Route('/test', methods: ['GET'])]
    function test(OcheredUnitovWorkflow $workflow): Response
    {
        $result = $workflow->run();
    }*/
}
