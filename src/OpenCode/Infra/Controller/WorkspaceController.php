<?php

declare(strict_types=1);

namespace App\OpenCode\Infra\Controller;

use App\OpenCode\Business\Command\ArhivirovatWorkspace;
use App\OpenCode\Business\Command\DobavitProektyVWorkspace;
use App\OpenCode\Business\Command\PoluchitSostoyanieWorkspace;
use App\OpenCode\Business\Command\PoluchitSpisokWorkspace;
use App\OpenCode\Business\Command\PoluchitSsylkuWorkspace;
use App\OpenCode\Business\Command\PrikrepitFaylyKWorkspace;
use App\OpenCode\Business\Command\SozdatUnitiWorkspace;
use App\OpenCode\Business\Command\SozdatWorkspace;
use App\OpenCode\Business\Command\UdalitFaylWorkspace;
use App\OpenCode\Business\Command\UdalitWorkspace;
use App\OpenCode\Business\Command\VosstanovitWorkspace;
use App\OpenCode\Business\ReadModel\WorkspaceReadModel;
use App\OpenCode\Business\ReadModel\WorkspaceUnitReadModel;
use App\OpenCode\Business\UseCase\ArhivirovatWorkspaceUseCase;
use App\OpenCode\Business\UseCase\DobavitProektyVWorkspaceUseCase;
use App\OpenCode\Business\UseCase\PoluchitSostoyanieWorkspaceQuery;
use App\OpenCode\Business\UseCase\PoluchitSpisokWorkspaceQuery;
use App\OpenCode\Business\UseCase\PoluchitSsylkuWorkspaceQuery;
use App\OpenCode\Business\UseCase\PrikrepitFaylyKWorkspaceUseCase;
use App\OpenCode\Business\UseCase\SozdatUnitiWorkspaceUseCase;
use App\OpenCode\Business\UseCase\SozdatWorkspaceUseCase;
use App\OpenCode\Business\UseCase\UdalitFaylWorkspaceUseCase;
use App\OpenCode\Business\UseCase\UdalitWorkspaceUseCase;
use App\OpenCode\Business\UseCase\VosstanovitWorkspaceUseCase;
use App\Utils\Model\Reponse\Response;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Workspace-сессии OpenCode.
 *
 * Промпты, ответы и история используют существующие маршруты `/api/opencode/*`:
 * ид сессии workspace и есть ид сессии OpenCode.
 */
