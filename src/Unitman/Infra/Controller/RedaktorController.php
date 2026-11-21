<?php

declare(strict_types=1);

namespace App\Unitman\Infra\Controller;

use App\Unitman\Business\Command\Unit\Redaktor\OtkrepitRedaktoraOtUnita;
use App\Unitman\Business\Command\Unit\Redaktor\PoluchitSsylkuRedaktora;
use App\Unitman\Business\Command\Unit\Redaktor\PrikrepitRedaktoraKUnitu;
use App\Unitman\Business\Command\Unit\Redaktor\SpisokRedaktorovUnita;
use App\Unitman\Business\Command\Unit\Redaktor\VklyuchitRedaktirovanieUnita;
use App\Unitman\Business\Command\Unit\Redaktor\VykluchitRedaktirovanieUnita;
use App\Unitman\Business\UseCase\Unit\Redaktor\OtkrepitRedaktoraOtUnitaUseCase;
use App\Unitman\Business\UseCase\Unit\Redaktor\PoluchitSsylkuRedaktoraQuery;
use App\Unitman\Business\UseCase\Unit\Redaktor\PrikrepitRedaktoraKUnituUseCase;
use App\Unitman\Business\UseCase\Unit\Redaktor\SpisokRedaktorovUnitaQuery;
use App\Unitman\Business\UseCase\Unit\Redaktor\VklyuchitRedaktirovanieUnitaUseCase;
use App\Unitman\Business\UseCase\Unit\Redaktor\VykluchitRedaktirovanieUnitaUseCase;
use App\Utils\Model\Reponse\Response;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/unit')]
final class RedaktorController extends AbstractController
{
    #[OA\Post(
        path: '/api/unit/vklyuchitRedaktirovanie',
        summary: 'Включить редактирование файлов юнита',
        tags: ['Unit'],
        responses: [
            new OA\Response(response: 200, description: 'Режим редактирования включён (идемпотентно)'),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки'),
        ],
    )]
    #[Route('/vklyuchitRedaktirovanie', methods: ['POST'])]
    public function vklyuchitRedaktirovanie(
        #[MapRequestPayload] VklyuchitRedaktirovanieUnita $command,
        VklyuchitRedaktirovanieUnitaUseCase $useCase,
    ): JsonResponse {
        $useCase->handle($command);

        return $this->json(Response::successStub());
    }

    #[OA\Post(
        path: '/api/unit/vykluchitRedaktirovanie',
        summary: 'Выключить редактирование файлов юнита',
        tags: ['Unit'],
        responses: [
            new OA\Response(response: 200, description: 'Режим редактирования выключен (идемпотентно)'),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки'),
        ],
    )]
    #[Route('/vykluchitRedaktirovanie', methods: ['POST'])]
    public function vykluchitRedaktirovanie(
        #[MapRequestPayload] VykluchitRedaktirovanieUnita $command,
        VykluchitRedaktirovanieUnitaUseCase $useCase,
    ): JsonResponse {
        $useCase->handle($command);

        return $this->json(Response::successStub());
    }

    #[OA\Post(
        path: '/api/unit/prikrepitRedaktora',
        summary: 'Прикрепить редактора к юниту',
        tags: ['Unit'],
        responses: [
            new OA\Response(response: 200, description: 'Редактор прикреплён'),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки'),
        ],
    )]
    #[Route('/prikrepitRedaktora', methods: ['POST'])]
    public function prikrepitRedaktora(
        #[MapRequestPayload] PrikrepitRedaktoraKUnitu $command,
        PrikrepitRedaktoraKUnituUseCase $useCase,
    ): JsonResponse {
        $useCase->handle($command);

        return $this->json(Response::successStub());
    }

    #[OA\Post(
        path: '/api/unit/otkrepitRedaktora',
        summary: 'Открепить редактора от юнита',
        tags: ['Unit'],
        responses: [
            new OA\Response(response: 200, description: 'Редактор откреплён (идемпотентно)'),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки'),
        ],
    )]
    #[Route('/otkrepitRedaktora', methods: ['POST'])]
    public function otkrepitRedaktora(
        #[MapRequestPayload] OtkrepitRedaktoraOtUnita $command,
        OtkrepitRedaktoraOtUnitaUseCase $useCase,
    ): JsonResponse {
        $useCase->handle($command);

        return $this->json(Response::successStub());
    }

    #[OA\Post(
        path: '/api/unit/redaktory',
        summary: 'Список редакторов юнита с состоянием их токенов',
        tags: ['Unit'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Список редакторов',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/SpisokRedaktorov'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки'),
        ],
    )]
    #[Route('/redaktory', methods: ['POST'])]
    public function redaktory(
        #[MapRequestPayload] SpisokRedaktorovUnita $command,
        SpisokRedaktorovUnitaQuery $query,
    ): JsonResponse {
        return $this->json(Response::success($query->handle($command)));
    }

    #[OA\Post(
        path: '/api/unit/poluchitSsylkuRedaktora',
        summary: 'Персональная подписанная ссылка редактора',
        tags: ['Unit'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Ссылка редактора; личный токен не возвращается',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/SsylkaRedaktora'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки'),
        ],
    )]
    #[Route('/poluchitSsylkuRedaktora', methods: ['POST'])]
    public function poluchitSsylkuRedaktora(
        #[MapRequestPayload] PoluchitSsylkuRedaktora $command,
        PoluchitSsylkuRedaktoraQuery $query,
    ): JsonResponse {
        return $this->json(Response::success($query->handle($command)));
    }
}
