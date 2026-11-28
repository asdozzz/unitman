<?php

declare(strict_types=1);

namespace App\OpenCode\Infra\Controller;

use App\OpenCode\Business\Command\PoluchitNavykiSessii;
use App\OpenCode\Business\UseCase\PoluchitNavykiSessiiQuery;
use App\Utils\Model\Reponse\Response;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Эффективные навыки для сессии OpenCode.
 *
 * Проекты определяются на сервере из сессии; клиент передаёт только
 * `sessionId`. Доступ к сессии проверяется той же политикой, что и для
 * остальных операций OpenCode.
 */
#[Route('/api/opencode')]
final class SkillNavykiController extends AbstractController
{
    #[OA\Post(
        path: '/api/opencode/navyki',
        operationId: 'opencode_session_skills',
        summary: 'Эффективные навыки для сессии OpenCode',
        description: 'Проекты разрешаются из сессии на сервере; клиент не может '
            . 'запросить навыки чужого проекта.',
        tags: ['OpenCode'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['sessionId'],
                properties: [new OA\Property(property: 'sessionId', type: 'string')],
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
            new OA\Response(response: 403, description: 'Нет доступа к сессии'),
            new OA\Response(response: 404, description: 'Сессия не найдена'),
        ]
    )]
    #[Route('/navyki', methods: ['POST'])]
    public function navyki(
        #[MapRequestPayload] PoluchitNavykiSessii $command,
        PoluchitNavykiSessiiQuery $query,
    ): HttpResponse {
        try {
            return $this->json(Response::success($query->handle($command->sessionId)));
        } catch (\DomainException $exception) {
            $code = $exception->getMessage();

            $status = match ($code) {
                'opencode.session.forbidden' => 403,
                'opencode.session.not_found' => 404,
                'account.not_authenticated' => 401,
                default => 500,
            };

            return new JsonResponse(
                Response::fail(['message' => $code, 'code' => $code]),
                $status,
            );
        }
    }
}