#[Route('/api/opencode/workspace')]
final class WorkspaceController extends AbstractController
{
    #[OA\Post(
        path: '/api/opencode/workspace/sozdat',
        summary: 'Создать workspace-сессию',
        tags: ['OpenCode'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/SozdatWorkspace')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Идентификатор и статус созданного workspace',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            required: ['id', 'status'],
                            properties: [
                                new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                                new OA\Property(
                                    property: 'status',
                                    type: 'string',
                                    enum: ['CREATING', 'STARTING', 'RUNNING', 'ARCHIVING', 'ARCHIVED', 'DELETING', 'ERROR'],
                                    example: 'CREATING'
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
    #[Route('/sozdat', methods: ['POST'])]
    function sozdat(
        SozdatWorkspace $command,
        SozdatWorkspaceUseCase $useCase,
        PoluchitSostoyanieWorkspaceQuery $stateQuery,
    ): HttpResponse {
        $id = $useCase->handle($command);
        $state = $stateQuery->handle(new PoluchitSostoyanieWorkspace($id));

        return $this->json(Response::success(['id' => $id, 'status' => $state->status]));
    }

    #[OA\Post(
        path: '/api/opencode/workspace/sozdatUniti',
        summary: 'Создать юниты для проектов workspace',
        description: 'Создаёт по одному юниту на каждый проект workspace и автоматически связывает хелперы, указывающие на проекты этого workspace. Юниты запускаются штатным конвейером.',
        tags: ['OpenCode'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/SozdatUnitiWorkspace')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Созданные юниты',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'units',
                                    type: 'array',
                                    items: new OA\Items(ref: '#/components/schemas/WorkspaceUnit')
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
    #[Route('/sozdatUniti', methods: ['POST'])]
    function sozdatUniti(
        SozdatUnitiWorkspace $command,
        SozdatUnitiWorkspaceUseCase $useCase,
    ): HttpResponse {
        $units = $useCase->handle($command);

        return $this->json(Response::success([
            'units' => array_map(
                static fn(WorkspaceUnitReadModel $unit): array => $unit->toArray(),
                $units,
            ),
        ]));
    }

    #[OA\Post(
        path: '/api/opencode/workspace/dobavitProekt',
        summary: 'Добавить проект в работающий workspace',
        tags: ['OpenCode'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/DobavitProektyVWorkspace')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Обновление запущено'),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки'),
        ]
    )]
    #[Route('/dobavitProekt', methods: ['POST'])]
    function dobavitProekt(
        DobavitProektyVWorkspace $command,
        DobavitProektyVWorkspaceUseCase $useCase,
    ): HttpResponse {
        $useCase->handle($command);

        return $this->json(Response::success(['updating' => true]));
    }

    #[OA\Post(
        path: '/api/opencode/workspace/fayly/prikrepit',
        summary: 'Прикрепить файлы к работающему workspace',
        tags: ['OpenCode'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/PrikrepitFaylyKWorkspace')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Обновление запущено'),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки'),
        ]
    )]
    #[Route('/fayly/prikrepit', methods: ['POST'])]
    function prikrepitFayly(
        PrikrepitFaylyKWorkspace $command,
        PrikrepitFaylyKWorkspaceUseCase $useCase,
    ): HttpResponse {
        $useCase->handle($command);

        return $this->json(Response::success(['updating' => true]));
    }

    #[OA\Post(
        path: '/api/opencode/workspace/fayly/udalit',
        summary: 'Удалить файл workspace',
        tags: ['OpenCode'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UdalitFaylWorkspace')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Обновление запущено'),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки'),
        ]
    )]
    #[Route('/fayly/udalit', methods: ['POST'])]
    function udalitFayl(
        UdalitFaylWorkspace $command,
        UdalitFaylWorkspaceUseCase $useCase,
    ): HttpResponse {
        $useCase->handle($command);

        return $this->json(Response::success(['updating' => true]));
    }

    #[OA\Get(
        path: '/api/opencode/workspace/spisok',
        summary: 'Список workspace-сессий текущего пользователя',
        tags: ['OpenCode'],
        parameters: [
            new OA\Parameter(
                name: 'onlyActive',
                description: 'Только неархивные workspace (по умолчанию показываются все)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'boolean', default: false)
            ),
            new OA\Parameter(
                name: 'search',
                description: 'Подстрока названия workspace (поиск через LIKE)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', nullable: true)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Workspace текущего пользователя',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/Workspace')
                        ),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки (FailEnvelope либо ErrorEnvelope)'),
        ]
    )]
    #[Route('/spisok', methods: ['GET'])]
    function spisok(Request $request, PoluchitSpisokWorkspaceQuery $query): HttpResponse
    {
        $search = trim($request->query->getString('search', ''));

        $workspaces = $query->handle(new PoluchitSpisokWorkspace(
            $request->query->getBoolean('onlyActive', false),
            $search === '' ? null : $search,
        ));

        return $this->json(Response::success(array_map(
            static fn (WorkspaceReadModel $workspace): array => $workspace->toArray(),
            $workspaces,
        )));
    }

    #[OA\Post(
        path: '/api/opencode/workspace/sostoyanie',
        summary: 'Состояние workspace: причина блокировки ходов и последний ход',
        tags: ['OpenCode'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/PoluchitSostoyanieWorkspace')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Состояние workspace для интерфейса',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/SostoyanieWorkspace'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки (FailEnvelope либо ErrorEnvelope)'),
        ]
    )]
    #[Route('/sostoyanie', methods: ['POST'])]
    function sostoyanie(
        PoluchitSostoyanieWorkspace $command,
        PoluchitSostoyanieWorkspaceQuery $query,
    ): HttpResponse {
        return $this->json(Response::success($query->handle($command)->toArray()));
    }

    #[OA\Post(
        path: '/api/opencode/workspace/arhivirovat',
        summary: 'Архивировать workspace (при незакоммиченных изменениях требует force)',
        tags: ['OpenCode'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ArhivirovatWorkspace')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Результат попытки архивации',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/RezultatArhivaciiWorkspace'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки (FailEnvelope либо ErrorEnvelope)'),
        ]
    )]
    #[Route('/arhivirovat', methods: ['POST'])]
    function arhivirovat(ArhivirovatWorkspace $command, ArhivirovatWorkspaceUseCase $useCase): HttpResponse
    {
        return $this->json(Response::success($useCase->handle($command)->toArray()));
    }

    #[OA\Post(
        path: '/api/opencode/workspace/vosstanovit',
        summary: 'Восстановить архивный workspace',
        tags: ['OpenCode'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/VosstanovitWorkspace')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Новый статус workspace',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            required: ['status'],
                            properties: [
                                new OA\Property(
                                    property: 'status',
                                    type: 'string',
                                    enum: ['CREATING', 'STARTING', 'RUNNING', 'ARCHIVING', 'ARCHIVED', 'DELETING', 'ERROR'],
                                    example: 'STARTING'
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
    #[Route('/vosstanovit', methods: ['POST'])]
    function vosstanovit(
        VosstanovitWorkspace $command,
        VosstanovitWorkspaceUseCase $useCase,
        PoluchitSostoyanieWorkspaceQuery $stateQuery,
    ): HttpResponse {
        $useCase->handle($command);
        $state = $stateQuery->handle(new PoluchitSostoyanieWorkspace($command->workspaceId));

        return $this->json(Response::success(['status' => $state->status]));
    }

    #[OA\Post(
        path: '/api/opencode/workspace/udalit',
        summary: 'Удалить workspace: контейнеры и тома удаляет Runner, записи БД — фоновая задача',
        tags: ['OpenCode'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UdalitWorkspace')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Пустой ответ: удаление запущено',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(property: 'data', type: 'null', description: 'Пустой ответ: удаление запущено'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки (FailEnvelope либо ErrorEnvelope)'),
        ]
    )]
    #[Route('/udalit', methods: ['POST'])]
    function udalit(UdalitWorkspace $command, UdalitWorkspaceUseCase $useCase): HttpResponse
    {
        $useCase->handle($command);

        return $this->json(Response::successStub());
    }

    #[OA\Post(
        path: '/api/opencode/workspace/ssylkaPoPortu',
        summary: 'Ссылка на произвольный порт workspace-контейнера',
        tags: ['OpenCode'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/PoluchitSsylkuWorkspace')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Ссылка на сервис workspace. При перехваченной ошибке PHP тело приходит с HTTP 200 в виде ErrorEnvelope',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/WorkspaceLinkHolder'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки (FailEnvelope либо ErrorEnvelope)'),
        ]
    )]
    #[Route('/ssylkaPoPortu', methods: ['POST'])]
    function ssylkaPoPortu(
        PoluchitSsylkuWorkspace $command,
        PoluchitSsylkuWorkspaceQuery $query,
    ): HttpResponse {
        return $this->json(Response::success($query->handle($command)->toArray()));
    }
}
