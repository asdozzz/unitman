<?php
declare(strict_types=1);

namespace App\Unitman\Infra\Controller;

use App\Unitman\Business\Command\Project\Webhook\IzmenitUrlWebhooka;
use App\Unitman\Business\Command\Project\Webhook\PoluchitSobitiyaWebhooka;
use App\Unitman\Business\Command\Project\Webhook\SozdatWebhookProekta;
use App\Unitman\Business\Port\Project\WebhookProjectRepository;
use App\Unitman\Business\UseCase\Project\Webhook\DobavitWebhookProektaUseCase;
use App\Unitman\Business\UseCase\Project\Webhook\IzmenitUrlWebhookaUseCase;
use App\Unitman\Business\UseCase\Project\Webhook\OtkluchitWebhookProektaUseCase;
use App\Unitman\Business\UseCase\Project\Webhook\PoluchitSpisokWebhookovProektaDlyAdmininstrirovaniya;
use App\Unitman\Business\UseCase\Project\Webhook\UdalitWebhookProektaUseCase;
use App\Unitman\Business\UseCase\Project\Webhook\VkluchitWebhookProektaUseCase;
use App\Unitman\Business\UseCase\Unit\WebhookEvent\PoluchitSobitiyaWebhookaUseCase;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/project/webhook')]
final class ProjectWebhookController extends AbstractController
{
    #[OA\Post(
        path: '/api/project/webhook/dobavit',
        summary: "Добавить webhook проекту",
        tags: ['Webhook'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/SozdatWebhookProekta')
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
    #[Route('/dobavit', methods: ['POST'])]
    function add(SozdatWebhookProekta $command, DobavitWebhookProektaUseCase $useCase): JsonResponse
    {
        try {
            $id = $useCase->handle($command);
            return $this->json(\App\Utils\Model\Reponse\Response::success(['id' => $id]));
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }
    }

    #[OA\Post(
        path: '/api/project/webhook/{projectId}/spisok',
        summary: "Список webhook'ов проекта (для администрирования)",
        tags: ['Webhook'],
        parameters: [
            new OA\Parameter(name: 'projectId', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
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
    #[Route('/{projectId}/spisok', methods: ['POST'])]
    function spisok(string $projectId, PoluchitSpisokWebhookovProektaDlyAdmininstrirovaniya $useCase): JsonResponse
    {
        try {
            return $this->json(\App\Utils\Model\Reponse\Response::success($useCase->handle($projectId)));
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }
    }

    #[OA\Post(
        path: '/api/project/webhook/{id}/otkluchit',
        summary: 'Отключить webhook',
        tags: ['Webhook'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
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
    #[Route('/{id}/otkluchit', methods: ['POST'])]
    function otkluchit(string $id, OtkluchitWebhookProektaUseCase $useCase): JsonResponse
    {
        try {
            $useCase->handle($id);
            return $this->json(\App\Utils\Model\Reponse\Response::successStub());
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }
    }

    #[OA\Post(
        path: '/api/project/webhook/{id}/vkluchit',
        summary: 'Включить webhook',
        tags: ['Webhook'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
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
    #[Route('/{id}/vkluchit', methods: ['POST'])]
    function vkluchit(string $id, VkluchitWebhookProektaUseCase $useCase): JsonResponse
    {
        try {
            $useCase->handle($id);
            return $this->json(\App\Utils\Model\Reponse\Response::successStub());
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }
    }

    #[OA\Post(
        path: '/api/project/webhook/{id}/udalit',
        summary: 'Удалить webhook',
        tags: ['Webhook'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
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
    #[Route('/{id}/udalit', methods: ['POST'])]
    function udalit(string $id, UdalitWebhookProektaUseCase $useCase): JsonResponse
    {
        try {
            $useCase->handle($id);
            return $this->json(\App\Utils\Model\Reponse\Response::successStub());
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }
    }

    #[OA\Post(
        path: '/api/project/webhook/{id}/read',
        summary: 'Webhook по идентификатору',
        tags: ['Webhook'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
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
    #[Route('/{id}/read', methods: ['POST'])]
    function read(string $id, WebhookProjectRepository $repository): JsonResponse
    {
        try {
            return $this->json(\App\Utils\Model\Reponse\Response::success($repository->getReadModelById($id)));
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }
    }

    #[OA\Post(
        path: '/api/project/webhook/obnovit',
        summary: "Изменить URL webhook'а",
        tags: ['Webhook'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/IzmenitUrlWebhooka')
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
    #[Route('/obnovit', methods: ['POST'])]
    function obnovit(IzmenitUrlWebhooka $command, IzmenitUrlWebhookaUseCase $useCase): JsonResponse
    {
        try {
            $useCase->handle($command);
            return $this->json(\App\Utils\Model\Reponse\Response::successStub());
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }
    }

    #[OA\Post(
        path: '/api/project/webhook/poluchitSobitiyaWebhooka',
        summary: "События webhook'а с пагинацией",
        tags: ['Webhook'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/PoluchitSobitiyaWebhooka')
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
    #[Route('/poluchitSobitiyaWebhooka', methods: ['POST'])]
    function poluchitSobitiyaWebhooka(PoluchitSobitiyaWebhooka $command, PoluchitSobitiyaWebhookaUseCase $useCase): JsonResponse
    {
        try {
            return $this->json(\App\Utils\Model\Reponse\Response::success($useCase->handle($command)));
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }
    }
}
