<?php

declare(strict_types=1);

namespace App\OpenCode\Infra\Controller;

use App\OpenCode\Business\Command\ArhivirovatSessiyu;
use App\OpenCode\Business\Command\NachatPrompt;
use App\OpenCode\Business\Command\OtvetitNaVopros;
use App\OpenCode\Business\Command\PoluchitIstoriyuSessii;
use App\OpenCode\Business\Command\PoluchitProgressHoda;
use App\OpenCode\Business\Command\PoluchitResultatHoda;
use App\OpenCode\Business\Command\PoluchitSostoyanieSessii;
use App\OpenCode\Business\Command\PoluchitSpisokSessiy;
use App\OpenCode\Business\Command\PrivezatSessiyuKUnitu;
use App\OpenCode\Business\Command\SozdatSessiyu;
use App\OpenCode\Business\ReadModel\HodOpenCodeReadModel;
use App\OpenCode\Business\UseCase\ArhivirovatSessiyuUseCase;
use App\OpenCode\Business\UseCase\NachatPromptUseCase;
use App\OpenCode\Business\UseCase\OtvetitNaVoprosUseCase;
use App\OpenCode\Business\UseCase\PoluchitIstoriyuSessiiQuery;
use App\OpenCode\Business\UseCase\PoluchitProgressHodaQuery;
use App\OpenCode\Business\UseCase\PoluchitResultatHodaQuery;
use App\OpenCode\Business\UseCase\PoluchitSostoyanieSessiiQuery;
use App\OpenCode\Business\UseCase\PoluchitSpisokSessiyQuery;
use App\OpenCode\Business\UseCase\PrivezatSessiyuKUnituUseCase;
use App\OpenCode\Business\UseCase\SozdatSessiyuUseCase;
use App\Utils\Model\Reponse\Response;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
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
        summary: 'Создать сессию OpenCode, при необходимости сразу с юнитом',
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

    #[OA\Post(
        path: '/api/opencode/privezatKUnitu',
        summary: 'Привязать свободную сессию к юниту',
        tags: ['OpenCode'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/PrivezatKUnitu')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Пустой ответ: сессия привязана к юниту',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(property: 'data', type: 'null', description: 'Пустой ответ: сессия привязана к юниту'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки (FailEnvelope либо ErrorEnvelope)'),
        ]
    )]
    #[Route('/privezatKUnitu', methods: ['POST'])]
    function privezatKUnitu(PrivezatSessiyuKUnitu $command, PrivezatSessiyuKUnituUseCase $useCase): HttpResponse
    {
        $useCase->handle($command);

        return $this->json(Response::successStub());
    }

    #[OA\Get(
        path: '/api/opencode/spisokSessiy',
        summary: 'Список сессий текущего пользователя',
        tags: ['OpenCode'],
        parameters: [
            new OA\Parameter(
                name: 'onlyActive',
                description: 'Только неархивные сессии (по умолчанию показываются все)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'boolean', default: false)
            ),
            new OA\Parameter(
                name: 'search',
                description: 'Подстрока названия сессии (поиск через LIKE)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', nullable: true)
            ),
        ],
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
    function spisokSessiy(Request $request, PoluchitSpisokSessiyQuery $query): HttpResponse
    {
        $search = trim($request->query->getString('search', ''));

        $command = new PoluchitSpisokSessiy(
            $request->query->getBoolean('onlyActive', false),
            $search === '' ? null : $search,
        );

        return $this->json(Response::success($query->handle($command)));
    }

    #[OA\Post(
        path: '/api/opencode/sostoyanieSessii',
        summary: 'Состояние сессии: причина блокировки ходов',
        tags: ['OpenCode'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/PoluchitSostoyanieSessii')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Состояние сессии для интерфейса',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/SostoyanieSessii'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки (FailEnvelope либо ErrorEnvelope)'),
        ]
    )]
    #[Route('/sostoyanieSessii', methods: ['POST'])]
    function sostoyanieSessii(
        PoluchitSostoyanieSessii $command,
        PoluchitSostoyanieSessiiQuery $query,
    ): HttpResponse {
        return $this->json(Response::success($query->handle($command)));
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

    #[OA\Post(
        path: '/api/opencode/arhivirovatSessiyu',
        summary: 'Архивировать сессию: остаётся в списке, новые ходы запрещены',
        tags: ['OpenCode'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ArhivirovatSessiyu')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Пустой ответ: сессия архивирована',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(property: 'data', type: 'null', description: 'Пустой ответ: сессия архивирована'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки (FailEnvelope либо ErrorEnvelope)'),
        ]
    )]
    #[Route('/arhivirovatSessiyu', methods: ['POST'])]
    function arhivirovatSessiyu(ArhivirovatSessiyu $command, ArhivirovatSessiyuUseCase $useCase): HttpResponse
    {
        $useCase->handle($command);

        return $this->json(Response::successStub());
    }

    #[OA\Post(
        path: '/api/opencode/udalitSessiyu',
        summary: 'Устаревший алиас архивации сессии',
        description: 'Полное удаление сессии заменено архивацией; тело и ответ совпадают с `arhivirovatSessiyu`.',
        deprecated: true,
        tags: ['OpenCode'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UdalitSessiyu')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Пустой ответ: сессия архивирована',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(property: 'data', type: 'null', description: 'Пустой ответ: сессия архивирована'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки (FailEnvelope либо ErrorEnvelope)'),
        ]
    )]
    #[Route('/udalitSessiyu', methods: ['POST'])]
    function udalitSessiyu(ArhivirovatSessiyu $command, ArhivirovatSessiyuUseCase $useCase): HttpResponse
    {
        $useCase->handle($command);

        return $this->json(Response::successStub());
    }
}
