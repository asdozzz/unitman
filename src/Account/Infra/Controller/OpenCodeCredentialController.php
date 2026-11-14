<?php

declare(strict_types=1);

namespace App\Account\Infra\Controller;

use App\Account\Business\Command\IzmenitRekvizityOpenCode;
use App\Account\Business\Command\SozdatRekvizityOpenCode;
use App\Account\Business\Command\UdalitRekvizityOpenCode;
use App\Account\Business\UseCase\OpenCode\IzmenitRekvizityOpenCodeUseCase;
use App\Account\Business\UseCase\OpenCode\PoluchitSpisokRekvizitovOpenCodeQuery;
use App\Account\Business\UseCase\OpenCode\SozdatRekvizityOpenCodeUseCase;
use App\Account\Business\UseCase\OpenCode\UdalitRekvizityOpenCodeUseCase;
use App\Utils\Model\Reponse\Response;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Управление реквизитами подключения к OpenCode.
 *
 * Все операции работают только со своим аккаунтом: владелец берётся из JWT,
 * подставить чужой `account_id` через тело запроса невозможно.
 * API-ключ наружу не отдаётся ни в одном ответе — только маска `apiKeyHint`.
 */
#[Route('/api/account/opencode')]
final class OpenCodeCredentialController extends AbstractController
{
    #[OA\Get(
        path: '/api/account/opencode/spisokRekvizitov',
        summary: 'Список реквизитов OpenCode текущего пользователя',
        tags: ['Account'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Список реквизитов (API-ключ замаскирован)',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(
                            property: 'data',
                            description: 'Реквизиты OpenCode текущего пользователя',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/OpenCodeCredential')
                        ),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки (FailEnvelope либо ErrorEnvelope)'),
        ]
    )]
    #[Route('/spisokRekvizitov', methods: ['GET'])]
    function spisokRekvizitov(PoluchitSpisokRekvizitovOpenCodeQuery $query): HttpResponse
    {
        return $this->json(Response::success($query->handle()));
    }

    #[OA\Post(
        path: '/api/account/opencode/sozdatRekvizity',
        summary: 'Добавить реквизиты OpenCode',
        tags: ['Account'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/SozdatRekvizityOpenCode')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Идентификатор созданных реквизитов',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/IdHolder'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки (FailEnvelope либо ErrorEnvelope)'),
        ]
    )]
    #[Route('/sozdatRekvizity', methods: ['POST'])]
    function sozdatRekvizity(SozdatRekvizityOpenCode $command, SozdatRekvizityOpenCodeUseCase $useCase): HttpResponse
    {
        return $this->json(Response::success(['id' => $useCase->handle($command)]));
    }

    #[OA\Post(
        path: '/api/account/opencode/izmenitRekvizity',
        summary: 'Изменить реквизиты OpenCode',
        tags: ['Account'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/IzmenitRekvizityOpenCode')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Пустой ответ: реквизиты изменены',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(property: 'data', type: 'null', description: 'Пустой ответ: реквизиты изменены'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки (FailEnvelope либо ErrorEnvelope)'),
        ]
    )]
    #[Route('/izmenitRekvizity', methods: ['POST'])]
    function izmenitRekvizity(IzmenitRekvizityOpenCode $command, IzmenitRekvizityOpenCodeUseCase $useCase): HttpResponse
    {
        $useCase->handle($command);

        return $this->json(Response::successStub());
    }

    #[OA\Post(
        path: '/api/account/opencode/udalitRekvizity',
        summary: 'Удалить реквизиты OpenCode',
        tags: ['Account'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UdalitRekvizityOpenCode')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Пустой ответ: реквизиты удалены',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(property: 'data', type: 'null', description: 'Пустой ответ: реквизиты удалены'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки (FailEnvelope либо ErrorEnvelope)'),
        ]
    )]
    #[Route('/udalitRekvizity', methods: ['POST'])]
    function udalitRekvizity(UdalitRekvizityOpenCode $command, UdalitRekvizityOpenCodeUseCase $useCase): HttpResponse
    {
        $useCase->handle($command);

        return $this->json(Response::successStub());
    }
}
