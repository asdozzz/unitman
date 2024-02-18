<?php

namespace App\Unitman\Tests\UseCase;

use App\Unitman\Business\Command\Project\AddProject;
use App\Unitman\Business\Command\Project\AddUserToProject;
use App\Unitman\Business\Command\Project\DisableProject;
use App\Unitman\Business\Command\Project\EnableProject;
use App\Unitman\Business\Command\Project\ForceRemoveProject;
use App\Unitman\Business\Command\Project\PostavitVOcheredNaSborku;
use App\Unitman\Business\Command\Project\PostavitVOcheredNaUdalenie;
use App\Unitman\Business\Command\Project\RemoveUserFromProject;
use App\Unitman\Business\Command\Project\UpdateProjectData;
use App\Unitman\Business\Model\Project\ProjectDataAboutBuilding;
use App\Unitman\Business\Model\Project\ProjectDataAboutRemoving;
use App\Unitman\Business\Port\CanGeneateGuid;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Unitman\Business\ReadModel\ProjectList\ProjectListStateType;
use App\Unitman\Business\UseCase\Project\AddProjectUseCase;
use App\Unitman\Business\UseCase\Project\AddUserToProjectUseCase;
use App\Unitman\Business\UseCase\Project\DisableProjectUseCase;
use App\Unitman\Business\UseCase\Project\EnableProjectUseCase;
use App\Unitman\Business\UseCase\Project\ForceRemoveProjectUseCase;
use App\Unitman\Business\UseCase\Project\PostavitVOcheredNaSborkuUseCase;
use App\Unitman\Business\UseCase\Project\PostavitVOcheredNaUdalenieUseCase;
use App\Unitman\Business\UseCase\Project\RemoveUserFromProjectUseCase;
use App\Unitman\Business\UseCase\Project\UpdateProjectDataUseCase;
use App\Unitman\Infra\Adapter\MemoryGuidGenerator;
use App\Unitman\Infra\Adapter\MemoryRunnerService;
use App\Unitman\Infra\Repository\Project\SqlProjectListRepository;
use App\Unitman\Infra\Repository\Project\SqlProjectUsersRepository;
use App\Utils\EventSauce\AbstractTestCaseWithTransactionWrapper;
use Ramsey\Uuid\Uuid;

final class ProjectTest extends AbstractTestCaseWithTransactionWrapper
{
    function addProject(string $repoId,string $projectCode, string $projectName, string $mainBranch): string
    {
        $securityService = $this->getMockBuilder(UnitmanSecurityService::class)->getMock();
        $securityService->expects($this->any())->method('isAdmin')->willReturn(true);
        self::$container->set(UnitmanSecurityService::class, $securityService);

        $projectId = Uuid::uuid7()->toString();

        self::$container->set(CanGeneateGuid::class, new MemoryGuidGenerator([$projectId]));

        $command = new AddProject($repoId,$projectCode, $projectName, $mainBranch);
        $useCase = self::$container->get(AddProjectUseCase::class);
        $useCase->handle($command);

        $projectListRepo = self::$container->get(SqlProjectListRepository::class);
        $row = $projectListRepo->findRowById($projectId);

        $this->assertEquals($row['repo_id'], $repoId);
        $this->assertEquals($row['id'], $projectId);
        $this->assertEquals($row['code'], $projectCode);
        $this->assertEquals($row['name'], $projectName);
        $this->assertEquals($row['main_branch'], $mainBranch);
        $this->assertEquals($row['is_active'], 0);
        $this->assertEquals($row['state'], ProjectListStateType::NEW->name);
        $this->assertEquals($row['build_text'], null);
        $this->assertEquals($row['remove_text'], null);

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
        $projectId = $this->addProject($repoId, 'asdozzz/unitman', 'Units', 'main');

        $addUserUseCase = self::$container->get(AddUserToProjectUseCase::class);
        $command = new AddUserToProject($projectId, $userId);
        $addUserUseCase->handle($command);

        $command = new AddUserToProject($projectId, $userId2);
        $addUserUseCase->handle($command);

        $projectUsersRepo = self::$container->get(SqlProjectUsersRepository::class);
        $rows = $projectUsersRepo->findRowsById($projectId);

        $expectedUsers = [['project_id' => $projectId, 'user_id' => $userId, 'role' => 'USER'], ['project_id' => $projectId, 'user_id' => $userId2, 'role' => 'USER']];
        $this->assertEquals($expectedUsers, $rows);

        $removeCommand = new RemoveUserFromProject($projectId, $userId);
        $deleteUseCase = self::$container->get(RemoveUserFromProjectUseCase::class);
        $deleteUseCase->handle($removeCommand);

        $rows = $projectUsersRepo->findRowsById($projectId);

        $expectedUsers = [['project_id' => $projectId, 'user_id' => $userId2, 'role' => 'USER']];
        $this->assertEquals($expectedUsers, $rows);
    }

    /**
     * @test
     * */
    function dannie_proekta_izmeneni()
    {
        $repoId = Uuid::uuid7()->toString();
        $projectId = $this->addProject($repoId, 'asdozzz/unitman', 'Units', 'main');

        $command = new UpdateProjectData($projectId, 'Юниты2');
        $useCase = self::$container->get(UpdateProjectDataUseCase::class);
        $useCase->handle($command);

        $projectListRepo = self::$container->get(SqlProjectListRepository::class);
        $row = $projectListRepo->findRowById($projectId);

        $this->assertEquals($row['name'], 'Юниты2');
    }

