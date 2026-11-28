<?php

declare(strict_types=1);

namespace App\Skill\Infra\Controller;

use App\Skill\Business\Command\IzmenitNavyk;
use App\Skill\Business\Command\OtkrepitNavykOtProekta;
use App\Skill\Business\Command\PoluchitEffektivnieNavyki;
use App\Skill\Business\Command\PoluchitNavyk;
use App\Skill\Business\Command\PoluchitSpisokNavykov;
use App\Skill\Business\Command\PoluchitVersiiNavyka;
use App\Skill\Business\Command\PrikrepitNavykKProektam;
use App\Skill\Business\Command\SozdatNavyk;
use App\Skill\Business\Command\UdalitNavyk;
use App\Skill\Business\UseCase\IzmenitNavykUseCase;
use App\Skill\Business\UseCase\OtkrepitNavykOtProektaUseCase;
use App\Skill\Business\UseCase\PoluchitEffektivnieNavykiQuery;
use App\Skill\Business\UseCase\PoluchitNavykQuery;
use App\Skill\Business\UseCase\PoluchitSpisokNavykovQuery;
use App\Skill\Business\UseCase\PoluchitVersiiNavykaQuery;
use App\Skill\Business\UseCase\PrikrepitNavykKProektamUseCase;
use App\Skill\Business\UseCase\SozdatNavykUseCase;
use App\Skill\Business\UseCase\UdalitNavykUseCase;
use App\Utils\Model\Reponse\Response;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Управление пользовательскими навыками OpenCode.
 *
 * Все маршруты закрыты JWT. Права проверяются в UseCase/Query:
 * глобальные навыки изменяет только `ROLE_ADMIN`, проектные — администратор
 * каждого привязанного проекта; чтение проектного навыка доступно участнику
 * хотя бы одного проекта.
 */
