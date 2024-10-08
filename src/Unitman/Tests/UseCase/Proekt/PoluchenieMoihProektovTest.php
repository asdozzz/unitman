<?php

namespace App\Unitman\Tests\UseCase\Proekt;

use App\Unitman\Business\Command\Project\AddUserToProject;
use App\Unitman\Business\Command\Project\EnableProject;
use App\Unitman\Business\Command\Project\PoluchitMoiProekti;
use App\Unitman\Business\Command\Project\PoluchitSpisokPolzovateleiProekta;
use App\Unitman\Business\Command\Project\PostavitVOcheredNaSborku;
use App\Unitman\Business\Model\Project\ProjectDataAboutBuilding;
use App\Unitman\Business\Model\Runner\JobId;
use App\Unitman\Business\Model\Runner\ResultatSborkiProekta;
use App\Unitman\Business\Model\Unit\Runner\RunnerJobStep;
use App\Unitman\Business\Port\CanGeneateGuid;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Unitman\Business\ReadModel\ProjectList;
use App\Unitman\Business\ReadModel\ProjectUsersList;
use App\Unitman\Business\UseCase\Project\AddUserToProjectUseCase;
use App\Unitman\Business\UseCase\Project\EnableProjectUseCase;
use App\Unitman\Business\UseCase\Project\PoluchitMoiProektiQuery;
use App\Unitman\Business\UseCase\Project\PoluchitSpisokPolzovateleiProektaQuery;
use App\Unitman\Business\UseCase\Project\PostavitVOcheredNaSborkuUseCase;
use App\Unitman\Business\UseCase\Project\UstanovitResultatSborkiProektaUseCase;
use App\Unitman\Infra\Adapter\MemoryGuidGenerator;
use App\Unitman\Infra\Adapter\MemoryRunnerService;
use Ramsey\Uuid\Uuid;

final class PoluchenieMoihProektovTest extends AbstractProjectUseCase
{
    /**
     * @test
     * */
    function poluchenie_moih_proektov()
    {
        $repoId = Uuid::uuid7()->toString();
        $adminId = Uuid::uuid7()->toString();
        $userId = Uuid::uuid7()->toString();
        $userId2 = Uuid::uuid7()->toString();

        $securityService = $this->getMockBuilder(UnitmanSecurityService::class)->getMock();
        $securityService->expects($this->any())->method('isAdmin')->willReturn(true);
        $securityService->expects($this->any())->method('getCurrentUserId')->willReturnOnConsecutiveCalls(
            $adminId,$adminId,$adminId, $userId
        );
        self::$container->set(UnitmanSecurityService::class, $securityService);

        $projectId = Uuid::uuid7()->toString();
        $projectId2 = Uuid::uuid7()->toString();
        $projectId3 = Uuid::uuid7()->toString();
        self::$container->set(CanGeneateGuid::class, new MemoryGuidGenerator([$projectId, $projectId2, $projectId3]));

        $this->addProjectRaw($repoId, 'test', 'name', 'main', 'http://test.ru');
        $this->addProjectRaw($repoId, 'test2', 'name2', 'main', 'http://test.ru');
        $this->addProjectRaw($repoId, 'test3', 'name3', 'main', 'http://test.ru');

        $memoryRunner = new MemoryRunnerService();
        $steps = [new RunnerJobStep('command', 'response', true, 123123123)];
        $memoryRunner->addResponse(MemoryRunnerService::BUILD_PROJECT, new JobId('jobId'));
        $memoryRunner->addResponse(MemoryRunnerService::RESULTAT_SBORKI_PROEKTA, new ResultatSborkiProekta(true, $steps));
        $memoryRunner->addResponse(MemoryRunnerService::BUILD_PROJECT, new JobId('jobId'));
        $memoryRunner->addResponse(MemoryRunnerService::RESULTAT_SBORKI_PROEKTA, new ResultatSborkiProekta(true, $steps));
        $memoryRunner->addResponse(MemoryRunnerService::BUILD_PROJECT, new JobId('jobId'));
        $memoryRunner->addResponse(MemoryRunnerService::RESULTAT_SBORKI_PROEKTA, new ResultatSborkiProekta(true, $steps));
        self::$container->set(RunnerService::class, $memoryRunner);

        //Кинули в очередь на сборку
        $queueCommand = new PostavitVOcheredNaSborku($projectId);
        $queueUseCase = self::$container->get(PostavitVOcheredNaSborkuUseCase::class);
        $queueUseCase->handle($queueCommand);

        $useCase = self::$container->get(UstanovitResultatSborkiProektaUseCase::class);
        $useCase->handle($projectId);

        $queueCommand = new PostavitVOcheredNaSborku($projectId2);
        $queueUseCase = self::$container->get(PostavitVOcheredNaSborkuUseCase::class);
        $queueUseCase->handle($queueCommand);

        $useCase = self::$container->get(UstanovitResultatSborkiProektaUseCase::class);
        $useCase->handle($projectId2);

        $queueCommand = new PostavitVOcheredNaSborku($projectId3);
        $queueUseCase = self::$container->get(PostavitVOcheredNaSborkuUseCase::class);
        $queueUseCase->handle($queueCommand);

        $useCase = self::$container->get(UstanovitResultatSborkiProektaUseCase::class);
        $useCase->handle($projectId3);

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
        $expectedUsers = [new ProjectUsersList($adminId, 'ADMIN'), new ProjectUsersList($userId, 'USER'), new ProjectUsersList($userId2, 'USER')];
        $this->assertEquals($expectedUsers, $actualUsers);

        $actualUsers = $polzovateliProekta->handle(new PoluchitSpisokPolzovateleiProekta($projectId2));
        $expectedUsers = [new ProjectUsersList($adminId, 'ADMIN'), new ProjectUsersList($userId, 'USER')];
        $this->assertEquals($expectedUsers, $actualUsers);

        $actualUsers = $polzovateliProekta->handle(new PoluchitSpisokPolzovateleiProekta($projectId3));
        $expectedUsers = [new ProjectUsersList($adminId, 'ADMIN'), new ProjectUsersList($userId2, 'USER')];
        $this->assertEquals($expectedUsers, $actualUsers);
    }
}
