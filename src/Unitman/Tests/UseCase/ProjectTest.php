<?php

namespace App\Unitman\Tests\UseCase;

use App\Unitman\Business\Command\Project\AddProject;
use App\Unitman\Business\Command\Project\AddUserToProject;
use App\Unitman\Business\Command\Project\DisableProject;
use App\Unitman\Business\Command\Project\EnableProject;
use App\Unitman\Business\Command\Project\ForceRemoveProject;
use App\Unitman\Business\Command\Project\GetActiveProjectList;
use App\Unitman\Business\Command\Project\GetProjectList;
use App\Unitman\Business\Command\Project\PoluchitMoiProekti;
use App\Unitman\Business\Command\Project\PoluchitSpisokPolzovateleiProekta;
use App\Unitman\Business\Command\Project\PostavitVOcheredNaSborku;
use App\Unitman\Business\Command\Project\PostavitVOcheredNaUdalenie;
use App\Unitman\Business\Command\Project\RemoveUserFromProject;
use App\Unitman\Business\Command\Project\UpdateProjectData;
use App\Unitman\Business\Model\Project\ProjectDataAboutBuilding;
use App\Unitman\Business\Model\Project\ProjectDataAboutRemoving;
use App\Unitman\Business\Port\CanGeneateGuid;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Unitman\Business\ReadModel\ProjectList;
use App\Unitman\Business\ReadModel\ProjectList\ProjectListStateType;
use App\Unitman\Business\ReadModel\ProjectUsersList;
use App\Unitman\Business\UseCase\Project\AddProjectUseCase;
use App\Unitman\Business\UseCase\Project\AddUserToProjectUseCase;
use App\Unitman\Business\UseCase\Project\DisableProjectUseCase;
use App\Unitman\Business\UseCase\Project\EnableProjectUseCase;
use App\Unitman\Business\UseCase\Project\ForceRemoveProjectUseCase;
use App\Unitman\Business\UseCase\Project\PoluchitMoiProektiQuery;
use App\Unitman\Business\UseCase\Project\PoluchitSpisokPolzovateleiProektaQuery;
use App\Unitman\Business\UseCase\Project\PostavitVOcheredNaSborkuUseCase;
use App\Unitman\Business\UseCase\Project\PostavitVOcheredNaUdalenieUseCase;
use App\Unitman\Business\UseCase\Project\RemoveUserFromProjectUseCase;
use App\Unitman\Business\UseCase\Project\UpdateProjectDataUseCase;
use App\Unitman\Infra\Adapter\MemoryGuidGenerator;
use App\Unitman\Infra\Adapter\MemoryRunnerService;
use App\Unitman\Infra\Repository\Project\SqlProjectListRepository;
use App\Utils\EventSauce\AbstractTestCaseWithTransactionWrapper;
use Ramsey\Uuid\Uuid;

final class ProjectTest extends AbstractTestCaseWithTransactionWrapper
{
    function addProject(string $repoId,string $projectCode, string $projectName, string $mainBranch, string $proxyHost): string
    {
        $securityService = $this->getMockBuilder(UnitmanSecurityService::class)->getMock();
        $securityService->expects($this->any())->method('isAdmin')->willReturn(true);
        self::$container->set(UnitmanSecurityService::class, $securityService);

        $projectId = Uuid::uuid7()->toString();
        self::$container->set(CanGeneateGuid::class, new MemoryGuidGenerator([$projectId]));

        $this->addProjectRaw($repoId, $projectCode, $projectName, $mainBranch, $proxyHost);

        $projectListRepo = self::$container->get(SqlProjectListRepository::class);
        /** @var $projectListRepo SqlProjectListRepository*/
        $project = $projectListRepo->getById($projectId);

        $this->assertEquals($project->repoId, $repoId);
        $this->assertEquals($project->id, $projectId);
        $this->assertEquals($project->code, $projectCode);
        $this->assertEquals($project->name, $projectName);
        $this->assertEquals($project->mainBranch, $mainBranch);
        $this->assertEquals($project->isActive, false);
        $this->assertEquals($project->state, ProjectListStateType::NEW);
        $this->assertEquals($project->buildInfo, null);
        $this->assertEquals($project->removeInfo, null);
        $this->assertEquals($project->proxyHost, $proxyHost);

        return $projectId;
    }
    /**
     * @test
     * */
    function user_udalen_is_proekta()
    {
        $repoId = Uuid::uuid7()->toString();
        $userId = Uuid::uuid7()->toString();
        $userId2 = Uuid::uuid7()->toString();
        $projectId = $this->addProject($repoId, 'asdozzz/unitman', 'Units', 'main', 'thttp://testcase.su');

        $addUserUseCase = self::$container->get(AddUserToProjectUseCase::class);
        $command = new AddUserToProject($projectId, $userId);
        $addUserUseCase->handle($command);

        $command = new AddUserToProject($projectId, $userId2);
        $addUserUseCase->handle($command);

        $projectRepo = self::$container->get(SqlProjectListRepository::class);
        /** @var $projectRepo SqlProjectListRepository*/
        $project = $projectRepo->getById($projectId);
        $expectedUsers = [new ProjectUsersList($userId, 'USER'), new ProjectUsersList($userId2, 'USER')];
        $this->assertEquals($expectedUsers, $project->users);

        $removeCommand = new RemoveUserFromProject($projectId, $userId);
        $deleteUseCase = self::$container->get(RemoveUserFromProjectUseCase::class);
        $deleteUseCase->handle($removeCommand);

        $project = $projectRepo->getById($projectId);

        $expectedUsers = [new ProjectUsersList($userId2, 'USER')];
        $this->assertEquals($expectedUsers, $project->users);
    }

