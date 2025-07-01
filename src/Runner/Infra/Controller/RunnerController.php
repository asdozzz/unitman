<?php

namespace App\Runner\Infra\Controller;

use App\Runner\Business\Command\GetResponseCommand;
use App\Runner\Business\Command\SaveStepsCommand;
use App\Runner\Infra\Repository\SqlRunnerStateRepository;
use App\Runner\Infra\Service\RedisService;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/runner')]
final class RunnerController extends AbstractController
{
    #[Route('/getDefaultRunnerActive', methods: ['POST'])]
    function getDefaultRunnerActive(SqlRunnerStateRepository $runnerStateRepository): JsonResponse
    {
        $runnerState = $runnerStateRepository->getDefaultRunnerState();
        return new JsonResponse(['active' => $runnerState->isActive()]);
    }

    #[Route('/test', methods: ['GET'])]
    function test(SqlRunnerStateRepository $runnerStateRepository): JsonResponse
    {
        $runners = $runnerStateRepository->getDefaultRunnerState();
        return new JsonResponse($runners);
    }

    #[Route('/saveSteps', methods: ['POST'])]
    function saveSteps(SaveStepsCommand $command, RedisService $redisService): JsonResponse
    {
        $redisService->set($command->responseId, $command->stepsContent);
        return new JsonResponse(true);
    }
}
