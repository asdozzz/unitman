<?php

namespace App\Runner\Infra\Controller;

use App\Runner\Infra\Repository\SqlRunnerStateRepository;
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
}
