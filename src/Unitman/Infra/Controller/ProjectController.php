<?php
declare(strict_types=1);

namespace App\Unitman\Infra\Controller;

use App\Unitman\Business\Command\Project\AddProject;
use App\Unitman\Business\Command\Project\AddUserToProject;
use App\Unitman\Business\Command\Project\DisableProject;
use App\Unitman\Business\Command\Project\DobavitPeremenuyuVProekt;
use App\Unitman\Business\Command\Project\DobavitSobitieIzHranilisha;
use App\Unitman\Business\Command\Project\EnableProject;
use App\Unitman\Business\Command\Project\ForceRemoveProject;
use App\Unitman\Business\Command\Project\GetActiveProjectList;
use App\Unitman\Business\Command\Project\GetProjectList;
use App\Unitman\Business\Command\Project\GetProjectRunnerJobs;
use App\Unitman\Business\Command\Project\GetProjectRunnerJobSteps;
use App\Unitman\Business\Command\Project\IzmenitZnacheniePeremenoiProekta;
use App\Unitman\Business\Command\Project\ObnovitNastroikiHuka;
use App\Unitman\Business\Command\Project\PoluchitMoiProekti;
use App\Unitman\Business\Command\Project\PoluchitSpisokPeremenihProekta;
use App\Unitman\Business\Command\Project\PoluchitSpisokPolzovateleiProekta;
use App\Unitman\Business\Command\Project\PoluchitSpisokVetokProekta;
use App\Unitman\Business\Command\Project\PostavitVOcheredNaSborku;
use App\Unitman\Business\Command\Project\PostavitVOcheredNaUdalenie;
use App\Unitman\Business\Command\Project\ProveritProxyHost;
use App\Unitman\Business\Command\Project\RemoveUserFromProject;
use App\Unitman\Business\Command\Project\UdalitPeremenuyuIzProekta;
use App\Unitman\Business\Command\Project\UpdateProjectData;
use App\Unitman\Business\UseCase\Project\AddProjectUseCase;
use App\Unitman\Business\UseCase\Project\AddUserToProjectUseCase;
use App\Unitman\Business\UseCase\Project\DisableProjectUseCase;
use App\Unitman\Business\UseCase\Project\DobavitSobitieIzHranilishaUseCase;
use App\Unitman\Business\UseCase\Project\EnableProjectUseCase;
use App\Unitman\Business\UseCase\Project\ForceRemoveProjectUseCase;
use App\Unitman\Business\UseCase\Project\GetActiveProjectListQuery;
use App\Unitman\Business\UseCase\Project\GetProjectListQuery;
use App\Unitman\Business\UseCase\Project\GetProjectRunnerJobsQuery;
use App\Unitman\Business\UseCase\Project\GetProjectRunnerJobStepsQuery;
use App\Unitman\Business\UseCase\Project\ObnovitNastroikiHukaUseCase;
use App\Unitman\Business\UseCase\Project\PoluchitMoiProektiQuery;
use App\Unitman\Business\UseCase\Project\PoluchitSpisokPeremenihProektaQuery;
use App\Unitman\Business\UseCase\Project\PoluchitSpisokPolzovateleiProektaQuery;
use App\Unitman\Business\UseCase\Project\PoluchitSpisokProektovPolzovatelyaUseCase;
use App\Unitman\Business\UseCase\Project\PoluchitSpisokVetokProektaQuery;
use App\Unitman\Business\UseCase\Project\PostavitVOcheredNaSborkuUseCase;
use App\Unitman\Business\UseCase\Project\PostavitVOcheredNaUdalenieUseCase;
use App\Unitman\Business\UseCase\Project\ProzessDobavleniyaPeremenoiVProekt;
use App\Unitman\Business\UseCase\Project\ProzessIzmeneniyaZnacheniyaPeremenoiProekta;
use App\Unitman\Business\UseCase\Project\ProzessUdaleniyaPeremenoiIzProekta;
use App\Unitman\Business\UseCase\Project\RemoveUserFromProjectUseCase;
use App\Unitman\Business\UseCase\Project\UpdateProjectDataUseCase;
use App\Unitman\Infra\Repository\Project\SqlProjectListRepository;
use App\Unitman\Infra\Repository\Unit\StatistikaPoProektuRepository;
use App\Unitman\Infra\Service\ServiceProverkiProxyHost;
use App\Utils\Service\ExceptionTranslator;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/project')]
final class ProjectController extends AbstractController
{
    public function __construct(private ExceptionTranslator $exceptionTranslator)
    {
    }