    /**
     * @test
     * */
    function proekt_vikluchen()
    {
        $repoId = Uuid::uuid7()->toString();
        $projectId = $this->addProject($repoId, 'asdozzz/unitman', 'Units', 'main');

        $memoryRunner = new MemoryRunnerService();
        $memoryRunner->addResponse(MemoryRunnerService::BUILD_PROJECT, new ProjectDataAboutBuilding('jobId', true, true, 'success_build'));
        self::$container->set(RunnerService::class, $memoryRunner);

        //Кинули в очередь на сборку
        $queueCommand = new PostavitVOcheredNaSborku($projectId);
        $queueUseCase = self::$container->get(PostavitVOcheredNaSborkuUseCase::class);
        $queueUseCase->handle($queueCommand);

        $projectListRepo = self::$container->get(SqlProjectListRepository::class);
        $row = $projectListRepo->findRowById($projectId);

        $this->assertEquals($row['is_active'], 0);
        $this->assertEquals($row['state'], ProjectListStateType::BUILD_SUCCESS->name);
        $this->assertEquals($row['build_text'], 'success_build');

        //Активировали проект
        $enableCommand = new EnableProject($projectId);
        $enableUseCase = self::$container->get(EnableProjectUseCase::class);
        $enableUseCase->handle($enableCommand);

        $row = $projectListRepo->findRowById($projectId);

        $this->assertEquals($row['is_active'], 1);

        //Выключили проект
        $command = new DisableProject($projectId);
        $useCase = self::$container->get(DisableProjectUseCase::class);
        $useCase->handle($command);

        $row = $projectListRepo->findRowById($projectId);

        $this->assertEquals($row['is_active'], 0);

    }

    /**
     * @test
     * */
    function sborka_proekta_zavershilas_oshibkoi()
    {
        $repoId = Uuid::uuid7()->toString();
        $projectId = $this->addProject($repoId, 'asdozzz/unitman', 'Units', 'main');

        $memoryRunner = new MemoryRunnerService();
        $memoryRunner->addResponse(MemoryRunnerService::BUILD_PROJECT, new ProjectDataAboutBuilding('jobId', true, false, 'error_when_build'));
        self::$container->set(RunnerService::class, $memoryRunner);

        //Кинули в очередь на сборку
        $queueCommand = new PostavitVOcheredNaSborku($projectId);
        $queueUseCase = self::$container->get(PostavitVOcheredNaSborkuUseCase::class);
        $queueUseCase->handle($queueCommand);

        $projectListRepo = self::$container->get(SqlProjectListRepository::class);
        $row = $projectListRepo->findRowById($projectId);

        $this->assertEquals($row['is_active'], 0);
        $this->assertEquals($row['state'], ProjectListStateType::BUILD_ERROR->name);
        $this->assertEquals($row['build_text'], 'error_when_build');


    }

    /**
     * @test
     * */
    function proekt_uspeshno_udalen()
    {
        $repoId = Uuid::uuid7()->toString();
        $projectId = $this->addProject($repoId, 'asdozzz/unitman', 'Units', 'main');

        $memoryRunner = new MemoryRunnerService();
        $memoryRunner->addResponse(MemoryRunnerService::REMOVE_PROJECT, new ProjectDataAboutRemoving('jobId', true, true, 'success_remove'));
        self::$container->set(RunnerService::class, $memoryRunner);

        //Кинули в очередь
        $queueCommand = new PostavitVOcheredNaUdalenie($projectId);
        $queueUseCase = self::$container->get(PostavitVOcheredNaUdalenieUseCase::class);
        $queueUseCase->handle($queueCommand);

        $projectListRepo = self::$container->get(SqlProjectListRepository::class);
        $row = $projectListRepo->findRowById($projectId);
        $this->assertTrue(empty($row), 'Запись в рид модели "список проектов" не удалена');
    }

    function proekt_udalen_vruchnuyu()
    {
        $repoId = Uuid::uuid7()->toString();
        $projectId = $this->addProject($repoId, 'asdozzz/unitman', 'Units', 'main');

        $memoryRunner = new MemoryRunnerService();
        $memoryRunner->addResponse(MemoryRunnerService::REMOVE_PROJECT, new ProjectDataAboutRemoving('jobId', true, false, 'error when remove'));
        self::$container->set(RunnerService::class, $memoryRunner);

        //Кинули в очередь
        $queueCommand = new PostavitVOcheredNaUdalenie($projectId);
        $queueUseCase = self::$container->get(PostavitVOcheredNaUdalenieUseCase::class);
        $queueUseCase->handle($queueCommand);

        $projectListRepo = self::$container->get(SqlProjectListRepository::class);
        $row = $projectListRepo->findRowById($projectId);
        $this->assertEquals($row['state'], ProjectListStateType::REMOVE_ERROR->name);
        $this->assertEquals($row['remove_text'], 'error when remove');
        $this->assertEquals($row['is_active'], 0);

        //Ручное удаление сборки юнита
        $command = new ForceRemoveProject($projectId);
        $useCase = self::$container->get(ForceRemoveProjectUseCase::class);
        $useCase->handle($command);

        $row = $projectListRepo->findRowById($projectId);
        $this->assertTrue(empty($row), 'Запись в рид модели "список проектов" не удалена');
    }
}
