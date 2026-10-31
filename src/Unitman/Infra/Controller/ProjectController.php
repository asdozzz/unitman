<?php
declare(strict_types=1);

namespace App\Unitman\Infra\Controller;

use App\Unitman\Business\Command\Project\AddProject;
use App\Unitman\Business\Command\Project\AddUserToProject;
use App\Unitman\Business\Command\Project\BuildProject;
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
use App\Unitman\Business\Command\Project\RemoveProject;
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
                description: 'Успешный ответ либо ошибка',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(ref: '#/components/schemas/SuccessEnvelope'),
                        new OA\Schema(ref: '#/components/schemas/ErrorEnvelope'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
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
                description: 'Успешный ответ либо ошибка',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(ref: '#/components/schemas/SuccessEnvelope'),
                        new OA\Schema(ref: '#/components/schemas/ErrorEnvelope'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
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
                description: 'Успешный ответ либо ошибка',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(ref: '#/components/schemas/SuccessEnvelope'),
                        new OA\Schema(ref: '#/components/schemas/ErrorEnvelope'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
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
                description: 'Успешный ответ либо ошибка',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(ref: '#/components/schemas/SuccessEnvelope'),
                        new OA\Schema(ref: '#/components/schemas/ErrorEnvelope'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
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
                description: 'Успешный ответ либо ошибка',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(ref: '#/components/schemas/SuccessEnvelope'),
                        new OA\Schema(ref: '#/components/schemas/ErrorEnvelope'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
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
                description: 'Успешный ответ либо ошибка',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(ref: '#/components/schemas/SuccessEnvelope'),
                        new OA\Schema(ref: '#/components/schemas/ErrorEnvelope'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
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
                description: 'Успешный ответ либо ошибка',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(ref: '#/components/schemas/SuccessEnvelope'),
                        new OA\Schema(ref: '#/components/schemas/ErrorEnvelope'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
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
                description: 'Успешный ответ либо ошибка',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(ref: '#/components/schemas/SuccessEnvelope'),
                        new OA\Schema(ref: '#/components/schemas/ErrorEnvelope'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
        ]
    )]
    #[Route('/remove', methods: ['POST'])]
    public function remove(#[MapRequestPayload] PostavitVOcheredNaUdalenie $command, PostavitVOcheredNaUdalenieUseCase $useCase): Response
    {
        try {
            $useCase->handle($command);
            return $this->json(\App\Utils\Model\Reponse\Response::successStub());
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
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
                description: 'Успешный ответ либо ошибка',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(ref: '#/components/schemas/SuccessEnvelope'),
                        new OA\Schema(ref: '#/components/schemas/ErrorEnvelope'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
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
                description: 'Успешный ответ либо ошибка',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(ref: '#/components/schemas/SuccessEnvelope'),
                        new OA\Schema(ref: '#/components/schemas/ErrorEnvelope'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
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
                description: 'Успешный ответ либо ошибка',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(ref: '#/components/schemas/SuccessEnvelope'),
                        new OA\Schema(ref: '#/components/schemas/ErrorEnvelope'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
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
                description: 'Успешный ответ либо ошибка',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(ref: '#/components/schemas/SuccessEnvelope'),
                        new OA\Schema(ref: '#/components/schemas/ErrorEnvelope'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
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
                description: 'Успешный ответ либо ошибка',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(ref: '#/components/schemas/SuccessEnvelope'),
                        new OA\Schema(ref: '#/components/schemas/ErrorEnvelope'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
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
                description: 'Успешный ответ либо ошибка',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(ref: '#/components/schemas/SuccessEnvelope'),
                        new OA\Schema(ref: '#/components/schemas/ErrorEnvelope'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
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
                description: 'Успешный ответ либо ошибка',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(ref: '#/components/schemas/SuccessEnvelope'),
                        new OA\Schema(ref: '#/components/schemas/ErrorEnvelope'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
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
                description: 'Успешный ответ либо ошибка',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(ref: '#/components/schemas/SuccessEnvelope'),
                        new OA\Schema(ref: '#/components/schemas/ErrorEnvelope'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
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
                description: 'Успешный ответ либо ошибка',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(ref: '#/components/schemas/SuccessEnvelope'),
                        new OA\Schema(ref: '#/components/schemas/ErrorEnvelope'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
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
                description: 'Успешный ответ либо ошибка',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(ref: '#/components/schemas/SuccessEnvelope'),
                        new OA\Schema(ref: '#/components/schemas/ErrorEnvelope'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
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
                description: 'Успешный ответ либо ошибка',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(ref: '#/components/schemas/SuccessEnvelope'),
                        new OA\Schema(ref: '#/components/schemas/ErrorEnvelope'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
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
                description: 'Успешный ответ либо ошибка',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(ref: '#/components/schemas/SuccessEnvelope'),
                        new OA\Schema(ref: '#/components/schemas/ErrorEnvelope'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
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
                description: 'Успешный ответ либо ошибка',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(ref: '#/components/schemas/SuccessEnvelope'),
                        new OA\Schema(ref: '#/components/schemas/ErrorEnvelope'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
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
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(
                            property: 'data',
                            properties: [new OA\Property(property: 'eventId', type: 'string')],
                            type: 'object'
                        ),
                    ],
                    type: 'object'
                )
            ),
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
                description: 'Успешный ответ либо ошибка',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(ref: '#/components/schemas/SuccessEnvelope'),
                        new OA\Schema(ref: '#/components/schemas/ErrorEnvelope'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
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
                description: 'Успешный ответ либо ошибка',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(ref: '#/components/schemas/SuccessEnvelope'),
                        new OA\Schema(ref: '#/components/schemas/ErrorEnvelope'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
        ]
    )]
    #[Route('/poluchitVipolnenieZadachiRunnera', methods: ['POST'])]
    public function poluchitVipolnenieZadachiRunnera(#[MapRequestPayload] GetProjectRunnerJobs $command, GetProjectRunnerJobsQuery $query): Response
    {
        try {
            $data = $query->handle($command);
            return $this->json(\App\Utils\Model\Reponse\Response::success($data));
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
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
                description: 'Успешный ответ либо ошибка',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(ref: '#/components/schemas/SuccessEnvelope'),
                        new OA\Schema(ref: '#/components/schemas/ErrorEnvelope'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
        ]
    )]
    #[Route('/poluchitShagiZadachiRunnera', methods: ['POST'])]
    public function poluchitShagiZadachiRunnera(#[MapRequestPayload] GetProjectRunnerJobSteps $command, GetProjectRunnerJobStepsQuery $query): Response
    {
        try {
            $data = $query->handle($command);
            return $this->json(\App\Utils\Model\Reponse\Response::success($data));
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
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
                description: 'Успешный ответ либо ошибка',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(ref: '#/components/schemas/SuccessEnvelope'),
                        new OA\Schema(ref: '#/components/schemas/ErrorEnvelope'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Не авторизован'),
        ]
    )]
    #[Route('/proverkaProxyHost', methods: ['POST'])]
    public function proverkaProxyHost(#[MapRequestPayload] ProveritProxyHost $command, ServiceProverkiProxyHost $service): JsonResponse
    {
        try {
            return $this->json(\App\Utils\Model\Reponse\Response::success(['result' => $service->proverit($command->proxyHost)]));
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }
    }
}
