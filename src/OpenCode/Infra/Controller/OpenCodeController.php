<?php

declare(strict_types=1);

namespace App\OpenCode\Infra\Controller;

use App\OpenCode\Business\Command\ArhivirovatSessiyu;
use App\OpenCode\Business\Command\NachatPrompt;
use App\OpenCode\Business\Command\OtmetitUvedomleniyaProchitannimi;
use App\OpenCode\Business\Command\OtvetitNaVopros;
use App\OpenCode\Business\Command\PoluchitIstoriyuSessii;
use App\OpenCode\Business\Command\PoluchitProgressHoda;
use App\OpenCode\Business\Command\PoluchitResultatHoda;
use App\OpenCode\Business\Command\PoluchitSostoyanieSessii;
use App\OpenCode\Business\Command\PoluchitSpisokSessiy;
use App\OpenCode\Business\Command\PoluchitUvedomleniyaOpenCode;
use App\OpenCode\Business\Command\PrivezatSessiyuKUnitu;
use App\OpenCode\Business\Command\SozdatSessiyu;
use App\OpenCode\Business\ReadModel\HodOpenCodeReadModel;
use App\OpenCode\Business\UseCase\ArhivirovatSessiyuUseCase;
use App\OpenCode\Business\UseCase\NachatPromptUseCase;
use App\OpenCode\Business\UseCase\OtmetitUvedomleniyaProchitannimiUseCase;
use App\OpenCode\Business\UseCase\OtvetitNaVoprosUseCase;
use App\OpenCode\Business\UseCase\PoluchitIstoriyuSessiiQuery;
use App\OpenCode\Business\UseCase\PoluchitProgressHodaQuery;
use App\OpenCode\Business\UseCase\PoluchitResultatHodaQuery;
use App\OpenCode\Business\UseCase\PoluchitSostoyanieSessiiQuery;
use App\OpenCode\Business\UseCase\PoluchitSpisokSessiyQuery;
use App\OpenCode\Business\UseCase\PoluchitUvedomleniyaOpenCodeQuery;
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
            new OA\Parameter(
                name: 'type',
                description: 'Тип сессии: unit или workspace (по умолчанию — все)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['unit', 'workspace'], nullable: true)
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
        $type = trim($request->query->getString('type', ''));

        $command = new PoluchitSpisokSessiy(
            $request->query->getBoolean('onlyActive', false),
            $search === '' ? null : $search,
            $type === '' ? null : $type,
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

    #[OA\Get(
        path: '/api/opencode/uvedomleniya',
        summary: 'Непрочитанные уведомления OpenCode и имя канала',
        tags: ['OpenCode'],
        parameters: [
            new OA\Parameter(
                name: 'limit',
                description: 'Максимум уведомлений: 1..100, по умолчанию 50',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', default: 50, minimum: 1, maximum: 100)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Канал уведомлений и непрочитанные уведомления текущего пользователя',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            required: ['channel', 'notifications'],
                            properties: [
                                new OA\Property(
                                    property: 'channel',
                                    type: 'string',
                                    description: 'Готовое имя персонального Centrifugo-канала',
                                    example: 'opencode_notifications#6f1c5e0a7d1f4d599a540c2f8a2e6f11'
                                ),
                                new OA\Property(
                                    property: 'notifications',
                                    type: 'array',
                                    items: new OA\Items(
                                        type: 'object',
                                        required: ['eventType', 'id', 'sessionId', 'sessionType', 'turnId', 'sessionTitle', 'status', 'kind', 'createdAt'],
                                        properties: [
                                            new OA\Property(property: 'eventType', type: 'string', enum: ['new_message'], example: 'new_message'),
                                            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                                            new OA\Property(property: 'sessionId', type: 'string', format: 'uuid'),
                                            new OA\Property(property: 'sessionType', type: 'string', enum: ['unit', 'workspace'], example: 'unit'),
                                            new OA\Property(property: 'turnId', type: 'string', format: 'uuid'),
                                            new OA\Property(property: 'sessionTitle', type: 'string', example: 'Рефакторинг модуля биллинга'),
                                            new OA\Property(property: 'status', type: 'string', enum: ['ANSWERED', 'AWAITING_INPUT', 'FAILED'], example: 'ANSWERED'),
                                            new OA\Property(property: 'kind', type: 'string', enum: ['ANSWER', 'QUESTION', 'ERROR'], nullable: true),
                                            new OA\Property(property: 'createdAt', type: 'integer', example: 1762000000),
                                        ]
                                    )
                                ),
                            ]
                        ),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки (FailEnvelope либо ErrorEnvelope)'),
        ]
    )]
    #[Route('/uvedomleniya', methods: ['GET'])]
    function uvedomleniya(Request $request, PoluchitUvedomleniyaOpenCodeQuery $query): HttpResponse
    {
        $command = new PoluchitUvedomleniyaOpenCode(
            $request->query->getInt('limit', PoluchitUvedomleniyaOpenCode::DEFAULT_LIMIT),
        );

        return $this->json(Response::success($query->handle($command)->toArray()));
    }

    #[OA\Post(
        path: '/api/opencode/otmetitUvedomleniyaProchitannimi',
        summary: 'Захватить уведомления и пометить их прочитанными',
        description: 'Возвращает только идентификаторы, которые именно этот запрос перевёл из непрочитанных в прочитанные: чужие, уже прочитанные и повторяющиеся идентификаторы отсутствуют.',
        tags: ['OpenCode'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['ids'],
                properties: [
                    new OA\Property(
                        property: 'ids',
                        type: 'array',
                        minItems: 1,
                        maxItems: 100,
                        uniqueItems: true,
                        items: new OA\Items(type: 'string', format: 'uuid')
                    ),
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Идентификаторы уведомлений, реально переведённых в прочитанные',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            required: ['ids'],
                            properties: [
                                new OA\Property(
                                    property: 'ids',
                                    type: 'array',
                                    items: new OA\Items(type: 'string', format: 'uuid')
                                ),
                            ]
                        ),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки (FailEnvelope либо ErrorEnvelope)'),
        ]
    )]
    #[Route('/otmetitUvedomleniyaProchitannimi', methods: ['POST'])]
    function otmetitUvedomleniyaProchitannimi(
        OtmetitUvedomleniyaProchitannimi $command,
        OtmetitUvedomleniyaProchitannimiUseCase $useCase,
    ): HttpResponse {
        return $this->json(Response::success(['ids' => $useCase->handle($command)]));
    }
}
