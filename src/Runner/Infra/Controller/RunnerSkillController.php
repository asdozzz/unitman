<?php

declare(strict_types=1);

namespace App\Runner\Infra\Controller;

use App\Runner\Infra\Service\RunnerCallbackSignatureChecker;
use App\Skill\Api\SkillApi;
use App\Skill\Business\Utils\PostroenieEffektivnyhNavykov;
use App\Utils\Model\Reponse\Response;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Выдача эффективных навыков раннеру.
 *
 * Аутентификация — та же callback-схема, что у остальных runner-facing
 * эндпоинтов: `X-Runner-Id` + `X-Runner-Signature: hex(HMAC-SHA256(raw_body,
 * secret))`, проверяется {@see RunnerCallbackSignatureChecker::estPodpisTelaValidna()}.
 * Пользовательская JWT-сессия здесь не принимается.
 *
 * Внутренний endpoint: не публикуется в AI-visible OpenAPI surface.
 */
#[Route('/api/internal/opencode/skills/resolve')]
final class RunnerSkillController extends AbstractController
{
    private const MAX_BODY_BYTES = 64 * 1024;
    private const MAX_ID_LENGTH = 128;
    private const FORMAT_VERSION = 1;

    #[Route('', methods: ['POST'])]
    #[OA\Post(
        path: '/api/internal/opencode/skills/resolve',
        operationId: 'resolve_runner_skills',
        summary: 'Эффективные навыки для раннера (internal)',
        description: 'Внутренний runner-facing endpoint. Аутентификация — '
            . 'X-Runner-Id + X-Runner-Signature: hex(HMAC-SHA256(raw_body, secret)). '
            . 'Возвращает эффективные навыки, manifestHash, суммарный размер и '
            . 'признак усечения. Секретов в ответе нет.',
        tags: ['Runner'],
        x: [
            'ai-enabled' => false,
            'internal' => true,
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['projectIds', 'formatVersion', 'maxTotalBytes'],
                properties: [
                    new OA\Property(
                        property: 'projectIds',
                        type: 'array',
                        items: new OA\Items(type: 'string')
                    ),
                    new OA\Property(property: 'formatVersion', type: 'integer', example: 1),
                    new OA\Property(property: 'maxTotalBytes', type: 'integer', example: 524288),
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Эффективные навыки',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success')],
                    type: 'object'
                )
            ),
            new OA\Response(response: 400, description: 'Некорректный запрос'),
            new OA\Response(response: 401, description: 'Неверная подпись раннера'),
            new OA\Response(response: 404, description: 'Раннер не найден'),
        ],
        security: []
    )]
    public function resolve(
        Request $request,
        RunnerCallbackSignatureChecker $signatureChecker,
        SkillApi $skillApi,
    ): JsonResponse {
        $body = $request->getContent();

        if (strlen($body) > self::MAX_BODY_BYTES) {
            return $this->error('skill.resolve.invalid_request', 400);
        }

        $runnerId = $request->headers->get(
            RunnerCallbackSignatureChecker::RUNNER_ID_HEADER,
            '',
        );

        if (trim($runnerId) === '') {
            return $this->error('runner.invalid_signature', 401);
        }

        try {
            $valid = $signatureChecker->estPodpisTelaValidna(
                $runnerId,
                $body,
                $request->headers->get(RunnerCallbackSignatureChecker::HEADER_NAME),
            );
        } catch (\DomainException) {
            return $this->error('runner.not_found', 404);
        }

        if (!$valid) {
            return $this->error('runner.invalid_signature', 401);
        }

        $payload = json_decode($body, true);

        if (!is_array($payload)) {
            return $this->error('skill.resolve.invalid_request', 400);
        }

        $projectIds = $this->projectIds($payload['projectIds'] ?? null);

        if ($projectIds === null) {
            return $this->error('skill.resolve.invalid_request', 400);
        }

        $formatVersion = $payload['formatVersion'] ?? self::FORMAT_VERSION;

        if (!is_int($formatVersion) || $formatVersion !== self::FORMAT_VERSION) {
            return $this->error('skill.resolve.unsupported_format', 400);
        }

        $maxTotalBytes = $payload['maxTotalBytes'] ?? PostroenieEffektivnyhNavykov::MAX_TOTAL_BYTES;

        if (
            !is_int($maxTotalBytes)
            || $maxTotalBytes <= 0
            || $maxTotalBytes > PostroenieEffektivnyhNavykov::MAX_TOTAL_BYTES
        ) {
            return $this->error('skill.resolve.invalid_request', 400);
        }

        $result = $skillApi->effektivnyeNavykiDlyaProektov(
            $projectIds,
            PostroenieEffektivnyhNavykov::MAX_SKILLS,
            $maxTotalBytes,
        );

        return new JsonResponse(Response::success($result->toArray()));
    }

    /**
     * @return list<string>|null `null` — некорректный формат
     */
    private function projectIds(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        $rezultat = [];

        foreach ($value as $projectId) {
            if (!is_string($projectId)) {
                return null;
            }

            $projectId = trim($projectId);

            if ($projectId === '' || strlen($projectId) > self::MAX_ID_LENGTH) {
                return null;
            }

            if (!in_array($projectId, $rezultat, true)) {
                $rezultat[] = $projectId;
            }
        }

        return $rezultat;
    }

    private function error(string $code, int $status): JsonResponse
    {
        return new JsonResponse(
            Response::fail(['message' => $code, 'code' => $code]),
            $status,
        );
    }
}