    #[OA\Post(
        path: '/api/project/list',
        summary: 'Список проектов',
        tags: ['Project'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/GetProjectList')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Список проектов, ограниченный фильтрами запроса',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(
                            property: 'data',
                            description: 'Список проектов, ограниченный фильтрами запроса',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/ProjectListItem')
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
    public function list(#[MapRequestPayload] GetProjectList $command, GetProjectListQuery $query): Response
    {
        $data = $query->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::success($data));
    }

    #[OA\Post(
        path: '/api/project/read/{id}',
        summary: 'Проект по идентификатору',
        tags: ['Project'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Проект по идентификатору',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/ProjectListItem'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки (FailEnvelope либо ErrorEnvelope)'),
        ]
    )]
    #[Route('/read/{id}', methods: ['POST'])]
    public function byId(string $id, SqlProjectListRepository $repository): Response
    {
        $data = $repository->getById($id);
        return $this->json(\App\Utils\Model\Reponse\Response::success($data));
    }

    #[OA\Post(
        path: '/api/project/activeList',
        summary: 'Список активных проектов',
        tags: ['Project'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/GetActiveProjectList')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Список активных проектов',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(
                            property: 'data',
                            description: 'Список активных проектов',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/ProjectListItem')
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
    public function activeList(#[MapRequestPayload] GetActiveProjectList $command, GetActiveProjectListQuery $query): Response
    {
        $data = $query->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::success($data));
    }

    #[OA\Post(
        path: '/api/project/my',
        summary: 'Мои проекты',
        tags: ['Project'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/PoluchitMoiProekti')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Проекты текущего пользователя',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(
                            property: 'data',
                            description: 'Проекты текущего пользователя',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/ProjectListItem')
                        ),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки (FailEnvelope либо ErrorEnvelope)'),
        ]
    )]
    #[Route('/my', methods: ['POST'])]
    public function my(#[MapRequestPayload] PoluchitMoiProekti $command, PoluchitMoiProektiQuery $query): Response
    {
        $data = $query->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::success($data));
    }

