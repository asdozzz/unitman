<?php

namespace App\Initialization\Infra\Controller;

use App\Initialization\Business\Command\ObnovitDefoltnyiProxyHost;
use App\Initialization\Business\Port\InitializationRepository;
use App\Initialization\Business\UseCase\ObnovitDefoltnyiProxyHostUseCase;
use App\Utils\Service\ExceptionTranslator;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/initialization')]
final class InitializationController extends AbstractController
{
    public function __construct(private ExceptionTranslator $exceptionTranslator)
    {
    }

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
                description: 'Пустой ответ: defoltnyi proxy host обновлён. При перехваченной ошибке PHP тело всё равно приходит с HTTP 200 в виде ErrorEnvelope',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(property: 'data', type: 'null', description: 'Пустой ответ: defoltnyi proxy host обновлён'),
                    ],
                    type: 'object'
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
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($this->exceptionTranslator->translateThrowable($e)));
        }
    }

    #[OA\Get(
        path: '/api/initialization/list',
        summary: 'Список параметров инициализации',
        tags: ['Initialization'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Параметры инициализации. При перехваченной ошибке PHP тело всё равно приходит с HTTP 200 в виде ErrorEnvelope',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(
                            property: 'data',
                            description: 'Параметры инициализации',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/InitializationRecord')
                        ),
                    ],
                    type: 'object'
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
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($this->exceptionTranslator->translateThrowable($e)));
        }
    }
}
