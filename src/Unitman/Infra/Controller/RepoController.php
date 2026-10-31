<?php

namespace App\Unitman\Infra\Controller;

use App\Unitman\Business\Command\Repo\AddRepo;
use App\Unitman\Business\Command\Repo\ChangeCredentialsOfRepo;
use App\Unitman\Business\Command\Repo\CheckAccessToRepo;
use App\Unitman\Business\Command\Repo\DeleteRepo;
use App\Unitman\Business\Command\Repo\GetActiveRepoList;
use App\Unitman\Business\Command\Repo\GetRepoList;
use App\Unitman\Business\Command\Repo\GetRepoTypeList;
use App\Unitman\Business\Command\Repo\PoluchitSpisokProektovRepi;
use App\Unitman\Business\UseCase\Repo\AddRepoUseCase;
use App\Unitman\Business\UseCase\Repo\ChangeCredentialsOfRepoUseCase;
use App\Unitman\Business\UseCase\Repo\CheckAccessToRepoUseCase;
use App\Unitman\Business\UseCase\Repo\DeleteRepoUseCase;
use App\Unitman\Business\UseCase\Repo\GetActiveRepoListQuery;
use App\Unitman\Business\UseCase\Repo\GetRepoListQuery;
use App\Unitman\Business\UseCase\Repo\GetRepoTypeListQuery;
use App\Unitman\Business\UseCase\Repo\PoluchitSpisokProektovRepiUseCase;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/repo')]
final class RepoController extends AbstractController
{
    #[OA\Post(
        path: '/api/repo/add',
        summary: 'Добавить репозиторий',
        tags: ['Repo'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/AddRepo')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Пустой ответ: репозиторий добавлен',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(property: 'data', type: 'null', description: 'Пустой ответ: репозиторий добавлен'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки (FailEnvelope либо ErrorEnvelope)'),
        ]
    )]
    #[Route('/add', methods: ['POST'])]
    public function add(#[MapRequestPayload] AddRepo $command, AddRepoUseCase $useCase): Response
    {
        $useCase->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::successStub());
    }

    #[OA\Post(
        path: '/api/repo/list',
        summary: 'Список репозиториев',
        tags: ['Repo'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/GetRepoList')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Список репозиториев',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(
                            property: 'data',
                            description: 'Список репозиториев',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/Repo')
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
    public function list(#[MapRequestPayload] GetRepoList $command, GetRepoListQuery $query): Response
    {
        $data = $query->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::success($data));
    }

    #[OA\Post(
        path: '/api/repo/projectList',
        summary: 'Список проектов репозитория',
        tags: ['Repo'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/PoluchitSpisokProektovRepi')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Проекты, доступные в репозитории',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(
                            property: 'data',
                            description: 'Проекты, доступные в репозитории',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/RepoProject')
                        ),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки (FailEnvelope либо ErrorEnvelope)'),
        ]
    )]
    #[Route('/projectList', methods: ['POST'])]
    public function projectList(#[MapRequestPayload] PoluchitSpisokProektovRepi $command, PoluchitSpisokProektovRepiUseCase $query): Response
    {
        $data = $query->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::success($data));
    }

    #[OA\Post(
        path: '/api/repo/activeList',
        summary: 'Список активных репозиториев',
        tags: ['Repo'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/GetActiveRepoList')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Список активных репозиториев',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(
                            property: 'data',
                            description: 'Список активных репозиториев',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/Repo')
                        ),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки (FailEnvelope либо ErrorEnvelope)'),
        ]
    )]
    #[Route('/activeList', methods: ['POST'])]
    public function activeList(#[MapRequestPayload] GetActiveRepoList $command, GetActiveRepoListQuery $query): Response
    {
        $data = $query->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::success($data));
    }

    #[OA\Post(
        path: '/api/repo/type/list',
        summary: 'Список типов репозиториев',
        tags: ['Repo'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/GetRepoTypeList')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Поддерживаемые типы репозиториев',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(
                            property: 'data',
                            description: 'Поддерживаемые типы репозиториев',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/RepoType')
                        ),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки (FailEnvelope либо ErrorEnvelope)'),
        ]
    )]
    #[Route('/type/list', methods: ['POST'])]
    public function typeList(#[MapRequestPayload] GetRepoTypeList $command, GetRepoTypeListQuery $query): Response
    {
        $data = $query->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::success($data));
    }

    #[OA\Post(
        path: '/api/repo/delete',
        summary: 'Удалить репозиторий',
        tags: ['Repo'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/DeleteRepo')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Пустой ответ: репозиторий удалён',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(property: 'data', type: 'null', description: 'Пустой ответ: репозиторий удалён'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки (FailEnvelope либо ErrorEnvelope)'),
        ]
    )]
    #[Route('/delete', methods: ['POST'])]
    public function delete(#[MapRequestPayload] DeleteRepo $command, DeleteRepoUseCase $useCase): Response
    {
        $useCase->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::successStub());
    }

    #[OA\Post(
        path: '/api/repo/changeCredentials',
        summary: 'Изменить учётные данные репозитория',
        tags: ['Repo'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ChangeCredentialsOfRepo')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Пустой ответ: учётные данные изменены',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(property: 'data', type: 'null', description: 'Пустой ответ: учётные данные изменены'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки (FailEnvelope либо ErrorEnvelope)'),
        ]
    )]
    #[Route('/changeCredentials', methods: ['POST'])]
    public function changeCredentials(#[MapRequestPayload] ChangeCredentialsOfRepo $command, ChangeCredentialsOfRepoUseCase $useCase): Response
    {
        $useCase->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::successStub());
    }

    #[OA\Post(
        path: '/api/repo/confirm',
        summary: 'Проверить доступ к репозиторию',
        tags: ['Repo'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/CheckAccessToRepo')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Пустой ответ: доступ к репозиторию подтверждён',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(property: 'data', type: 'null', description: 'Пустой ответ: доступ к репозиторию подтверждён'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки (FailEnvelope либо ErrorEnvelope)'),
        ]
    )]
    #[Route('/confirm', methods: ['POST'])]
    public function checkAccess(#[MapRequestPayload] CheckAccessToRepo $command, CheckAccessToRepoUseCase $useCase): Response
    {
        $useCase->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::successStub());
    }
}
