<?php

declare(strict_types=1);

namespace App\OpenCode\Infra\Controller;

use App\OpenCode\Business\Command\PoluchitSsylkuFajlaHoda;
use App\OpenCode\Business\Command\PoluchitTekstFajlaHoda;
use App\OpenCode\Business\UseCase\PoluchitSsylkuFajlaHodaQuery;
use App\OpenCode\Business\UseCase\PoluchitTekstFajlaHodaQuery;
use App\Utils\Model\Reponse\Response;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Файлы-артефакты хода для интерфейса.
 *
 * Все маршруты закрыты JWT. Владелец берётся из токена; чужой ход, чужая
 * сессия и чужой файл дают одинаковый 404, чтобы не раскрывать существование
 * данных.
 */
#[Route('/api/opencode/fajl')]
final class OpenCodeFileController extends AbstractController
{
    /**
     * @var array<string, int>
     */
    private const STATUS_BY_KEY = [
        'opencode.turn.not_found' => HttpResponse::HTTP_NOT_FOUND,
        'opencode.session.not_found' => HttpResponse::HTTP_NOT_FOUND,
        'opencode.artifact.not_found' => HttpResponse::HTTP_NOT_FOUND,
        'opencode.artifact.preview_unsupported' => HttpResponse::HTTP_UNPROCESSABLE_ENTITY,
        'opencode.artifact.preview_too_large' => HttpResponse::HTTP_UNPROCESSABLE_ENTITY,
        'opencode.artifact.content_invalid' => HttpResponse::HTTP_UNPROCESSABLE_ENTITY,
    ];

    #[OA\Post(
        path: '/api/opencode/fajl/ssylka',
        summary: 'Получить подписанную ссылку на файл-артефакт хода',
        tags: ['OpenCode'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['turnId', 'fileId'],
                properties: [
                    new OA\Property(property: 'turnId', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'fileId', type: 'string', format: 'uuid'),
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Ссылка на скачивание файла',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/FajlHodaSsilka'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 404, description: 'Ход, сессия или файл не найдены либо чужие'),
            new OA\Response(response: 500, description: 'Ошибка обработки (ErrorEnvelope)'),
        ]
    )]
    #[Route('/ssylka', methods: ['POST'])]
    public function ssylka(
        PoluchitSsylkuFajlaHoda $command,
        PoluchitSsylkuFajlaHodaQuery $query,
    ): HttpResponse {
        try {
            $data = $query->handle($command);
        } catch (\DomainException $exception) {
            throw $this->preobrazovatOshibku($exception);
        }

        return $this->json(Response::success($data));
    }

    #[OA\Post(
        path: '/api/opencode/fajl/tekst',
        summary: 'Получить текст файла-артефакта хода для preview',
        tags: ['OpenCode'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['turnId', 'fileId'],
                properties: [
                    new OA\Property(property: 'turnId', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'fileId', type: 'string', format: 'uuid'),
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Текст файла',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/FajlHodaTekst'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 404, description: 'Ход, сессия или файл не найдены либо чужие'),
            new OA\Response(response: 422, description: 'Тип не previewable, файл слишком большой или не UTF-8'),
            new OA\Response(response: 500, description: 'Ошибка обработки (ErrorEnvelope)'),
        ]
    )]
    #[Route('/tekst', methods: ['POST'])]
    public function tekst(
        PoluchitTekstFajlaHoda $command,
        PoluchitTekstFajlaHodaQuery $query,
    ): HttpResponse {
        try {
            $data = $query->handle($command);
        } catch (\DomainException $exception) {
            throw $this->preobrazovatOshibku($exception);
        }

        return $this->json(Response::success($data));
    }

    private function preobrazovatOshibku(\DomainException $exception): \Throwable
    {
        $key = $exception->getMessage();
        $status = self::STATUS_BY_KEY[$key] ?? null;

        if ($status === null) {
            return $exception;
        }

        if ($status === HttpResponse::HTTP_NOT_FOUND) {
            return new NotFoundHttpException($key, $exception);
        }

        return new HttpException($status, $key, $exception);
    }
}
