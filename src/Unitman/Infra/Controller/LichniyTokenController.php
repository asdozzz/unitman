<?php

declare(strict_types=1);

namespace App\Unitman\Infra\Controller;

use App\Unitman\Business\Command\LichniyToken\DobavitLichniyToken;
use App\Unitman\Business\Command\LichniyToken\PereproveritLichniyToken;
use App\Unitman\Business\Command\LichniyToken\SpisokLichnihTokenov;
use App\Unitman\Business\Command\LichniyToken\UdalitLichniyToken;
use App\Unitman\Business\UseCase\LichniyToken\DobavitLichniyTokenUseCase;
use App\Unitman\Business\UseCase\LichniyToken\PereproveritLichniyTokenUseCase;
use App\Unitman\Business\UseCase\LichniyToken\SpisokLichnihTokenovQuery;
use App\Unitman\Business\UseCase\LichniyToken\UdalitLichniyTokenUseCase;
use App\Utils\Model\Reponse\Response;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/lichniyToken')]
final class LichniyTokenController extends AbstractController
{
    #[OA\Post(
        path: '/api/lichniyToken/dobavit',
        summary: 'Добавить личный токен хранилища',
        tags: ['LichniyToken'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Токен добавлен; секрет не возвращается, только hint',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/LichniyTokenPublic'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки'),
        ],
    )]
    #[Route('/dobavit', methods: ['POST'])]
    public function dobavit(
        #[MapRequestPayload] DobavitLichniyToken $command,
        DobavitLichniyTokenUseCase $useCase,
    ): JsonResponse {
        return $this->json(Response::success($useCase->handle($command)));
    }

    #[OA\Post(
        path: '/api/lichniyToken/spisok',
        summary: 'Список личных токенов (админ — все)',
        tags: ['LichniyToken'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Список токенов без секретов',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/LichniyTokenPublic')
                        ),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки'),
        ],
    )]
    #[Route('/spisok', methods: ['POST'])]
    public function spisok(
        #[MapRequestPayload] SpisokLichnihTokenov $command,
        SpisokLichnihTokenovQuery $query,
    ): JsonResponse {
        return $this->json(Response::success($query->handle($command)));
    }

    #[OA\Post(
        path: '/api/lichniyToken/pereproverit',
        summary: 'Перепроверить права личного токена',
        tags: ['LichniyToken'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Обновлённый объём прав',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/LichniyTokenPublic'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки'),
        ],
    )]
    #[Route('/pereproverit', methods: ['POST'])]
    public function pereproverit(
        #[MapRequestPayload] PereproveritLichniyToken $command,
        PereproveritLichniyTokenUseCase $useCase,
    ): JsonResponse {
        return $this->json(Response::success($useCase->handle($command)));
    }

    #[OA\Post(
        path: '/api/lichniyToken/udalit',
        summary: 'Удалить личный токен',
        tags: ['LichniyToken'],
        responses: [
            new OA\Response(response: 200, description: 'Пустой ответ: токен удалён'),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки'),
        ],
    )]
    #[Route('/udalit', methods: ['POST'])]
    public function udalit(
        #[MapRequestPayload] UdalitLichniyToken $command,
        UdalitLichniyTokenUseCase $useCase,
    ): JsonResponse {
        $useCase->handle($command);

        return $this->json(Response::successStub());
    }
}
