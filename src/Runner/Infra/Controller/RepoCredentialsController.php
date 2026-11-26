<?php

declare(strict_types=1);

namespace App\Runner\Infra\Controller;

use App\Runner\Business\Port\UmeetPoluchatKredensialyRepozitoriya;
use App\Runner\Business\UseCase\ProjectBuildState\ProjectBuildStateInvalidSignatureException;
use App\Runner\Business\UseCase\ProjectBuildStateSignatureVerifier;
use App\Runner\Business\UseCase\RunnerSelectionService;
use OpenApi\Attributes as OA;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Just-in-time broker git-креденшелов для раннера.
 *
 * Аутентификация — та же callback-схема, что у build-state:
 * `X-Runner-Id` + `X-Runner-Signature: hex(HMAC-SHA256(raw_body, secret))`.
 * Пользовательская JWT-сессия здесь не принимается. Неизвестный/неактивный
 * раннер, чужой repo/project/unit — deny by default.
 *
 * Внутренний endpoint: не публикуется в AI-visible OpenAPI surface.
 */
#[Route('/api/internal/repo/credentials')]
final class RepoCredentialsController extends AbstractController
{
    private const MAX_BODY_BYTES = 16 * 1024;
    private const ID_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._:-]*$/';
    private const MAX_ID_LENGTH = 128;

    #[Route('', methods: ['POST'])]
    #[OA\Post(
        path: '/api/internal/repo/credentials',
        operationId: 'issue_runner_repo_credentials',
        summary: 'Короткоживущий грант git-доступа для раннера (internal)',
        description: 'Внутренний runner-facing endpoint. Аутентификация — '
            . 'X-Runner-Id + X-Runner-Signature: hex(HMAC-SHA256(raw_body, secret)). '
            . 'Возвращает чистый repoUrl, username, token и expiresAt (TTL ≤ 5 минут). '
            . 'Не принимает пользовательскую сессию и не публикуется в AI-инструментах.',
        tags: ['Runner'],
        x: [
            'ai-enabled' => false,
            'internal' => true,
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['repoId', 'projectId'],
                properties: [
                    new OA\Property(property: 'repoId', type: 'string'),
                    new OA\Property(property: 'projectId', type: 'string'),
                    new OA\Property(property: 'unitId', type: 'string', nullable: true),
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Грант выдан',
                content: new OA\JsonContent(
                    required: ['repoId', 'projectId', 'repoUrl', 'username', 'token', 'expiresAt'],
                    properties: [
                        new OA\Property(property: 'repoId', type: 'string'),
                        new OA\Property(property: 'projectId', type: 'string'),
                        new OA\Property(property: 'repoUrl', type: 'string', description: 'URL без userinfo'),
                        new OA\Property(property: 'username', type: 'string'),
                        new OA\Property(property: 'token', type: 'string'),
                        new OA\Property(property: 'expiresAt', type: 'integer'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 400, description: 'Некорректный запрос'),
            new OA\Response(response: 401, description: 'Неверная подпись раннера'),
            new OA\Response(response: 403, description: 'Раннер не имеет доступа к repo/project/unit'),
            new OA\Response(response: 404, description: 'Раннер не найден'),
            new OA\Response(response: 409, description: 'Раннер неактивен'),
            new OA\Response(response: 503, description: 'Токен репозитория недоступен (fail closed)'),
        ],
        security: []
    )]
    public function issue(
        Request $request,
        ProjectBuildStateSignatureVerifier $signatureVerifier,
        RunnerSelectionService $runnerSelectionService,
        UmeetPoluchatKredensialyRepozitoriya $credentials,
        LoggerInterface $logger,
    ): JsonResponse {
        $body = $request->getContent();

        if (strlen($body) > self::MAX_BODY_BYTES) {
            return $this->error('repo.credentials.invalid_request', 400);
        }

        $runnerId = (string) $request->headers->get(
            ProjectBuildStateSignatureVerifier::RUNNER_ID_HEADER,
            '',
        );

        if (trim($runnerId) === '') {
            return $this->error('runner.invalid_signature', 401);
        }

        try {
            $signatureVerifier->assertValid(
                $runnerId,
                $body,
                $request->headers->get(ProjectBuildStateSignatureVerifier::HEADER_NAME),
            );
        } catch (\DomainException) {
            return $this->error('runner.not_found', 404);
        } catch (ProjectBuildStateInvalidSignatureException) {
            return $this->error('runner.invalid_signature', 401);
        }

        try {
            // Отдельно проверяем active: подпись подтверждает личность, но
            // выключенный раннер не должен получать новые креденшелы.
            $runnerSelectionService->getByRunnerId($runnerId);
        } catch (\DomainException $exception) {
            return $this->error($exception->getMessage(), 409);
        }

        $payload = json_decode($body, true);
        if (!is_array($payload)) {
            return $this->error('repo.credentials.invalid_request', 400);
        }

        $repoId = $this->validId($payload['repoId'] ?? null);
        $projectId = $this->validId($payload['projectId'] ?? null);
        $unitId = null;

        if (array_key_exists('unitId', $payload) && $payload['unitId'] !== null && $payload['unitId'] !== '') {
            $unitId = $this->validId($payload['unitId']);
        }

        if ($repoId === null || $projectId === null || ($unitId === null && isset($payload['unitId']) && $payload['unitId'] !== null && $payload['unitId'] !== '')) {
            return $this->error('repo.credentials.invalid_request', 400);
        }

        try {
            $grant = $credentials->poluchitKredensialy($runnerId, $repoId, $projectId, $unitId);
        } catch (\DomainException $exception) {
            $code = $exception->getMessage();

            return $this->error(
                in_array($code, ['repo.credentials.denied', 'repo.credentials.unavailable', 'repo.credentials.invalid_request'], true)
                    ? $code
                    : 'repo.credentials.denied',
                match ($code) {
                    'repo.credentials.invalid_request' => 400,
                    'repo.credentials.unavailable' => 503,
                    default => 403,
                },
            );
        }

        // Аудит без токена и без userinfo: только идентификаторы и TTL.
        $logger->info('repo.credentials.issued', [
            'runnerId' => $runnerId,
            'repoId' => $grant->repoId,
            'projectId' => $grant->projectId,
            'unitId' => $unitId,
            'expiresAt' => $grant->expiresAt,
        ]);

        return new JsonResponse([
            'repoId' => $grant->repoId,
            'projectId' => $grant->projectId,
            'repoUrl' => $grant->repoUrl,
            'username' => $grant->username,
            'token' => $grant->token,
            'expiresAt' => $grant->expiresAt,
        ]);
    }

    private function validId(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        if (
            $value === ''
            || strlen($value) > self::MAX_ID_LENGTH
            || preg_match(self::ID_PATTERN, $value) !== 1
        ) {
            return null;
        }

        return $value;
    }

    private function error(string $code, int $status): JsonResponse
    {
        return new JsonResponse(
            \App\Utils\Model\Reponse\Response::fail(['message' => $code, 'code' => $code]),
            $status,
        );
    }
}