    /**
     * @test
     * */
    function dannie_proekta_izmeneni()
    {
        $repoId = Uuid::uuid7()->toString();
        $projectId = $this->addProject($repoId, 'asdozzz/unitman', 'Units', 'main', 'http://testcase.su');

        $command = new UpdateProjectData($projectId, 'Юниты2', 'https://testcase2.su');
        $useCase = self::$container->get(UpdateProjectDataUseCase::class);
        $useCase->handle($command);

        $projectListRepo = self::$container->get(SqlProjectListRepository::class);
        /** @var $projectListRepo SqlProjectListRepository*/
        $project = $projectListRepo->getById($projectId);

        $this->assertEquals($project->name, 'Юниты2');
        $this->assertEquals($project->proxyHost, 'https://testcase2.su');
    }

    /**
     * @test
     * */
    function proekt_vikluchen()
    {
        $repoId = Uuid::uuid7()->toString();
        $projectId = $this->addProject($repoId, 'asdozzz/unitman', 'Units', 'main', 'http://testcase.su');

        $memoryRunner = new MemoryRunnerService();
        $memoryRunner->addResponse(MemoryRunnerService::BUILD_PROJECT, new ProjectDataAboutBuilding('jobId', true, true, 'success_build'));
        self::$container->set(RunnerService::class, $memoryRunner);

        //Кинули в очередь на сборку
        $queueCommand = new PostavitVOcheredNaSborku($projectId);
        $queueUseCase = self::$container->get(PostavitVOcheredNaSborkuUseCase::class);
        $queueUseCase->handle($queueCommand);

        $projectListRepo = self::$container->get(SqlProjectListRepository::class);
        /** @var $projectListRepo SqlProjectListRepository*/
        $project = $projectListRepo->getById($projectId);

        $this->assertEquals($project->isActive, false);
        $this->assertEquals($project->state, ProjectListStateType::BUILD_SUCCESS);
        $this->assertEquals($project->buildInfo, 'success_build');

        //Активировали проект
        $enableCommand = new EnableProject($projectId);
        $enableUseCase = self::$container->get(EnableProjectUseCase::class);
        $enableUseCase->handle($enableCommand);

        $project = $projectListRepo->getById($projectId);

        $this->assertEquals($project->isActive, true);

        //Выключили проект
        $command = new DisableProject($projectId);
        $useCase = self::$container->get(DisableProjectUseCase::class);
        $useCase->handle($command);

        $project = $projectListRepo->getById($projectId);
        $this->assertEquals($project->isActive, false);

    }

