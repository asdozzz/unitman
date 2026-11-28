<?php

declare(strict_types=1);

namespace App\OpenCode\Infra\Controller;

use App\OpenCode\Business\Command\DobavitUchastnika;
use App\OpenCode\Business\Command\IzmenitUchastnika;
use App\OpenCode\Business\Command\PoluchitUchastnikov;
use App\OpenCode\Business\Command\UdalitUchastnika;
use App\OpenCode\Business\ReadModel\UchastnikSessiiReadModel;
use App\OpenCode\Business\UseCase\DobavitUchastnikaUseCase;
use App\OpenCode\Business\UseCase\IzmenitUchastnikaUseCase;
use App\OpenCode\Business\UseCase\PoluchitUchastnikovQuery;
use App\OpenCode\Business\UseCase\UdalitUchastnikaUseCase;
use App\Utils\Model\Reponse\Response;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Участники workspace-сессии.
 *
 * Управлять составом может только владелец; список доступен любому участнику.
 * Приглашение проверяет доступ кандидата ко всем проектам workspace.
 */
#[Route('/api/opencode/workspace/uchastniki')]
final class WorkspaceParticipantController extends AbstractController
{
    public function __construct(
        private readonly PoluchitUchastnikovQuery $list,
        private readonly DobavitUchastnikaUseCase $add,
        private readonly IzmenitUchastnikaUseCase $change,
        private readonly UdalitUchastnikaUseCase $remove,
    ) {
    }

    #[OA\Post(
        path: '/api/opencode/workspace/uchastniki/spisok',
        summary: 'Список участников workspace',
        tags: ['Workspace'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['workspaceId'],
                properties: [new OA\Property(property: 'workspaceId', type: 'string')]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Участники',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            required: ['items'],
                            properties: [
                                new OA\Property(
                                    property: 'items',
                                    type: 'array',
                                    items: new OA\Items(ref: '#/components/schemas/UchastnikSessii')
                                ),
                            ]
                        ),
                    ],
                    type: 'object'
                )
            ),
        ]
    )]
    #[Route('/spisok', methods: ['POST'])]
    public function spisok(Request $request): HttpResponse
    {
        $data = $request->toArray();

        $items = $this->list->handle(new PoluchitUchastnikov($this->str($data, 'workspaceId')));

        return $this->json(Response::success([
            'items' => array_map(
                static fn (UchastnikSessiiReadModel $uchastnik): array => $uchastnik->vMassiv(),
                $items,
            ),
        ]));
    }

    #[OA\Post(
        path: '/api/opencode/workspace/uchastniki/dobavit',
        summary: 'Пригласить участника в workspace',
        tags: ['Workspace'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['workspaceId', 'accountId', 'role'],
                properties: [
                    new OA\Property(property: 'workspaceId', type: 'string'),
                    new OA\Property(property: 'accountId', type: 'string'),
                    new OA\Property(property: 'role', type: 'string', enum: ['writer', 'viewer']),
                    new OA\Property(property: 'credentialId', type: 'string', nullable: true),
                    new OA\Property(property: 'model', type: 'string', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Участник добавлен',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            required: ['added'],
                            properties: [
                                new OA\Property(property: 'added', type: 'boolean', example: true),
                            ]
                        ),
                    ],
                    type: 'object'
                )
            ),
        ]
    )]
    #[Route('/dobavit', methods: ['POST'])]
    public function dobavit(Request $request): HttpResponse
    {
        $data = $request->toArray();

        $this->add->handle(new DobavitUchastnika(
            $this->str($data, 'workspaceId'),
            $this->str($data, 'accountId'),
            $this->str($data, 'role'),
            $this->opt($data, 'credentialId'),
            $this->opt($data, 'model'),
        ));

        return $this->json(Response::success(['added' => true]));
    }

    #[OA\Post(
        path: '/api/opencode/workspace/uchastniki/izmenit',
        summary: 'Изменить роль или реквизиты участника',
        tags: ['Workspace'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['workspaceId', 'accountId', 'role'],
                properties: [
                    new OA\Property(property: 'workspaceId', type: 'string'),
                    new OA\Property(property: 'accountId', type: 'string'),
                    new OA\Property(property: 'role', type: 'string', enum: ['writer', 'viewer']),
                    new OA\Property(property: 'credentialId', type: 'string', nullable: true),
                    new OA\Property(property: 'model', type: 'string', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Участник изменён',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            required: ['updated'],
                            properties: [
                                new OA\Property(property: 'updated', type: 'boolean', example: true),
                            ]
                        ),
                    ],
                    type: 'object'
                )
            ),
        ]
    )]
    #[Route('/izmenit', methods: ['POST'])]
    public function izmenit(Request $request): HttpResponse
    {
        $data = $request->toArray();

        $this->change->handle(new IzmenitUchastnika(
            $this->str($data, 'workspaceId'),
            $this->str($data, 'accountId'),
            $this->str($data, 'role'),
            $this->opt($data, 'credentialId'),
            $this->opt($data, 'model'),
        ));

        return $this->json(Response::success(['updated' => true]));
    }

    #[OA\Post(
        path: '/api/opencode/workspace/uchastniki/udalit',
        summary: 'Удалить участника из workspace',
        tags: ['Workspace'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['workspaceId', 'accountId'],
                properties: [
                    new OA\Property(property: 'workspaceId', type: 'string'),
                    new OA\Property(property: 'accountId', type: 'string'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Участник удалён',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            required: ['removed'],
                            properties: [
                                new OA\Property(property: 'removed', type: 'boolean', example: true),
                            ]
                        ),
                    ],
                    type: 'object'
                )
            ),
        ]
    )]
    #[Route('/udalit', methods: ['POST'])]
    public function udalit(Request $request): HttpResponse
    {
        $data = $request->toArray();

        $this->remove->handle(new UdalitUchastnika(
            $this->str($data, 'workspaceId'),
            $this->str($data, 'accountId'),
        ));

        return $this->json(Response::success(['removed' => true]));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function str(array $data, string $key): string
    {
        $value = $data[$key] ?? '';

        return is_string($value) ? trim($value) : '';
    }

    /**
     * @param array<string, mixed> $data
     */
    private function opt(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