    #[OA\Post(
        path: '/api/project/user/{id}',
        summary: 'Проекты пользователя',
        tags: ['Project'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Проекты указанного пользователя',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(
                            property: 'data',
                            description: 'Проекты указанного пользователя',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/ProjectListItem')
                        ),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки (FailEnvelope либо ErrorEnvelope)'),
        ]
    )]
    #[Route('/user/{id}', methods: ['POST'])]
    public function byUserId(string $id, PoluchitSpisokProektovPolzovatelyaUseCase $query): Response
    {
        $data = $query->handle($id);
        return $this->json(\App\Utils\Model\Reponse\Response::success($data));
    }

    #[OA\Post(
        path: '/api/project/add',
        summary: 'Добавить проект',
        tags: ['Project'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/AddProject')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Пустой ответ: проект добавлен',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(property: 'data', type: 'null', description: 'Пустой ответ: проект добавлен'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки (FailEnvelope либо ErrorEnvelope)'),
        ]
    )]
    #[Route('/add', methods: ['POST'])]
    public function add(#[MapRequestPayload] AddProject $command, AddProjectUseCase $useCase, PostavitVOcheredNaSborkuUseCase $postavitVOcheredNaSborkuUseCase): Response
    {
        $id = $useCase->handle($command);
        $postavitVOcheredNaSborkuUseCase->handle(new PostavitVOcheredNaSborku($id));
        return $this->json(\App\Utils\Model\Reponse\Response::successStub());
    }

    #[OA\Post(
        path: '/api/project/updateData',
        summary: 'Обновить данные проекта',
        tags: ['Project'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UpdateProjectData')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Пустой ответ: данные проекта обновлены',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(property: 'data', type: 'null', description: 'Пустой ответ: данные проекта обновлены'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки (FailEnvelope либо ErrorEnvelope)'),
        ]
    )]
    #[Route('/updateData', methods: ['POST'])]
    public function updateData(#[MapRequestPayload] UpdateProjectData $command, UpdateProjectDataUseCase $useCase): Response
    {
        $useCase->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::successStub());
    }

    #[OA\Post(
        path: '/api/project/remove',
        summary: 'Поставить проект в очередь на удаление',
        tags: ['Project'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/PostavitVOcheredNaUdalenie')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Пустой ответ: проект поставлен в очередь на удаление. При перехваченной ошибке PHP тело всё равно приходит с HTTP 200 в виде ErrorEnvelope',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(property: 'data', type: 'null', description: 'Пустой ответ: проект поставлен в очередь на удаление'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки (FailEnvelope либо ErrorEnvelope)'),
        ]
    )]
    #[Route('/remove', methods: ['POST'])]
    public function remove(#[MapRequestPayload] PostavitVOcheredNaUdalenie $command, PostavitVOcheredNaUdalenieUseCase $useCase): Response
    {
        try {
            $useCase->handle($command);
            return $this->json(\App\Utils\Model\Reponse\Response::successStub());
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($this->exceptionTranslator->translateThrowable($error)));
        }

    }

    #[OA\Post(
        path: '/api/project/forceRemove',
        summary: 'Принудительно удалить проект',
        tags: ['Project'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ForceRemoveProject')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Пустой ответ: проект удалён принудительно',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(property: 'data', type: 'null', description: 'Пустой ответ: проект удалён принудительно'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки (FailEnvelope либо ErrorEnvelope)'),
        ]
    )]
    #[Route('/forceRemove', methods: ['POST'])]
    public function forceRemove(#[MapRequestPayload] ForceRemoveProject $command, ForceRemoveProjectUseCase $useCase): Response
    {
        $useCase->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::successStub());
    }

    #[OA\Post(
        path: '/api/project/enable',
        summary: 'Включить проект',
        tags: ['Project'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/EnableProject')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Пустой ответ: проект включён',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(property: 'data', type: 'null', description: 'Пустой ответ: проект включён'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки (FailEnvelope либо ErrorEnvelope)'),
        ]
    )]
    #[Route('/enable', methods: ['POST'])]
    public function enable(#[MapRequestPayload] EnableProject $command, EnableProjectUseCase $useCase): Response
    {
        $useCase->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::successStub());
    }

    #[OA\Post(
        path: '/api/project/disable',
        summary: 'Выключить проект',
        tags: ['Project'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/DisableProject')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Пустой ответ: проект выключен',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(property: 'data', type: 'null', description: 'Пустой ответ: проект выключен'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки (FailEnvelope либо ErrorEnvelope)'),
        ]
    )]
    #[Route('/disable', methods: ['POST'])]
    public function disable(#[MapRequestPayload] DisableProject $command, DisableProjectUseCase $useCase): Response
    {
        $useCase->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::successStub());
    }

    #[OA\Post(
        path: '/api/project/usersList',
        summary: 'Пользователи проекта',
        tags: ['Project'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/PoluchitSpisokPolzovateleiProekta')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Пользователи проекта',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(
                            property: 'data',
                            description: 'Пользователи проекта',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/ProjectUser')
                        ),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки (FailEnvelope либо ErrorEnvelope)'),
        ]
    )]
    #[Route('/usersList', methods: ['POST'])]
    public function usersList(#[MapRequestPayload] PoluchitSpisokPolzovateleiProekta $command, PoluchitSpisokPolzovateleiProektaQuery $query): Response
    {
        $data = $query->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::success($data));
    }

    #[OA\Post(
        path: '/api/project/addUser',
        summary: 'Добавить пользователя в проект',
        tags: ['Project'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/AddUserToProject')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Пустой ответ: пользователь добавлен в проект',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(property: 'data', type: 'null', description: 'Пустой ответ: пользователь добавлен в проект'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки (FailEnvelope либо ErrorEnvelope)'),
        ]
    )]
    #[Route('/addUser', methods: ['POST'])]
    public function addUser(#[MapRequestPayload] AddUserToProject $command, AddUserToProjectUseCase $useCase): Response
    {
        $useCase->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::successStub());
    }

    #[OA\Post(
        path: '/api/project/removeUser',
        summary: 'Удалить пользователя из проекта',
        tags: ['Project'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/RemoveUserFromProject')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Пустой ответ: пользователь удалён из проекта',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(property: 'data', type: 'null', description: 'Пустой ответ: пользователь удалён из проекта'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки (FailEnvelope либо ErrorEnvelope)'),
        ]
    )]
    #[Route('/removeUser', methods: ['POST'])]
    public function removeUser(#[MapRequestPayload] RemoveUserFromProject $command, RemoveUserFromProjectUseCase $useCase): Response
    {
        $useCase->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::successStub());
    }

    #[OA\Post(
        path: '/api/project/spisokPeremenihProekta',
        summary: 'Переменные проекта',
        tags: ['Project'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/PoluchitSpisokPeremenihProekta')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Переменные проекта',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(
                            property: 'data',
                            description: 'Переменные проекта',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/ProjectVariable')
                        ),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки (FailEnvelope либо ErrorEnvelope)'),
        ]
    )]
    #[Route('/spisokPeremenihProekta', methods: ['POST'])]
    public function variablesList(#[MapRequestPayload] PoluchitSpisokPeremenihProekta $command, PoluchitSpisokPeremenihProektaQuery $query): Response
    {
        $data = $query->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::success($data));
    }

    #[OA\Post(
        path: '/api/project/dobavitPeremenuyu',
        summary: 'Добавить переменную в проект',
        tags: ['Project'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/DobavitPeremenuyuVProekt')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Пустой ответ: переменная добавлена в проект',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(property: 'data', type: 'null', description: 'Пустой ответ: переменная добавлена в проект'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки (FailEnvelope либо ErrorEnvelope)'),
        ]
    )]
    #[Route('/dobavitPeremenuyu', methods: ['POST'])]
    public function addVariable(#[MapRequestPayload] DobavitPeremenuyuVProekt $command, ProzessDobavleniyaPeremenoiVProekt $useCase): Response
    {
        $useCase->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::successStub());
    }

    #[OA\Post(
        path: '/api/project/udalitPeremenuyu',
        summary: 'Удалить переменную из проекта',
        tags: ['Project'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UdalitPeremenuyuIzProekta')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Пустой ответ: переменная удалена из проекта',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(property: 'data', type: 'null', description: 'Пустой ответ: переменная удалена из проекта'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки (FailEnvelope либо ErrorEnvelope)'),
        ]
    )]
    #[Route('/udalitPeremenuyu', methods: ['POST'])]
    public function removeVariable(#[MapRequestPayload] UdalitPeremenuyuIzProekta $command, ProzessUdaleniyaPeremenoiIzProekta $useCase): Response
    {
        $useCase->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::successStub());
    }

    #[OA\Post(
        path: '/api/project/izmenitPeremenuyu',
        summary: 'Изменить значение переменной проекта',
        tags: ['Project'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/IzmenitZnacheniePeremenoiProekta')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Пустой ответ: значение переменной проекта изменено',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(property: 'data', type: 'null', description: 'Пустой ответ: значение переменной проекта изменено'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки (FailEnvelope либо ErrorEnvelope)'),
        ]
    )]
    #[Route('/izmenitPeremenuyu', methods: ['POST'])]
    public function izmenitPeremenuyu(#[MapRequestPayload] IzmenitZnacheniePeremenoiProekta $command, ProzessIzmeneniyaZnacheniyaPeremenoiProekta $useCase): Response
    {
        $useCase->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::successStub());
    }

    #[OA\Post(
        path: '/api/project/build',
        summary: 'Поставить проект в очередь на сборку',
        tags: ['Project'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/PostavitVOcheredNaSborku')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Пустой ответ: проект поставлен в очередь на сборку',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(property: 'data', type: 'null', description: 'Пустой ответ: проект поставлен в очередь на сборку'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки (FailEnvelope либо ErrorEnvelope)'),
        ]
    )]
    #[Route('/build', methods: ['POST'])]
    public function build(#[MapRequestPayload] PostavitVOcheredNaSborku $command, PostavitVOcheredNaSborkuUseCase $useCase): Response
    {
        $useCase->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::successStub());
    }

    #[OA\Post(
        path: '/api/project/obnovitNastroikiHuka',
        summary: 'Обновить настройки webhook проекта',
        tags: ['Project'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ObnovitNastroikiHuka')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Пустой ответ: настройки webhook проекта обновлены',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(property: 'data', type: 'null', description: 'Пустой ответ: настройки webhook проекта обновлены'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки (FailEnvelope либо ErrorEnvelope)'),
        ]
    )]
    #[Route('/obnovitNastroikiHuka', methods: ['POST'])]
    public function obnovitNastroikiHuka(#[MapRequestPayload] ObnovitNastroikiHuka $command, ObnovitNastroikiHukaUseCase $useCase): Response
    {
        $useCase->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::successStub());
    }

    #[OA\Post(
        path: '/api/project/branchesList',
        summary: 'Список веток проекта',
        tags: ['Project'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/PoluchitSpisokVetokProekta')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Ветки проекта в хранилище',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(
                            property: 'data',
                            description: 'Ветки проекта в хранилище',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/ProjectBranch')
                        ),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки (FailEnvelope либо ErrorEnvelope)'),
        ]
    )]
    #[Route('/branchesList', methods: ['POST'])]
    public function branchesList(#[MapRequestPayload] PoluchitSpisokVetokProekta $command, PoluchitSpisokVetokProektaQuery $query): Response
    {
        $data = $query->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::success($data));
    }

    #[OA\Post(
        path: '/api/project/{id}/hook',
        summary: 'Webhook события Git-провайдера',
        description: 'Публичный эндпоинт без JWT: тело запроса передаётся как есть в хранилище событий',
        tags: ['Webhook'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(type: 'object', description: 'Сырое тело webhook-события')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Идентификатор сохранённого события',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/EventIdHolder'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 500, description: 'Ошибка обработки (FailEnvelope либо ErrorEnvelope)'),
        ],
        security: []
    )]
    #[Route('/{id}/hook', methods: ['POST'])]
    public function hook(string $id, Request $request, DobavitSobitieIzHranilishaUseCase $useCase): Response
    {
        $id = $useCase->handle(new DobavitSobitieIzHranilisha($id, $request->getContent()));
        return $this->json(\App\Utils\Model\Reponse\Response::success(['eventId' => $id]));
    }

    #[OA\Post(
        path: '/api/project/statistikaPoProektam',
        summary: 'Статистика по проектам',
        tags: ['Project'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Статистика единиц по проектам',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(
                            property: 'data',
                            description: 'Статистика единиц по проектам',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/ProjectStatistics')
                        ),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки (FailEnvelope либо ErrorEnvelope)'),
        ]
    )]
    #[Route('/statistikaPoProektam', methods: ['POST'])]
    public function statistikaPoProektam(StatistikaPoProektuRepository $statistikaPoProektuRepository): Response
    {
        return $this->json(\App\Utils\Model\Reponse\Response::success($statistikaPoProektuRepository->getList()));
    }

    #[OA\Post(
        path: '/api/project/poluchitVipolnenieZadachiRunnera',
        summary: 'Задания раннера по проекту',
        tags: ['Project'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/GetProjectRunnerJobs')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Задания runner по проекту без шагов. При перехваченной ошибке PHP тело всё равно приходит с HTTP 200 в виде ErrorEnvelope',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(
                            property: 'data',
                            description: 'Задания runner по проекту без шагов',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/ProjectRunnerJob')
                        ),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки (FailEnvelope либо ErrorEnvelope)'),
        ]
    )]
    #[Route('/poluchitVipolnenieZadachiRunnera', methods: ['POST'])]
    public function poluchitVipolnenieZadachiRunnera(#[MapRequestPayload] GetProjectRunnerJobs $command, GetProjectRunnerJobsQuery $query): Response
    {
        try {
            $data = $query->handle($command);
            return $this->json(\App\Utils\Model\Reponse\Response::success($data));
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($this->exceptionTranslator->translateThrowable($error)));
        }
    }

    #[OA\Post(
        path: '/api/project/poluchitShagiZadachiRunnera',
        summary: 'Шаги задания раннера по проекту',
        tags: ['Project'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/GetProjectRunnerJobSteps')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Задание runner по проекту с шагами. При перехваченной ошибке PHP тело всё равно приходит с HTTP 200 в виде ErrorEnvelope',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/ProjectRunnerJobDetailed'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки (FailEnvelope либо ErrorEnvelope)'),
        ]
    )]
    #[Route('/poluchitShagiZadachiRunnera', methods: ['POST'])]
    public function poluchitShagiZadachiRunnera(#[MapRequestPayload] GetProjectRunnerJobSteps $command, GetProjectRunnerJobStepsQuery $query): Response
    {
        try {
            $data = $query->handle($command);
            return $this->json(\App\Utils\Model\Reponse\Response::success($data));
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($this->exceptionTranslator->translateThrowable($error)));
        }
    }

    #[OA\Post(
        path: '/api/project/proverkaProxyHost',
        summary: 'Проверить доступность proxy host',
        tags: ['Project'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ProveritProxyHost')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Результат проверки доступности proxy host. При перехваченной ошибке PHP тело всё равно приходит с HTTP 200 в виде ErrorEnvelope',
                content: new OA\JsonContent(
                    required: ['status', 'data'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/ProxyHostCheckResult'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 500, description: 'Ошибка обработки (FailEnvelope либо ErrorEnvelope)'),
        ]
    )]
    #[Route('/proverkaProxyHost', methods: ['POST'])]
    public function proverkaProxyHost(#[MapRequestPayload] ProveritProxyHost $command, ServiceProverkiProxyHost $service): JsonResponse
    {
        try {
            return $this->json(\App\Utils\Model\Reponse\Response::success(['result' => $service->proverit($command->proxyHost)]));
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($this->exceptionTranslator->translateThrowable($error)));
        }
    }
}
