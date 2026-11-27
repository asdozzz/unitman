<?php

declare(strict_types=1);

namespace App\OpenCode\Infra\Controller;

use App\OpenCode\Business\Command\PrinyatFajlHoda;
use App\OpenCode\Business\UseCase\PrinyatFajlHodaUseCase;
use App\Runner\Infra\Service\RunnerCallbackSignatureChecker;
use App\Utils\Model\Reponse\Response;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Приём файла-артефакта от Golang-раннера.
 *
 * Публичный по JWT маршрут: аутентификация — подпись `X-Runner-Id` +
 * `X-Runner-Signature`, а не токен пользователя. Подпись считается по
 * `workflowId "\n" rawName "\n" rawBody`; имя в подписи — ровно то, что пришло
 * в query. Нормализация имени выполняется внутри use case уже после проверки
 * подписи.
 */
#[Route('/api/runner/opencode')]
final class RunnerArtifactController extends AbstractController
{
    private const DEFAULT_CONTENT_TYPE = 'application/octet-stream';

    /**
     * Ошибки бизнес-слоя -> статусы HTTP.
     *
     * @var array<string, int>
     */
    private const STATUS_BY_KEY = [
        'opencode.artifact.turn_not_found' => HttpResponse::HTTP_NOT_FOUND,
        'opencode.artifact.turn_not_running' => HttpResponse::HTTP_CONFLICT,
        'opencode.artifact.file_is_too_large' => HttpResponse::HTTP_REQUEST_ENTITY_TOO_LARGE,
        'opencode.artifact.workspace_required' => HttpResponse::HTTP_UNPROCESSABLE_ENTITY,
        'opencode.artifact.workspace_mismatch' => HttpResponse::HTTP_UNPROCESSABLE_ENTITY,
        'opencode.artifact.workspace_archived' => HttpResponse::HTTP_UNPROCESSABLE_ENTITY,
        'opencode.artifact.name_is_invalid' => HttpResponse::HTTP_UNPROCESSABLE_ENTITY,
        'opencode.artifact.file_type_forbidden' => HttpResponse::HTTP_UNPROCESSABLE_ENTITY,
        'opencode.artifact.content_invalid' => HttpResponse::HTTP_UNPROCESSABLE_ENTITY,
        'opencode.artifact.too_many_files' => HttpResponse::HTTP_UNPROCESSABLE_ENTITY,
        'opencode.artifact.total_is_too_large' => HttpResponse::HTTP_UNPROCESSABLE_ENTITY,
    ];

    public function __construct(
        private readonly RunnerCallbackSignatureChecker $signatureChecker,
        private readonly PrinyatFajlHodaUseCase $useCase,
        private readonly int $artifactMaxBytes,
    ) {
    }

    #[OA\Post(
        path: '/api/runner/opencode/artifact',
        summary: 'Загрузить файл-артефакт хода OpenCode',
        description: 'Вызывается Golang-раннером, публичный эндпоинт без JWT. '
            . 'Аутентификация — `X-Runner-Id` и `X-Runner-Signature`: '
            . 'hex(HMAC-SHA256(workflowId + "\\n" + name + "\\n" + rawBody, secret)). '
            . 'Тело — сырые байты файла.',
        tags: ['Runner'],
        security: [],
        parameters: [
            new OA\Parameter(
                name: 'workflowId',
                description: 'Идентификатор workflow хода',
                in: 'query',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'workspaceId',
                description: 'Идентификатор workspace-сессии хода',
                in: 'query',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'name',
                description: 'Исходное имя файла; нормализуется после проверки подписи',
                in: 'query',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'contentType',
                description: 'MIME-тип файла',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', default: 'application/octet-stream')
            ),
            new OA\Parameter(
                name: RunnerCallbackSignatureChecker::RUNNER_ID_HEADER,
                description: 'Идентификатор зарегистрированного раннера',
                in: 'header',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: RunnerCallbackSignatureChecker::HEADER_NAME,
                description: 'hex(HMAC-SHA256(workflowId + "\\n" + name + "\\n" + body, secret))',
                in: 'header',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/octet-stream',
                schema: new OA\Schema(type: 'string', format: 'binary')
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Файл зарегистрирован (повторная загрузка идемпотентна)',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/FajlHoda'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Неверная подпись раннера (runner.invalid_signature)'),
            new OA\Response(response: 404, description: 'Неизвестный раннер (runner.not_found) либо ход не найден'),
            new OA\Response(response: 409, description: 'Ход уже не выполняется (opencode.artifact.turn_not_running)'),
            new OA\Response(response: 413, description: 'Тело больше лимита (opencode.artifact.file_is_too_large)'),
            new OA\Response(response: 422, description: 'Нарушены имя, тип, кодировка, владелец или лимиты'),
        ]
    )]
    #[Route('/artifact', methods: ['POST'])]
    public function artifact(Request $request): HttpResponse
    {
        $workflowId = trim($request->query->getString('workflowId'));
        $workspaceId = trim($request->query->getString('workspaceId'));

        // Имя не trim'ится: раннер подписывает ровно это значение.
        $name = $request->query->getString('name');
        $contentType = $request->query->getString('contentType', self::DEFAULT_CONTENT_TYPE);

        $this->proveritRazmer($request);

        $body = $request->getContent();

        $runnerId = $request->headers->get(RunnerCallbackSignatureChecker::RUNNER_ID_HEADER);

        try {
            $signatureValid = $this->signatureChecker->estPodpisFajlaValidna(
                $runnerId,
                $workflowId,
                $name,
                $body,
                $request->headers->get(RunnerCallbackSignatureChecker::HEADER_NAME),
            );
        } catch (\DomainException) {
            throw new NotFoundHttpException('runner.not_found');
        }

        if (!$signatureValid) {
            throw new UnauthorizedHttpException('RunnerSignature', 'runner.invalid_signature');
        }

        try {
            $file = $this->useCase->handle(new PrinyatFajlHoda(
                $workflowId,
                $workspaceId,
                $name,
                $contentType,
                $body,
            ));
        } catch (\DomainException $exception) {
            throw $this->preobrazovatOshibku($exception);
        }

        return $this->json(Response::success($file->toArray()));
    }

    /**
     * Размер проверяется и по заголовку, и по фактически прочитанному телу:
     * заголовку доверять нельзя, а читать тело сверх лимита — тоже.
     */
    private function proveritRazmer(Request $request): void
    {
        $contentLength = $request->headers->get('Content-Length');

        if ($contentLength !== null && ctype_digit($contentLength) && (int) $contentLength > $this->artifactMaxBytes) {
            throw new HttpException(
                HttpResponse::HTTP_REQUEST_ENTITY_TOO_LARGE,
                'opencode.artifact.file_is_too_large',
            );
        }

        if (strlen($request->getContent()) > $this->artifactMaxBytes) {
            throw new HttpException(
                HttpResponse::HTTP_REQUEST_ENTITY_TOO_LARGE,
                'opencode.artifact.file_is_too_large',
            );
        }
    }

    /**
     * Доменная ошибка -> HTTP-исключение с тем же ключом перевода.
     * Неизвестные ключи не маскируются: пусть падают дальше.
     */
    private function preobrazovatOshibku(\DomainException $exception): \Throwable
    {
        $key = $exception->getMessage();
        $status = self::STATUS_BY_KEY[$key] ?? null;

        if ($status === null) {
            return $exception;
        }

        return new HttpException($status, $key);
    }
}
