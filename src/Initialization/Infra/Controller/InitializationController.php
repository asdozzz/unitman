<?php

namespace App\Initialization\Infra\Controller;

use App\Initialization\Business\Command\ObnovitDefoltnyiProxyHost;
use App\Initialization\Business\Port\InitializationRepository;
use App\Initialization\Business\UseCase\ObnovitDefoltnyiProxyHostUseCase;
use App\Initialization\Infra\Repository\SqlInitializationRepository;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/initialization')]
final class InitializationController extends AbstractController
{
    #[OA\Put(
        path: '/api/initialization/proxy_host',
        summary: 'Обновить defoltnый proxy host',
        tags: ['Initialization'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ObnovitDefoltnyiProxyHost')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Успешный ответ либо ошибка',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(ref: '#/components/schemas/SuccessEnvelope'),
                        new OA\Schema(ref: '#/components/schemas/ErrorEnvelope'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
        ]
    )]
    #[Route('/proxy_host', methods: ['PUT'])]
    public function updateProxyHost(#[MapRequestPayload] ObnovitDefoltnyiProxyHost $command, ObnovitDefoltnyiProxyHostUseCase $useCase): Response
    {
        try {
            $useCase->handle($command);
            return $this->json(\App\Utils\Model\Reponse\Response::successStub());
        } catch (\Throwable $e) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($e->getMessage()));
        }
    }

    #[OA\Get(
        path: '/api/initialization/list',
        summary: 'Список параметров инициализации',
        tags: ['Initialization'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Успешный ответ либо ошибка',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(ref: '#/components/schemas/SuccessEnvelope'),
                        new OA\Schema(ref: '#/components/schemas/ErrorEnvelope'),
                    ]
                )
            ),
        ],
        security: []
    )]
    #[Route('/list', methods: ['GET'])]
    public function list(InitializationRepository $repository): JsonResponse
    {
        try {
            return $this->json(\App\Utils\Model\Reponse\Response::success($repository->getAll()));
        } catch (\Throwable $e) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($e->getMessage()));
        }
    }
}
