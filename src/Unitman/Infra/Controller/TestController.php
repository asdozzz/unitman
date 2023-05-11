<?php

namespace App\Unitman\Infra\Controller;

use App\App\Infra\Workflow\ActivityCollection;
use App\App\Infra\Workflow\GreetingWorkflow;
use Carbon\CarbonInterval;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\WorkflowClient;
use Temporal\Client\WorkflowOptions;

final class TestController extends AbstractController
{
    #[Route('/test/{unitId}')]
    function test(string $unitId)
    {
        try {
            if (empty($unitId)) {
                throw new \Exception('unitId not found');
            }
            $workflowClient = WorkflowClient::create(
                ServiceClient::create(
                    'temporal:7233'
                ),
            );
            $workflow = $workflowClient->newWorkflowStub(
                GreetingWorkflow::class,
                WorkflowOptions::new()->withWorkflowExecutionTimeout(CarbonInterval::minute())
            );

            $result = $workflow->greet($unitId);
            return new Response("<pre>" . print_r($result, true) . "</pre>");
        } catch (\Exception $e) {
            return new Response("<pre>" . print_r($e->getMessage(), true) . "</pre>");
        }

    }
}
