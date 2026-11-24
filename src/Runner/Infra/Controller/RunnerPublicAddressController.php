<?php

declare(strict_types=1);

namespace App\Runner\Infra\Controller;

use App\Runner\Business\UseCase\PublicAddress\ProveritPublicAddressUseCase;
use App\Runner\Business\UseCase\PublicAddress\UdalitPublicAddressUseCase;
use App\Runner\Business\UseCase\PublicAddress\UstanovitPublicAddressUseCase;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Административное управление публичным адресом раннера (контракт v2, A2).
 *
 * Все три операции доступны только администратору (JWT + ROLE_ADMIN в
 * `security.yaml`, дополнительная проверка в use case).
 */
#[Route('/api/runner')]
final class RunnerPublicAddressController extends AbstractController
{
    #[OA\Put(
        path: '/api/runner/{runnerId}/public-address',
        summary: 'Задать публичный адрес раннера (администратор)',
        description: 'Адрес обязан быть абсолютным http/https URL с непустым host '
            . 'и без userinfo (`user:pass@`). Неизвестный runnerId — 404. '
            . 'Недоступность адреса сохранение не блокирует: проверка — отдельный эндпоинт.',
        tags: ['Runner'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'runnerId',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid'),
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/RunnerPublicAddressRequest'),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Адрес сохранён',
                content: new OA\JsonContent(ref: '#/components/schemas/RunnerPublicAddress'),
            ),
            new OA\Response(response: 400, description: 'runner.public_address.invalid'),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 403, description: 'Недостаточно прав (не администратор)'),
            new OA\Response(response: 404, description: 'runner.not_found'),
        ],
    )]
    #[Route('/{runnerId}/public-address', methods: ['PUT'])]
    public function putPublicAddress(
        string $runnerId,
        Request $request,
        UstanovitPublicAddressUseCase $useCase,
    ): JsonResponse {
        try {
            $runner = $useCase->handle($runnerId, $this->extractPublicAddress($request));
        } catch (\InvalidArgumentException $exception) {
            throw new BadRequestHttpException($exception->getMessage());
        } catch (\DomainException $exception) {
            throw new NotFoundHttpException($exception->getMessage());
        }

        return new JsonResponse([
            'runnerId' => $runner->getId(),
            'publicAddress' => $runner->getPublicAddress(),
        ]);
    }

    #[OA\Delete(
        path: '/api/runner/{runnerId}/public-address',
        summary: 'Очистить публичный адрес раннера (администратор)',
        description: 'Раннер остаётся в реестре и может присылать health, '
            . 'но перестаёт выбираться для сборок и построения ссылок.',
        tags: ['Runner'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'runnerId',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid'),
            ),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Адрес очищен'),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 403, description: 'Недостаточно прав (не администратор)'),
            new OA\Response(response: 404, description: 'runner.not_found'),
        ],
    )]
    #[Route('/{runnerId}/public-address', methods: ['DELETE'])]
    public function deletePublicAddress(
        string $runnerId,
        UdalitPublicAddressUseCase $useCase,
    ): Response {
        try {
            $useCase->handle($runnerId);
        } catch (\DomainException $exception) {
            throw new NotFoundHttpException($exception->getMessage());
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    #[OA\Post(
        path: '/api/runner/{runnerId}/public-address/check',
        summary: 'Проверить доступность публичного адреса раннера (администратор)',
        description: 'Best-effort `GET <publicAddress>/__unitman/health` с коротким таймаутом. '
            . 'Локальный дев-адрес (localhost/127.0.0.1:8087) проверяется по внутреннему '
            . 'имени uproxy:80. Проверка консультативная: недоступность не является '
            . 'ошибкой запроса и ничего не сохраняет.',
        tags: ['Runner'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'runnerId',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid'),
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/RunnerPublicAddressRequest'),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Результат проверки',
                content: new OA\JsonContent(ref: '#/components/schemas/RunnerPublicAddressCheck'),
            ),
            new OA\Response(response: 400, description: 'runner.public_address.invalid'),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 403, description: 'Недостаточно прав (не администратор)'),
            new OA\Response(response: 404, description: 'runner.not_found'),
        ],
    )]
    #[Route('/{runnerId}/public-address/check', methods: ['POST'])]
    public function checkPublicAddress(
        string $runnerId,
        Request $request,
        ProveritPublicAddressUseCase $useCase,
    ): JsonResponse {
        try {
            $result = $useCase->handle($runnerId, $this->extractPublicAddress($request));
        } catch (\InvalidArgumentException $exception) {
            throw new BadRequestHttpException($exception->getMessage());
        } catch (\DomainException $exception) {
            throw new NotFoundHttpException($exception->getMessage());
        }

        return new JsonResponse($result);
    }

    private function extractPublicAddress(Request $request): string
    {
        try {
            $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new BadRequestHttpException('runner.public_address.invalid');
        }

        if (!is_array($payload) || !is_string($payload['publicAddress'] ?? null)) {
            throw new BadRequestHttpException('runner.public_address.invalid');
        }

        return $payload['publicAddress'];
    }
}
