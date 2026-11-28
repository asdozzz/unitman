<?php

declare(strict_types=1);

namespace App\OpenCode\Infra\Controller;

use App\OpenCode\Business\ReadModel\HodOpenCodeReadModel;
use App\OpenCode\Business\Workspace\UseCase\NachatPromptWorkspaceUseCase;
use App\OpenCode\Business\Workspace\UseCase\OtvetitNaVoprosWorkspaceUseCase;
use App\OpenCode\Business\Workspace\UseCase\PoluchitIstoriyuWorkspaceQuery;
use App\OpenCode\Business\Workspace\UseCase\PoluchitProgressHodaWorkspaceQuery;
use App\OpenCode\Business\Workspace\UseCase\PoluchitResultatHodaWorkspaceQuery;
use App\OpenCode\Business\Workspace\UseCase\PoluchitSostoyanieWorkspaceSessiiQuery;
use App\Utils\Model\Reponse\Response;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Чат workspace на самостоятельном агрегате.
 *
 * Жизненный цикл workspace (создание, архив, восстановление, удаление)
 * обслуживает существующий WorkspaceController; здесь только диалог.
 */
#[Route('/api/opencode/workspace-session')]
final class WorkspaceSessionController extends AbstractController
{
    #[OA\Post(
        path: '/api/opencode/workspace-session/prompt',
        summary: 'Запустить ход workspace',
        tags: ['Workspace'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['workspaceId', 'text'],
                properties: [
                    new OA\Property(property: 'workspaceId', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'text', type: 'string'),
                    new OA\Property(property: 'model', type: 'string', nullable: true, description: 'Переопределение модели перед ходом'),
                    new OA\Property(property: 'credentialId', type: 'string', nullable: true, description: 'Переопределение реквизитов перед ходом'),
                    new OA\Property(property: 'agentModeId', type: 'string', nullable: true, description: 'Мод агента для хода'),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Запуск хода')]
    )]
    #[Route('/prompt', methods: ['POST'])]
    public function prompt(Request $request, NachatPromptWorkspaceUseCase $useCase): HttpResponse
    {
        $data = $request->toArray();
        $result = $useCase->handle(
            (string) ($data['workspaceId'] ?? ''),
            (string) ($data['text'] ?? ''),
            is_string($data['model'] ?? null) ? $data['model'] : null,
            is_string($data['credentialId'] ?? null) ? $data['credentialId'] : null,
            is_string($data['agentModeId'] ?? null) ? $data['agentModeId'] : null,
        );

        return $this->json(Response::success([
            'id' => $result->turnId,
            'status' => $result->status,
            'progressChannel' => $result->progressChannel,
        ]));
    }

    #[OA\Post(
        path: '/api/opencode/workspace-session/otvetit',
        summary: 'Ответить на вопросы хода workspace',
        tags: ['Workspace'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['turnId', 'answers'],
                properties: [
                    new OA\Property(property: 'turnId', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'answers', type: 'array', items: new OA\Items(type: 'object')),
                    new OA\Property(property: 'model', type: 'string', nullable: true, description: 'Переопределение модели перед ходом'),
                    new OA\Property(property: 'credentialId', type: 'string', nullable: true, description: 'Переопределение реквизитов перед ходом'),
                    new OA\Property(property: 'agentModeId', type: 'string', nullable: true, description: 'Мод агента для хода'),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Запуск хода')]
    )]
    #[Route('/otvetit', methods: ['POST'])]
    public function otvetit(Request $request, OtvetitNaVoprosWorkspaceUseCase $useCase): HttpResponse
    {
        $data = $request->toArray();
        $answers = is_array($data['answers'] ?? null) ? $data['answers'] : [];

        $result = $useCase->handle(
            (string) ($data['turnId'] ?? ''),
            $answers,
            is_string($data['model'] ?? null) ? $data['model'] : null,
            is_string($data['credentialId'] ?? null) ? $data['credentialId'] : null,
            is_string($data['agentModeId'] ?? null) ? $data['agentModeId'] : null,
        );

        return $this->json(Response::success([
            'id' => $result->turnId,
            'status' => $result->status,
            'progressChannel' => $result->progressChannel,
        ]));
    }

    #[OA\Post(
        path: '/api/opencode/workspace-session/istoriya',
        summary: 'История ходов workspace',
        tags: ['Workspace'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object')),
        responses: [new OA\Response(response: 200, description: 'История ходов')]
    )]
    #[Route('/istoriya', methods: ['POST'])]
    public function istoriya(Request $request, PoluchitIstoriyuWorkspaceQuery $query): HttpResponse
    {
        $data = $request->toArray();

        return $this->json(Response::success(array_map(
            static fn (HodOpenCodeReadModel $turn): array => $turn->toArray(),
            $query->handle((string) ($data['workspaceId'] ?? '')),
        )));
    }

    #[OA\Post(
        path: '/api/opencode/workspace-session/sostoyanie',
        summary: 'Состояние чата workspace',
        tags: ['Workspace'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object')),
        responses: [new OA\Response(response: 200, description: 'Состояние workspace')]
    )]
    #[Route('/sostoyanie', methods: ['POST'])]
    public function sostoyanie(Request $request, PoluchitSostoyanieWorkspaceSessiiQuery $query): HttpResponse
    {
        $data = $request->toArray();

        return $this->json(Response::success(
            $query->handle((string) ($data['workspaceId'] ?? '')),
        ));
    }

    #[OA\Post(
        path: '/api/opencode/workspace-session/poluchitResultatHoda',
        summary: 'Результат хода workspace',
        tags: ['Workspace'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object')),
        responses: [new OA\Response(response: 200, description: 'Состояние хода')]
    )]
    #[Route('/poluchitResultatHoda', methods: ['POST'])]
    public function poluchitResultatHoda(Request $request, PoluchitResultatHodaWorkspaceQuery $query): HttpResponse
    {
        $data = $request->toArray();

        return $this->json(Response::success(
            $query->handle((string) ($data['turnId'] ?? ''))->toArray(),
        ));
    }

    #[OA\Post(
        path: '/api/opencode/workspace-session/poluchitProgressHoda',
        summary: 'Снапшот прогресса хода workspace',
        tags: ['Workspace'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object')),
        responses: [new OA\Response(response: 200, description: 'Прогресс или null')]
    )]
    #[Route('/poluchitProgressHoda', methods: ['POST'])]
    public function poluchitProgressHoda(Request $request, PoluchitProgressHodaWorkspaceQuery $query): HttpResponse
    {
        $data = $request->toArray();

        return $this->json(Response::success(
            $query->handle((string) ($data['turnId'] ?? '')),
        ));
    }
}