#[Route('/api/skill')]
final class SkillController extends AbstractController
{
    #[OA\Post(
        path: '/api/skill/spisok',
        operationId: 'skill_list',
        summary: 'Список навыков (глобальных или проектных)',
        tags: ['Skill'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'scope', type: 'string', enum: ['global', 'project'], nullable: true),
                new OA\Property(property: 'projectId', type: 'string', nullable: true),
                new OA\Property(property: 'includeArchived', type: 'boolean', default: false),
            ], type: 'object')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Краткие карточки навыков',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success')],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 403, description: 'Нет доступа к проекту'),
            new OA\Response(response: 422, description: 'Некорректный запрос'),
        ]
    )]
    #[Route('/spisok', methods: ['POST'])]
    public function spisok(
        #[MapRequestPayload] PoluchitSpisokNavykov $command,
        PoluchitSpisokNavykovQuery $query,
    ): HttpResponse {
        return $this->vypolnit(static fn (): mixed => $query->handle($command));
    }

    #[OA\Post(
        path: '/api/skill/read',
        operationId: 'skill_read',
        summary: 'Полная карточка навыка',
        tags: ['Skill'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['id'],
                properties: [new OA\Property(property: 'id', type: 'string')],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Карточка навыка с содержимым',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success')],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 403, description: 'Нет доступа'),
            new OA\Response(response: 404, description: 'Навык не найден'),
        ]
    )]
    #[Route('/read', methods: ['POST'])]
    public function read(
        #[MapRequestPayload] PoluchitNavyk $command,
        PoluchitNavykQuery $query,
    ): HttpResponse {
        return $this->vypolnit(static fn (): mixed => $query->handle($command));
    }

    #[OA\Post(
        path: '/api/skill/sozdat',
        operationId: 'skill_create',
        summary: 'Создать навык',
        tags: ['Skill'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'scope'],
                properties: [
                    new OA\Property(property: 'slug', type: 'string', nullable: true),
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'description', type: 'string'),
                    new OA\Property(property: 'content', type: 'string'),
                    new OA\Property(property: 'scope', type: 'string', enum: ['global', 'project']),
                    new OA\Property(
                        property: 'projectIds',
                        type: 'array',
                        items: new OA\Items(type: 'string')
                    ),
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Идентификатор и slug созданного навыка',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success')],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 403, description: 'Недостаточно прав'),
            new OA\Response(response: 422, description: 'Ошибка валидации или занятый slug'),
        ]
    )]
    #[Route('/sozdat', methods: ['POST'])]
    public function sozdat(
        #[MapRequestPayload] SozdatNavyk $command,
        SozdatNavykUseCase $useCase,
    ): HttpResponse {
        return $this->vypolnit(static fn (): mixed => $useCase->handle($command));
    }

    #[OA\Post(
        path: '/api/skill/izmenit',
        operationId: 'skill_update',
        summary: 'Изменить навык',
        tags: ['Skill'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['id'],
                properties: [
                    new OA\Property(property: 'id', type: 'string'),
                    new OA\Property(property: 'name', type: 'string', nullable: true),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                    new OA\Property(property: 'content', type: 'string', nullable: true),
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Идентификатор и версия навыка',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success')],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 403, description: 'Недостаточно прав'),
            new OA\Response(response: 404, description: 'Навык не найден'),
            new OA\Response(response: 422, description: 'Ошибка валидации'),
        ]
    )]
    #[Route('/izmenit', methods: ['POST'])]
    public function izmenit(
        #[MapRequestPayload] IzmenitNavyk $command,
        IzmenitNavykUseCase $useCase,
    ): HttpResponse {
        return $this->vypolnit(static fn (): mixed => $useCase->handle($command));
    }

    #[OA\Post(
        path: '/api/skill/udalit',
        operationId: 'skill_archive',
        summary: 'Архивировать навык',
        tags: ['Skill'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['id'],
                properties: [new OA\Property(property: 'id', type: 'string')],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Пустой ответ: навык архивирован',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(property: 'data', type: 'null'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 403, description: 'Недостаточно прав'),
            new OA\Response(response: 404, description: 'Навык не найден'),
        ]
    )]
    #[Route('/udalit', methods: ['POST'])]
    public function udalit(
        #[MapRequestPayload] UdalitNavyk $command,
        UdalitNavykUseCase $useCase,
    ): HttpResponse {
        return $this->vypolnit(static function () use ($useCase, $command): mixed {
            $useCase->handle($command);

            return null;
        });
    }

    #[OA\Post(
        path: '/api/skill/prikrepit',
        operationId: 'skill_attach_projects',
        summary: 'Прикрепить проектный навык к проектам',
        tags: ['Skill'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['id', 'projectIds'],
                properties: [
                    new OA\Property(property: 'id', type: 'string'),
                    new OA\Property(
                        property: 'projectIds',
                        type: 'array',
                        items: new OA\Items(type: 'string')
                    ),
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Актуальный список проектов навыка',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success')],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 403, description: 'Недостаточно прав'),
            new OA\Response(response: 404, description: 'Навык не найден'),
            new OA\Response(response: 422, description: 'Некорректный запрос'),
        ]
    )]
    #[Route('/prikrepit', methods: ['POST'])]
    public function prikrepit(
        #[MapRequestPayload] PrikrepitNavykKProektam $command,
        PrikrepitNavykKProektamUseCase $useCase,
    ): HttpResponse {
        return $this->vypolnit(static fn (): mixed => $useCase->handle($command));
    }

    #[OA\Post(
        path: '/api/skill/otkrepit',
        operationId: 'skill_detach_project',
        summary: 'Открепить проектный навык от проекта',
        tags: ['Skill'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['id', 'projectId'],
                properties: [
                    new OA\Property(property: 'id', type: 'string'),
                    new OA\Property(property: 'projectId', type: 'string'),
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Актуальный список проектов навыка',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success')],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 403, description: 'Недостаточно прав'),
            new OA\Response(response: 404, description: 'Навык не найден'),
            new OA\Response(response: 422, description: 'Некорректный запрос'),
        ]
    )]
    #[Route('/otkrepit', methods: ['POST'])]
    public function otkrepit(
        #[MapRequestPayload] OtkrepitNavykOtProekta $command,
        OtkrepitNavykOtProektaUseCase $useCase,
    ): HttpResponse {
        return $this->vypolnit(static fn (): mixed => $useCase->handle($command));
    }

    #[OA\Post(
        path: '/api/skill/versii',
        operationId: 'skill_versions',
        summary: 'История версий навыка',
        tags: ['Skill'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['id'],
                properties: [new OA\Property(property: 'id', type: 'string')],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Версии навыка от новой к старой',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success')],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 403, description: 'Нет доступа'),
            new OA\Response(response: 404, description: 'Навык не найден'),
        ]
    )]
    #[Route('/versii', methods: ['POST'])]
    public function versii(
        #[MapRequestPayload] PoluchitVersiiNavyka $command,
        PoluchitVersiiNavykaQuery $query,
    ): HttpResponse {
        return $this->vypolnit(static fn (): mixed => $query->handle($command));
    }

    #[OA\Post(
        path: '/api/skill/effektivnie',
        operationId: 'skill_effective',
        summary: 'Эффективный набор навыков для проектов (диагностика)',
        tags: ['Skill'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['projectIds'],
                properties: [
                    new OA\Property(
                        property: 'projectIds',
                        type: 'array',
                        items: new OA\Items(type: 'string')
                    ),
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Эффективные навыки с манифестом',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success')],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 403, description: 'Нет доступа к проектам'),
        ]
    )]
    #[Route('/effektivnie', methods: ['POST'])]
    public function effektivnie(
        #[MapRequestPayload] PoluchitEffektivnieNavyki $command,
        PoluchitEffektivnieNavykiQuery $query,
    ): HttpResponse {
        return $this->vypolnit(static fn (): mixed => $query->handle($command));
    }

    /**
     * @param callable(): mixed $deistvie
     */
    private function vypolnit(callable $deistvie): HttpResponse
    {
        try {
            return $this->json(Response::success($deistvie()));
        } catch (\DomainException $exception) {
            return $this->oshibka($exception->getMessage());
        }
    }

    private function oshibka(string $code): JsonResponse
    {
        $status = match (true) {
            $code === 'skill.forbidden' => 403,
            $code === 'skill.not_found' => 404,
            $code === 'account.not_authenticated' => 401,
            str_starts_with($code, 'skill.') => 422,
            default => 500,
        };

        return new JsonResponse(
            Response::fail(['message' => $code, 'code' => $code]),
            $status,
        );
    }
}
