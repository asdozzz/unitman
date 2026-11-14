<?php

declare(strict_types=1);

namespace App\OpenCode\Infra\Controller;

use App\OpenCode\Business\Command\NachatPrompt;
use App\OpenCode\Business\Command\OtvetitNaVopros;
use App\OpenCode\Business\Command\PoluchitIstoriyuSessii;
use App\OpenCode\Business\Command\PoluchitProgressHoda;
use App\OpenCode\Business\Command\PoluchitResultatHoda;
use App\OpenCode\Business\Command\SozdatSessiyu;
use App\OpenCode\Business\UseCase\NachatPromptUseCase;
use App\OpenCode\Business\UseCase\OtvetitNaVoprosUseCase;
use App\OpenCode\Business\UseCase\PoluchitIstoriyuSessiiQuery;
use App\OpenCode\Business\UseCase\PoluchitProgressHodaQuery;
use App\OpenCode\Business\UseCase\PoluchitResultatHodaQuery;
use App\OpenCode\Business\UseCase\PoluchitSpisokSessiyQuery;
use App\OpenCode\Business\ReadModel\HodOpenCodeReadModel;
use App\OpenCode\Business\UseCase\SozdatSessiyuUseCase;
use App\Utils\Model\Reponse\Response;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Сессии и ходы OpenCode.
 *
 * Все маршруты закрыты JWT: владелец сессии берётся из токена и проверяется
 * в UseCase, поэтому подставить чужой `sessionId` бесполезно — ответ будет
 * тем же, что и на несуществующую сессию.
 */
#[Route('/api/opencode')]
final class OpenCodeController extends AbstractController
{
    #[OA\Post(
        path: '/api/opencode/sozdatSessiyu',
        summary: 'Создать сессию OpenCode',
        tags: ['OpenCode'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/SozdatSessiyu')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Идентификатор созданной сессии',
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
    #[Route('/sozdatSessiyu', methods: ['POST'])]
    function sozdatSessiyu(SozdatSessiyu $command, SozdatSessiyuUseCase $useCase): HttpResponse
    {
        return $this->json(Response::success(['id' => $useCase->handle($command)]));
    }

    #[OA\Get(
        path: '/api/opencode/spisokSessiy',
        summary: 'Список сессий текущего пользователя',
        tags: ['OpenCode'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Сессии текущего пользователя',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/OpenCodeSession')
                        ),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки (FailEnvelope либо ErrorEnvelope)'),
        ]
    )]
    #[Route('/spisokSessiy', methods: ['GET'])]
    function spisokSessiy(PoluchitSpisokSessiyQuery $query): HttpResponse
    {
        return $this->json(Response::success($query->handle()));
    }

    #[OA\Post(
        path: '/api/opencode/prompt',
        summary: 'Отправить промпт и запустить ход',
        tags: ['OpenCode'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/NachatPrompt')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Идентификатор запущенного хода',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/OpenCodeTurnLaunch'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки (FailEnvelope либо ErrorEnvelope)'),
        ]
    )]
    #[Route('/prompt', methods: ['POST'])]
    function prompt(NachatPrompt $command, NachatPromptUseCase $useCase): HttpResponse
    {
        return $this->json(Response::success($useCase->handle($command)->toArray()));
    }

    #[OA\Post(
        path: '/api/opencode/otvetit',
        summary: 'Ответить на вопрос OpenCode и запустить новый ход',
        tags: ['OpenCode'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/OtvetitNaVopros')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Идентификатор запущенного хода',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/OpenCodeTurnLaunch'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки (FailEnvelope либо ErrorEnvelope)'),
        ]
    )]
    #[Route('/otvetit', methods: ['POST'])]
    function otvetit(OtvetitNaVopros $command, OtvetitNaVoprosUseCase $useCase): HttpResponse
    {
        return $this->json(Response::success($useCase->handle($command)->toArray()));
    }

    #[OA\Post(
        path: '/api/opencode/poluchitResultatHoda',
        summary: 'Опросить результат хода',
        tags: ['OpenCode'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/PoluchitResultatHoda')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Состояние хода: пока RUNNING — результата ещё нет',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/OpenCodeTurn'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки (FailEnvelope либо ErrorEnvelope)'),
        ]
    )]
    #[Route('/poluchitResultatHoda', methods: ['POST'])]
    function poluchitResultatHoda(
        PoluchitResultatHoda $command,
        PoluchitResultatHodaQuery $query,
    ): HttpResponse {
        return $this->json(Response::success($query->handle($command)->toArray()));
    }

    #[OA\Post(
        path: '/api/opencode/poluchitProgressHoda',
        summary: 'Снапшот шагов выполнения хода',
        tags: ['OpenCode'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/PoluchitProgressHoda')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Шаги из KV раннера; null — прогресса ещё нет',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/OpenCodeTurnProgress', nullable: true),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки (FailEnvelope либо ErrorEnvelope)'),
        ]
    )]
    #[Route('/poluchitProgressHoda', methods: ['POST'])]
    function poluchitProgressHoda(
        PoluchitProgressHoda $command,
        PoluchitProgressHodaQuery $query,
    ): HttpResponse {
        return $this->json(Response::success($query->handle($command)?->toArray()));
    }

    #[OA\Post(
        path: '/api/opencode/istoriyaSessii',
        summary: 'История ходов сессии',
        tags: ['OpenCode'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/PoluchitIstoriyuSessii')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Ходы сессии по возрастанию seq',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/OpenCodeTurn')
                        ),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки (FailEnvelope либо ErrorEnvelope)'),
        ]
    )]
    #[Route('/istoriyaSessii', methods: ['POST'])]
    function istoriyaSessii(
        PoluchitIstoriyuSessii $command,
        PoluchitIstoriyuSessiiQuery $query,
    ): HttpResponse {
        $turns = array_map(
            static fn (HodOpenCodeReadModel $turn): array => $turn->toArray(),
            $query->handle($command),
        );

        return $this->json(Response::success($turns));
    }
}
