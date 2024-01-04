<?php

namespace App\BackgroundJob\Infra\Controller;

use App\BackgroundJob\Infra\Repository\JobRepository;
use App\BackgroundJob\Infra\Workflow\ChildWorkflow;
use App\BackgroundJob\Infra\Workflow\ChildWorkflowInterface;
use App\BackgroundJob\Infra\Workflow\StartJobWorkflow;
use App\BackgroundJob\Infra\Workflow\StartJobWorkflowInterface;
use App\Utils\Model\Reponse\Response;
use Carbon\CarbonInterval;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Temporal\Client\WorkflowClient;
use Temporal\Client\WorkflowOptions;
use Temporal\Workflow;

#[Route('/api/job')]
final class JobController extends AbstractController
{

    #[Route('/start', methods: ['POST'])]
    public function start(WorkflowClient $workflowClient, JobRepository $jobRepository): JsonResponse
    {
        $workflow = $workflowClient->newWorkflowStub(
            StartJobWorkflow::class
        );

        /*$result = $workflow->startJob(ChildWorkflowInterface::class, 'init', [5]);
        return new JsonResponse(['id' => $result]);*/

        $run = $workflowClient->start($workflow, ChildWorkflowInterface::class, 'init', [5]);

        $jobRepository->start('test', $run->getExecution()->getID());

        return new JsonResponse(['id' => $run->getExecution()->getID()]);

    }

    #[Route('/pause', methods: ['POST'])]
    public function pause(WorkflowClient $workflowClient, JobRepository $jobRepository): JsonResponse
    {
        $runId = $jobRepository->getRunIdByName('test');
        /** @psalm-suppress NoValue*/
        $workflow = $workflowClient->newRunningWorkflowStub(
            StartJobWorkflow::class,
            $runId
        );

        $workflow->pause();

        $jobRepository->pause('test');

        return new JsonResponse(['counter' => $workflow->getCounter()]);
    }

    #[Route('/unpause', methods: ['POST'])]
    public function unpause(WorkflowClient $workflowClient, JobRepository $jobRepository): JsonResponse
    {
        $runId = $jobRepository->getRunIdByName('test');
        /** @psalm-suppress NoValue*/
        $workflow = $workflowClient->newRunningWorkflowStub(
            StartJobWorkflow::class,
            $runId
        );

        $workflow->unpause();

        $jobRepository->unpause('test');

        return new JsonResponse(['counter' => $workflow->getCounter()]);
    }

    #[Route('/stop', methods: ['POST'])]
    public function stop(WorkflowClient $workflowClient, JobRepository $jobRepository): JsonResponse
    {
        $runId = $jobRepository->getRunIdByName('test');
        /** @psalm-suppress NoValue*/
        $workflow = $workflowClient->newRunningWorkflowStub(
            StartJobWorkflow::class,
            $runId
        );

        $workflow->stop();

        $jobRepository->stop('test');

        return new JsonResponse(['counter' => $workflow->getCounter()]);
    }

    #[Route('/result', methods: ['GET'])]
    public function result(WorkflowClient $workflowClient, JobRepository $jobRepository): JsonResponse
    {
        $runId = $jobRepository->getRunIdByName('test');
        /** @psalm-suppress NoValue*/
        $workflow = $workflowClient->newRunningWorkflowStub(
            StartJobWorkflow::class,
            $runId
        );
        /** @var $workflow StartJobWorkflow */
        return new JsonResponse(['counter' => $workflow->getCounter()]);
    }
}
