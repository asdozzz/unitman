<?php

declare(strict_types=1);

namespace App\Runner\Infra\Controller;

use App\Runner\Business\Model\ProjectBuildState;
use App\Runner\Business\UseCase\ProjectBuildState\ProjectBuildStateInvalidPayloadException;
use App\Runner\Business\UseCase\ProjectBuildState\ProjectBuildStateInvalidProjectIdException;
use App\Runner\Business\UseCase\ProjectBuildState\ProjectBuildStateInvalidSignatureException;
use App\Runner\Business\UseCase\ProjectBuildStateSignatureVerifier;
use App\Runner\Business\UseCase\ProveritSostoyanieSborkiProektaUseCase;
use App\Runner\Business\UseCase\ZapisatSostoyanieSborkiProektaUseCase;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Runner-facing API состояния сборки проекта (multi-runner).
 *
 * Контракт: plans/runner-multi-runner.md §3.
 *
 * GET  /api/runner/{runnerId}/projects/{projectId}/build-state — проверка;
 * PUT  /api/runner/{runnerId}/projects/{projectId}/build-state — идемпотентная
 * запись факта сборки.
 *
 * Аутентификация — callback-схема раннера v2:
 * `X-Runner-Id` + `X-Runner-Signature: hex(hmac_sha256(payload, secret))`,
 * где secret детерминированно вычисляется по runnerId. Для GET
 * подписывается пустое тело, для PUT — сырое тело запроса.
 */
#[Route('/api/runner/{runnerId}/projects/{projectId}/build-state')]
final class ProjectBuildStateController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    #[OA\Get(
        path: '/api/runner/{runnerId}/projects/{projectId}/build-state',
        operationId: 'get_runner_project_build_state',
        summary: 'Состояние сборки проекта на раннере',
        description: 'Проверяет, собран ли проект на конкретном раннере (multi-runner). '
            . 'Аутентификация — X-Runner-Id + X-Runner-Signature: hex(HMAC-SHA256(пустое_тело, secret)).',
        tags: ['Runner'],
        parameters: [
            new OA\Parameter(name: 'runnerId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'projectId', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Состояние сборки (built=false, если записи нет)'),
            new OA\Response(response: 400, description: 'Некорректный projectId'),
            new OA\Response(response: 401, description: 'Неверная подпись либо X-Runner-Id не совпадает с путём'),
            new OA\Response(response: 404, description: 'runner.not_found'),
            new OA\Response(response: 409, description: 'runner.inactive'),
        ],
    )]
    public function check(
        string $runnerId,
        string $projectId,
        Request $request,
        ProveritSostoyanieSborkiProektaUseCase $useCase,
        ProjectBuildStateSignatureVerifier $signatureVerifier,
    ): JsonResponse {
        $authError = $this->authenticate($request, $runnerId, $signatureVerifier);
        if ($authError !== null) {
            return $authError;
        }

        try {
            $state = $useCase->handle($runnerId, $projectId);
        } catch (ProjectBuildStateInvalidProjectIdException $exception) {
            return $this->error($exception->getMessage(), 400);
        } catch (\DomainException $exception) {
            return $this->domainError($exception);
        }

        return new JsonResponse($this->serializeState($state));
    }

    #[Route('', methods: ['PUT'])]
    #[OA\Put(
        path: '/api/runner/{runnerId}/projects/{projectId}/build-state',
        operationId: 'put_runner_project_build_state',
        summary: 'Записать состояние сборки проекта на раннере',
        description: 'Идемпотентно фиксирует факт сборки проекта на раннере (upsert). '
            . 'Аутентификация — X-Runner-Id + X-Runner-Signature: hex(HMAC-SHA256(raw_body, secret)).',
        tags: ['Runner'],
        parameters: [
            new OA\Parameter(name: 'runnerId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'projectId', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'builtAt', type: 'integer', nullable: true),
                new OA\Property(property: 'commit', type: 'string', nullable: true),
                new OA\Property(property: 'branch', type: 'string', nullable: true),
            ]),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Сохранённое состояние сборки'),
            new OA\Response(response: 400, description: 'Некорректный projectId или payload'),
            new OA\Response(response: 401, description: 'Неверная подпись либо X-Runner-Id не совпадает с путём'),
            new OA\Response(response: 404, description: 'runner.not_found'),
            new OA\Response(response: 409, description: 'runner.inactive'),
        ],
    )]
    public function record(
        string $runnerId,
        string $projectId,
        Request $request,
        ZapisatSostoyanieSborkiProektaUseCase $useCase,
        ProjectBuildStateSignatureVerifier $signatureVerifier,
    ): JsonResponse {
        $authError = $this->authenticate($request, $runnerId, $signatureVerifier);
        if ($authError !== null) {
            return $authError;
        }

        try {
            $state = $useCase->handlePayload($runnerId, $projectId, $request->getContent());
        } catch (ProjectBuildStateInvalidProjectIdException $exception) {
            return $this->error($exception->getMessage(), 400);
        } catch (ProjectBuildStateInvalidPayloadException $exception) {
            return $this->error($exception->getMessage(), 400);
        } catch (\DomainException $exception) {
            return $this->domainError($exception);
        }

        return new JsonResponse($this->serializeState($state));
    }

    private function authenticate(
        Request $request,
        string $runnerId,
        ProjectBuildStateSignatureVerifier $signatureVerifier,
    ): ?JsonResponse {
        // `X-Runner-Id` обязан совпадать с runnerId из пути: иначе валидный
        // секрет одного раннера позволил бы читать/писать build-state другого.
        $headerRunnerId = $request->headers->get(ProjectBuildStateSignatureVerifier::RUNNER_ID_HEADER);

        if ($headerRunnerId === null || $headerRunnerId !== $runnerId) {
            return $this->error('runner.invalid_signature', 401);
        }

        try {
            $signatureVerifier->assertValid(
                $runnerId,
                $request->getContent(),
                $request->headers->get(ProjectBuildStateSignatureVerifier::HEADER_NAME),
            );
        } catch (\DomainException $exception) {
            return $this->error($exception->getMessage(), 404);
        } catch (ProjectBuildStateInvalidSignatureException $exception) {
            return $this->error($exception->getMessage(), 401);
        }

        return null;
    }

    /**
     * @return array{built: bool, builtAt: ?int, commit: ?string, branch: ?string}
     */
    private function serializeState(?ProjectBuildState $state): array
    {
        if ($state === null) {
            return [
                'built' => false,
                'builtAt' => null,
                'commit' => null,
                'branch' => null,
            ];
        }

        return [
            'built' => true,
            'builtAt' => $state->builtAt,
            'commit' => $state->commit,
            'branch' => $state->branch,
        ];
    }

    private function domainError(\DomainException $exception): JsonResponse
    {
        return match ($exception->getMessage()) {
            'runner.not_found' => $this->error('runner.not_found', 404),
            'runner.inactive' => $this->error('runner.inactive', 409),
            default => $this->error($exception->getMessage(), 400),
        };
    }

    /**
     * Ошибка в общем формате приложения (`FailEnvelope`): `code` продублирован
     * отдельным полем, как у регистрации раннера.
     */
    private function error(string $code, int $status): JsonResponse
    {
        return new JsonResponse(
            \App\Utils\Model\Reponse\Response::fail(['message' => $code, 'code' => $code]),
            $status,
        );
    }
}
