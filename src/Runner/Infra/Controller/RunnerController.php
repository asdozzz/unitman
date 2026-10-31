<?php

namespace App\Runner\Infra\Controller;

use App\Runner\Business\Command\SaveStepsCommand;
use App\Runner\Infra\Repository\SqlRunnerStateRepository;
use App\Runner\Infra\Service\RedisService;
use OpenApi\Attributes as OA;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/runner')]
final class RunnerController extends AbstractController
{
    #[OA\Post(
        path: '/api/runner/getDefaultRunnerActive',
        summary: 'Признак активности раннера по умолчанию',
        tags: ['Runner'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Состояние раннера',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'active', type: 'boolean')],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
        ]
    )]
    #[Route('/getDefaultRunnerActive', methods: ['POST'])]
    function getDefaultRunnerActive(SqlRunnerStateRepository $runnerStateRepository): JsonResponse
    {
        $runnerState = $runnerStateRepository->getDefaultRunnerState();
        return new JsonResponse(['active' => $runnerState->isActive()]);
    }

    #[OA\Get(
        path: '/api/runner/test',
        summary: 'Диагностическое состояние раннера',
        tags: ['Runner'],
        responses: [
            new OA\Response(response: 200, description: 'Состояние раннера'),
            new OA\Response(response: 401, description: 'Не авторизован'),
        ]
    )]
    #[Route('/test', methods: ['GET'])]
    function test(SqlRunnerStateRepository $runnerStateRepository): JsonResponse
    {
        $runners = $runnerStateRepository->getDefaultRunnerState();
        return new JsonResponse($runners);
    }

    #[OA\Post(
        path: '/api/runner/saveSteps',
        summary: 'Сохранить шаги выполнения задачи',
        description: 'Вызывается Golang-раннером, публичный эндпоинт без JWT',
        tags: ['Runner'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/SaveStepsCommand')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Признак успешного сохранения',
                content: new OA\JsonContent(type: 'boolean', example: true)
            ),
        ],
        security: []
    )]
    #[Route('/saveSteps', methods: ['POST'])]
    function saveSteps(SaveStepsCommand $command, RedisService $redisService): JsonResponse
    {
        $redisService->set($command->responseId, $command->stepsContent);
        return new JsonResponse(true);
    }
}