    /**
     * @test
     * */
    function sborka_proekta_zavershilas_oshibkoi()
    {
        $repoId = Uuid::uuid7()->toString();
        $projectId = $this->addProject($repoId, 'asdozzz/unitman', 'Units', 'main', 'http://testcase.su');

        $memoryRunner = new MemoryRunnerService();
        $memoryRunner->addResponse(MemoryRunnerService::BUILD_PROJECT, new ProjectDataAboutBuilding('jobId', true, false, 'error_when_build'));
        self::$container->set(RunnerService::class, $memoryRunner);

        //Кинули в очередь на сборку
        $queueCommand = new PostavitVOcheredNaSborku($projectId);
        $queueUseCase = self::$container->get(PostavitVOcheredNaSborkuUseCase::class);
        $queueUseCase->handle($queueCommand);

        $projectListRepo = self::$container->get(SqlProjectListRepository::class);
        /** @var $projectListRepo SqlProjectListRepository*/
        $project = $projectListRepo->getById($projectId);

        $this->assertEquals($project->isActive, false);
        $this->assertEquals($project->state, ProjectListStateType::BUILD_ERROR);
        $this->assertEquals($project->buildInfo, 'error_when_build');


    }

    /**
     * @test
     * */
    function proekt_uspeshno_udalen()
    {
        $repoId = Uuid::uuid7()->toString();
        $projectId = $this->addProject($repoId, 'asdozzz/unitman', 'Units', 'main', 'http://testcase.su');

        $memoryRunner = new MemoryRunnerService();
        $memoryRunner->addResponse(MemoryRunnerService::REMOVE_PROJECT, new ProjectDataAboutRemoving('jobId', true, true, 'success_remove'));
        self::$container->set(RunnerService::class, $memoryRunner);

        //Кинули в очередь
        $queueCommand = new PostavitVOcheredNaUdalenie($projectId);
        $queueUseCase = self::$container->get(PostavitVOcheredNaUdalenieUseCase::class);
        $queueUseCase->handle($queueCommand);

        $projectListRepo = self::$container->get(SqlProjectListRepository::class);
        /** @var $projectListRepo SqlProjectListRepository*/
        $row = $projectListRepo->findRowById($projectId);
        $this->assertTrue(empty($row), 'Запись в рид модели "список проектов" не удалена');
    }

    function proekt_udalen_vruchnuyu()
    {
        $repoId = Uuid::uuid7()->toString();
        $projectId = $this->addProject($repoId, 'asdozzz/unitman', 'Units', 'main', 'http://testcase.su');

        $memoryRunner = new MemoryRunnerService();
        $memoryRunner->addResponse(MemoryRunnerService::REMOVE_PROJECT, new ProjectDataAboutRemoving('jobId', true, false, 'error when remove'));
        self::$container->set(RunnerService::class, $memoryRunner);

        //Кинули в очередь
        $queueCommand = new PostavitVOcheredNaUdalenie($projectId);
        $queueUseCase = self::$container->get(PostavitVOcheredNaUdalenieUseCase::class);
        $queueUseCase->handle($queueCommand);

        $projectListRepo = self::$container->get(SqlProjectListRepository::class);
        /** @var $projectListRepo SqlProjectListRepository*/
        $project = $projectListRepo->getById($projectId);
        $this->assertEquals($project->state, ProjectListStateType::REMOVE_ERROR);
        $this->assertEquals($project->removeInfo, 'error when remove');
        $this->assertEquals($project->isActive, false);

        //Ручное удаление сборки юнита
        $command = new ForceRemoveProject($projectId);
        $useCase = self::$container->get(ForceRemoveProjectUseCase::class);
        $useCase->handle($command);

        $row = $projectListRepo->findRowById($projectId);
        $this->assertTrue(empty($row), 'Запись в рид модели "список проектов" не удалена');
    }

