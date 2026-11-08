<?php

declare(strict_types=1);

namespace App\Unitman\Infra\Controller;

use App\Unitman\Business\Command\VolumeType\DobavitTipVoluma;
use App\Unitman\Business\Command\VolumeType\OtkrepitFaylOtTipaVoluma;
use App\Unitman\Business\Command\VolumeType\PoluchitTipyVolumov;
use App\Unitman\Business\Command\VolumeType\PrikrepitFaylKTipuVoluma;
use App\Unitman\Business\Command\VolumeType\UdalitTipVoluma;
use App\Unitman\Business\ReadModel\VolumeType\VolumeTypeList;
use App\Unitman\Business\UseCase\VolumeType\DobavitTipVolumaUseCase;
use App\Unitman\Business\UseCase\VolumeType\OtkrepitFaylOtTipaVolumaUseCase;
use App\Unitman\Business\UseCase\VolumeType\PoluchitTipyVolumovQuery;
use App\Unitman\Business\UseCase\VolumeType\PrikrepitFaylKTipuVolumaUseCase;
use App\Unitman\Business\UseCase\VolumeType\UdalitTipVolumaUseCase;
use App\Utils\Model\Reponse\Response;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Каталог типов томов `unitman_volumes` проекта.
 *
 * Чтение доступно членам проекта, изменение — только администраторам проекта.
 */
#[Route('/api/volumeType')]
final class VolumeTypeController extends AbstractController
{
    #[OA\Post(
        path: '/api/volumeType/list',
        summary: 'Список типов томов проекта',
        tags: ['VolumeType'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/VolumeTypeListRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Типы томов проекта с привязанными архивами',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(
                            property: 'data',
                            description: 'Типы томов проекта',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/VolumeTypeList')
                        ),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки (FailEnvelope либо ErrorEnvelope)'),
        ]
    )]
    #[Route('/list', methods: ['POST'])]
    public function list(#[MapRequestPayload] PoluchitTipyVolumov $command, PoluchitTipyVolumovQuery $query): HttpResponse
    {
        $data = array_map(
            static fn (VolumeTypeList $type): array => $type->toArray(),
            $query->handle($command)
        );

        return $this->json(Response::success($data));
    }

    #[OA\Post(
        path: '/api/volumeType/add',
        summary: 'Добавить тип тома',
        tags: ['VolumeType'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/AddVolumeType')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Пустой ответ: тип тома добавлен',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(property: 'data', type: 'null', description: 'Пустой ответ: тип тома добавлен'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки (FailEnvelope либо ErrorEnvelope)'),
        ]
    )]
    #[Route('/add', methods: ['POST'])]
    public function add(#[MapRequestPayload] DobavitTipVoluma $command, DobavitTipVolumaUseCase $useCase): HttpResponse
    {
        $useCase->handle($command);

        return $this->json(Response::successStub());
    }

    #[OA\Post(
        path: '/api/volumeType/delete',
        summary: 'Удалить тип тома',
        tags: ['VolumeType'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/DeleteVolumeType')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Пустой ответ: тип тома удалён',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(property: 'data', type: 'null', description: 'Пустой ответ: тип тома удалён'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки (FailEnvelope либо ErrorEnvelope)'),
        ]
    )]
    #[Route('/delete', methods: ['POST'])]
    public function delete(#[MapRequestPayload] UdalitTipVoluma $command, UdalitTipVolumaUseCase $useCase): HttpResponse
    {
        $useCase->handle($command);

        return $this->json(Response::successStub());
    }

    #[OA\Post(
        path: '/api/volumeType/file/add',
        summary: 'Прикрепить архив к типу тома',
        tags: ['VolumeType'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/AddVolumeTypeFile')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Пустой ответ: архив прикреплён к типу тома',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(property: 'data', type: 'null', description: 'Пустой ответ: архив прикреплён'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки (FailEnvelope либо ErrorEnvelope)'),
        ]
    )]
    #[Route('/file/add', methods: ['POST'])]
    public function addFile(
        #[MapRequestPayload] PrikrepitFaylKTipuVoluma $command,
        PrikrepitFaylKTipuVolumaUseCase $useCase
    ): HttpResponse {
        $useCase->handle($command);

        return $this->json(Response::successStub());
    }

    #[OA\Post(
        path: '/api/volumeType/file/delete',
        summary: 'Открепить архив от типа тома',
        tags: ['VolumeType'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/DeleteVolumeTypeFile')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Пустой ответ: архив откреплён от типа тома',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(property: 'data', type: 'null', description: 'Пустой ответ: архив откреплён'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки (FailEnvelope либо ErrorEnvelope)'),
        ]
    )]
    #[Route('/file/delete', methods: ['POST'])]
    public function deleteFile(
        #[MapRequestPayload] OtkrepitFaylOtTipaVoluma $command,
        OtkrepitFaylOtTipaVolumaUseCase $useCase
    ): HttpResponse {
        $useCase->handle($command);

        return $this->json(Response::successStub());
    }
}
