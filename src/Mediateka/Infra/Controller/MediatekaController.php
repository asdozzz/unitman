<?php

declare(strict_types=1);

namespace App\Mediateka\Infra\Controller;

use App\Mediateka\Business\Command\PrikrepitFaylyKUnitu;
use App\Mediateka\Business\Command\ZagruzitFaylUnita;
use App\Mediateka\Business\Model\FaylUnita;
use App\Mediateka\Business\Port\CurrentUserProvider;
use App\Mediateka\Business\UseCase\PoluchitFaylUnitaQuery;
use App\Mediateka\Business\UseCase\PoluchitFaylyUnitaQuery;
use App\Mediateka\Business\UseCase\PoluchitSsylkuDlyaFaylaQuery;
use App\Mediateka\Business\UseCase\PrikrepitFaylyKUnituUseCase;
use App\Mediateka\Business\UseCase\UdalitFaylUseCase;
use App\Mediateka\Business\UseCase\ZagruzitFaylUnitaUseCase;
use App\Utils\Model\Reponse\ErrorResponse\ErrorCodeEnum;
use App\Utils\Model\Reponse\Response;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

#[Route('/api/mediateka')]
final class MediatekaController extends AbstractController
{
    #[OA\Post(
        path: '/api/mediateka/upload',
        summary: 'Загрузить zip-архив',
        description: 'Принимает multipart/form-data. Допускаются только zip-архивы размером не более MEDIATEKA_MAX_FILE_SIZE (по умолчанию 200 МБ). Поле unitId необязательно: без него файл сохраняется как черновик (unitId = null) и привязывается к юниту отдельным запросом /api/mediateka/prikrepit либо при создании юнита. Поле name необязательно: при отсутствии берётся имя загруженного файла.',
        tags: ['Mediateka'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['file'],
                    properties: [
                        new OA\Property(
                            property: 'unitId',
                            type: 'string',
                            format: 'uuid',
                            nullable: true,
                            description: 'Идентификатор юнита; не передаётся при загрузке черновика'
                        ),
                        new OA\Property(
                            property: 'name',
                            type: 'string',
                            maxLength: 255,
                            description: 'Отображаемое имя файла; по умолчанию — имя загруженного файла'
                        ),
                        new OA\Property(
                            property: 'file',
                            type: 'string',
                            format: 'binary',
                            description: 'zip-архив (проверяется по сигнатуре)'
                        ),
                    ],
                    type: 'object'
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Файл загружен (SuccessEnvelope) либо ошибка валидации/хранилища (ErrorEnvelope) — в обоих случаях HTTP 200',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(
                            required: ['status', 'data'],
                            properties: [
                                new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                                new OA\Property(property: 'data', ref: '#/components/schemas/MediatekaFaylUnita'),
                            ],
                            type: 'object'
                        ),
                        new OA\Schema(ref: '#/components/schemas/ErrorEnvelope'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
        ]
    )]
    #[Route('/upload', methods: ['POST'])]
    public function upload(
        Request $request,
        ZagruzitFaylUnitaUseCase $useCase,
        CurrentUserProvider $currentUserProvider
    ): HttpResponse {
        try {
            $uploadedFile = $request->files->get('file');
            if ($uploadedFile === null) {
                return new JsonResponse(Response::error('mediateka.upload.file_is_missing', ErrorCodeEnum::DEFAULT));
            }

            $unitId = (string) $request->request->get('unitId', '');
            $name = (string) $request->request->get('name', $uploadedFile->getClientOriginalName());

            $stream = fopen($uploadedFile->getPathname(), 'rb');
            if ($stream === false) {
                return new JsonResponse(Response::error('mediateka.upload.file_is_unreadable'));
            }

            try {
                $file = $useCase->handle(new ZagruzitFaylUnita(
                    $unitId,
                    $name,
                    (int) $uploadedFile->getSize(),
                    $this->poluchitContentLength($request),
                    $currentUserProvider->getCurrentUserId(),
                    $stream
                ));
            } finally {
                fclose($stream);
            }

            return $this->json(Response::success($this->fileToArray($file)));
        } catch (Throwable $e) {
            return new JsonResponse(Response::error($e->getMessage()));
        }
    }

    #[OA\Post(
        path: '/api/mediateka/prikrepit',
        summary: 'Прикрепить ранее загруженные файлы к юниту',
        description: 'Привязывает черновики (файлы, загруженные без unitId) к юниту. Файл, уже привязанный к другому юниту, принять нельзя. Объект в MinIO не копируется — меняется только запись в реестре.',
        tags: ['Mediateka'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    required: ['unitId', 'fileIds'],
                    properties: [
                        new OA\Property(
                            property: 'unitId',
                            type: 'string',
                            format: 'uuid',
                            description: 'Идентификатор юнита'
                        ),
                        new OA\Property(
                            property: 'fileIds',
                            description: 'Идентификаторы файлов',
                            type: 'array',
                            items: new OA\Items(type: 'string', format: 'uuid')
                        ),
                    ],
                    type: 'object'
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Файлы привязаны (SuccessEnvelope) либо ошибка (ErrorEnvelope) — в обоих случаях HTTP 200',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(
                            required: ['status', 'data'],
                            properties: [
                                new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                                new OA\Property(
                                    property: 'data',
                                    description: 'Привязанные файлы',
                                    type: 'array',
                                    items: new OA\Items(ref: '#/components/schemas/MediatekaFaylUnita')
                                ),
                            ],
                            type: 'object'
                        ),
                        new OA\Schema(ref: '#/components/schemas/ErrorEnvelope'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
        ]
    )]
    #[Route('/prikrepit', methods: ['POST'])]
    public function prikrepit(Request $request, PrikrepitFaylyKUnituUseCase $useCase): HttpResponse
    {
        try {
            $body = json_decode($request->getContent(), true);
            if (!is_array($body)) {
                return new JsonResponse(Response::error('mediateka.bind.body_format_invalid'));
            }

            $unitId = $body['unitId'] ?? '';
            if (!is_string($unitId)) {
                return new JsonResponse(Response::error('mediateka.bind.body_format_invalid'));
            }

            $fileIds = $body['fileIds'] ?? [];
            if (!is_array($fileIds)) {
                return new JsonResponse(Response::error('mediateka.bind.file_ids_format_invalid'));
            }

            $files = $useCase->handle(new PrikrepitFaylyKUnitu($unitId, $fileIds));

            return $this->json(Response::success(
                array_map(fn (FaylUnita $file): array => $this->fileToArray($file), $files)
            ));
        } catch (Throwable $e) {
            return new JsonResponse(Response::error($e->getMessage()));
        }
    }

    #[OA\Get(
        path: '/api/mediateka/file/{id}',
        summary: 'Получить информацию о файле и ссылку на скачивание',
        tags: ['Mediateka'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Идентификатор файла (UUID)',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Информация о файле со ссылкой (SuccessEnvelope) либо файл не найден/ошибка (ErrorEnvelope) — в обоих случаях HTTP 200',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(
                            required: ['status', 'data'],
                            properties: [
                                new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                                new OA\Property(property: 'data', ref: '#/components/schemas/MediatekaFaylUnitaSoSsylkoy'),
                            ],
                            type: 'object'
                        ),
                        new OA\Schema(ref: '#/components/schemas/ErrorEnvelope'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
        ]
    )]
    #[Route('/file/{id}', methods: ['GET'])]
    public function fileInfo(
        string $id,
        PoluchitSsylkuDlyaFaylaQuery $ssylkaQuery,
        PoluchitFaylUnitaQuery $poluchitFaylQuery
    ): HttpResponse {
        try {
            $file = $poluchitFaylQuery->handle($id);
            if ($file === null) {
                return new JsonResponse(Response::error('mediateka.file.not_found'));
            }

            return $this->json(Response::success([
                ...$this->fileToArray($file),
                'url' => $ssylkaQuery->handle($file->getId()),
            ]));
        } catch (Throwable $e) {
            return new JsonResponse(Response::error($e->getMessage()));
        }
    }

    #[OA\Get(
        path: '/api/mediateka/unit/{unitId}',
        summary: 'Список файлов юнита',
        tags: ['Mediateka'],
        parameters: [
            new OA\Parameter(
                name: 'unitId',
                in: 'path',
                required: true,
                description: 'Идентификатор юнита (UUID)',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Файлы юнита (SuccessEnvelope) либо ошибка (ErrorEnvelope) — в обоих случаях HTTP 200',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(
                            required: ['status', 'data'],
                            properties: [
                                new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                                new OA\Property(
                                    property: 'data',
                                    description: 'Файлы юнита (пустой массив, если файлов нет)',
                                    type: 'array',
                                    items: new OA\Items(ref: '#/components/schemas/MediatekaFaylUnita')
                                ),
                            ],
                            type: 'object'
                        ),
                        new OA\Schema(ref: '#/components/schemas/ErrorEnvelope'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
        ]
    )]
    #[Route('/unit/{unitId}', methods: ['GET'])]
    public function unitFiles(string $unitId, PoluchitFaylyUnitaQuery $query): HttpResponse
    {
        try {
            $files = array_map(
                fn (FaylUnita $file): array => $this->fileToArray($file),
                $query->handle($unitId)
            );

            return $this->json(Response::success($files));
        } catch (Throwable $e) {
            return new JsonResponse(Response::error($e->getMessage()));
        }
    }

    #[OA\Delete(
        path: '/api/mediateka/file/{id}/udalit',
        summary: 'Удалить файл',
        description: 'Удаляет метаданные файла и объект в MinIO. При ошибке тело всё равно приходит с HTTP 200 в виде ErrorEnvelope.',
        tags: ['Mediateka'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Идентификатор файла (UUID)',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Файл удалён (SuccessEnvelope с data: null) либо ошибка (ErrorEnvelope) — в обоих случаях HTTP 200',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(
                            required: ['status', 'data'],
                            properties: [
                                new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                                new OA\Property(property: 'data', type: 'null', description: 'Пустой ответ: файл удалён'),
                            ],
                            type: 'object'
                        ),
                        new OA\Schema(ref: '#/components/schemas/ErrorEnvelope'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
        ]
    )]
    #[Route('/file/{id}/udalit', methods: ['DELETE'])]
    public function delete(string $id, UdalitFaylUseCase $useCase): HttpResponse
    {
        try {
            $useCase->handle($id);

            return $this->json(Response::successStub());
        } catch (Throwable $e) {
            return new JsonResponse(Response::error($e->getMessage()));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function fileToArray(FaylUnita $file): array
    {
        return $file->toArray();
    }

    /**
     * Content-Length приходит строкой; отсутствие заголовка или мусор трактуем как «не задан».
     */
    private function poluchitContentLength(Request $request): ?int
    {
        $rawValue = $request->headers->get('content-length');

        if ($rawValue === null || !ctype_digit($rawValue)) {
            return null;
        }

        return (int) $rawValue;
    }
}