    /**
     * @test
     * */
    function poluchenie_moih_proektov()
    {
        $repoId = Uuid::uuid7()->toString();
        $userId = Uuid::uuid7()->toString();
        $userId2 = Uuid::uuid7()->toString();

        $securityService = $this->getMockBuilder(UnitmanSecurityService::class)->getMock();
        $securityService->expects($this->any())->method('isAdmin')->willReturn(true);
        $securityService->expects($this->any())->method('getCurrentUserId')->willReturn($userId);
        self::$container->set(UnitmanSecurityService::class, $securityService);

        $projectId = Uuid::uuid7()->toString();
        $projectId2 = Uuid::uuid7()->toString();
        $projectId3 = Uuid::uuid7()->toString();
        self::$container->set(CanGeneateGuid::class, new MemoryGuidGenerator([$projectId, $projectId2, $projectId3]));

        $this->addProjectRaw($repoId, 'test', 'name', 'main', 'http://test.ru');
        $this->addProjectRaw($repoId, 'test2', 'name2', 'main', 'http://test.ru');
        $this->addProjectRaw($repoId, 'test3', 'name3', 'main', 'http://test.ru');

        $memoryRunner = new MemoryRunnerService();
        $memoryRunner->addResponse(MemoryRunnerService::BUILD_PROJECT, new ProjectDataAboutBuilding('jobId', true, true, 'success_build'));
        $memoryRunner->addResponse(MemoryRunnerService::BUILD_PROJECT, new ProjectDataAboutBuilding('jobId', true, true, 'success_build'));
        $memoryRunner->addResponse(MemoryRunnerService::BUILD_PROJECT, new ProjectDataAboutBuilding('jobId', true, true, 'success_build'));
        self::$container->set(RunnerService::class, $memoryRunner);

        //Кинули в очередь на сборку
        $queueCommand = new PostavitVOcheredNaSborku($projectId);
        $queueUseCase = self::$container->get(PostavitVOcheredNaSborkuUseCase::class);
        $queueUseCase->handle($queueCommand);

        $queueCommand = new PostavitVOcheredNaSborku($projectId2);
        $queueUseCase = self::$container->get(PostavitVOcheredNaSborkuUseCase::class);
        $queueUseCase->handle($queueCommand);

        $queueCommand = new PostavitVOcheredNaSborku($projectId3);
        $queueUseCase = self::$container->get(PostavitVOcheredNaSborkuUseCase::class);
        $queueUseCase->handle($queueCommand);

        $enableUseCase = self::$container->get(EnableProjectUseCase::class);
        $enableUseCase->handle(new EnableProject($projectId));

        $enableUseCase = self::$container->get(EnableProjectUseCase::class);
        $enableUseCase->handle(new EnableProject($projectId2));

        $enableUseCase = self::$container->get(EnableProjectUseCase::class);
        $enableUseCase->handle(new EnableProject($projectId3));


        $addUserUseCase = self::$container->get(AddUserToProjectUseCase::class);
        $command = new AddUserToProject($projectId, $userId);
        $addUserUseCase->handle($command);
        $command = new AddUserToProject($projectId, $userId2);
        $addUserUseCase->handle($command);

        $addUserUseCase = self::$container->get(AddUserToProjectUseCase::class);
        $command = new AddUserToProject($projectId2, $userId);
        $addUserUseCase->handle($command);

        $addUserUseCase = self::$container->get(AddUserToProjectUseCase::class);
        $command = new AddUserToProject($projectId3, $userId2);
        $addUserUseCase->handle($command);

        $query = self::$container->get(PoluchitMoiProektiQuery::class);
        /** @var PoluchitMoiProektiQuery $query*/
        $actualResult = $query->handle(new PoluchitMoiProekti());

        $actualIds = array_map(fn(ProjectList $projectList) => $projectList->id, $actualResult);
        $expecteResultIds = [$projectId2, $projectId];
        $this->assertEquals($expecteResultIds, $actualIds);

        $polzovateliProekta = self::$container->get(PoluchitSpisokPolzovateleiProektaQuery::class);
        /** @var $polzovateliProekta PoluchitSpisokPolzovateleiProektaQuery*/

        $actualUsers = $polzovateliProekta->handle(new PoluchitSpisokPolzovateleiProekta($projectId));
        $expectedUsers = [new ProjectUsersList($userId, 'USER'), new ProjectUsersList($userId2, 'USER')];
        $this->assertEquals($expectedUsers, $actualUsers);

        $actualUsers = $polzovateliProekta->handle(new PoluchitSpisokPolzovateleiProekta($projectId2));
        $expectedUsers = [new ProjectUsersList($userId, 'USER')];
        $this->assertEquals($expectedUsers, $actualUsers);

        $actualUsers = $polzovateliProekta->handle(new PoluchitSpisokPolzovateleiProekta($projectId3));
        $expectedUsers = [new ProjectUsersList($userId2, 'USER')];
        $this->assertEquals($expectedUsers, $actualUsers);
    }


    /**
     * @param string $repoId
     * @param string $projectCode
     * @param string $projectName
     * @param string $mainBranch
     * @param string $proxyHost
     * @return string
     * @throws \Exception
     */
    private function addProjectRaw(string $repoId, string $projectCode, string $projectName, string $mainBranch, string $proxyHost): void
    {
        $command = new AddProject($repoId, $projectCode, $projectName, $mainBranch, $proxyHost);
        $useCase = self::$container->get(AddProjectUseCase::class);
        $useCase->handle($command);
    }
}
