<?php

namespace App\Unitman\Tests\UseCase\Proekt;

use App\Runner\Api\RunnerApiInterface;
use App\Runner\Business\Model\GolangRunner\Project\InitProjectResult;
use App\Runner\Business\Model\GolangRunner\Project\RemoveProjectResult;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatSbrokiUnita;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatUdaleniyaUnita;
use App\Unitman\Business\Command\Project\ForceRemoveProject;
use App\Unitman\Business\Command\Project\PostavitVOcheredNaSborku;
use App\Unitman\Business\Command\Project\PostavitVOcheredNaUdalenie;
use App\Unitman\Business\Model\Repo\RepoType;
use App\Unitman\Business\Port\CanGeneateGuid;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Unitman\Business\ReadModel\ProjectList\ProjectListStateType;
use App\Unitman\Business\UseCase\Project\ForceRemoveProjectUseCase;
use App\Unitman\Business\UseCase\Project\PostavitVOcheredNaSborkuUseCase;
use App\Unitman\Business\UseCase\Project\PostavitVOcheredNaUdalenieUseCase;
use App\Unitman\Business\UseCase\Project\UstanovitResultatSborkiProektaUseCase;
use App\Unitman\Business\UseCase\Project\UstanovitResultatUdaleniyaProektaUseCase;
use App\Unitman\Infra\Adapter\MemoryGuidGenerator;
use App\Unitman\Acl\MemoryRunnerService;
use App\Unitman\Infra\Repository\Project\SqlProjectListRepository;
use Ramsey\Uuid\Uuid;

final class ProektUdalenVruchnuyuTest extends AbstractProjectUseCase
{
    /**
     * @test
     * */
    function proekt_udalen_vruchnuyu()
    {
        $repoId = Uuid::uuid7()->toString();
        $projectId = Uuid::uuid7()->toString();
        $adminId = Uuid::uuid7()->toString();

        self::$container->set(CanGeneateGuid::class, new MemoryGuidGenerator([$repoId, $projectId]));

        $securityService = $this->getMockBuilder(UnitmanSecurityService::class)->getMock();
        $securityService->expects($this->any())->method('isAdmin')->willReturn(true);
        $securityService->expects($this->any())->method('getCurrentUserId')->willReturn($adminId);
        self::$container->set(UnitmanSecurityService::class, $securityService);

        $this->addRepoRaw(RepoType::GITLAB, 'repoName', 'http://repoUrl');

        $this->addProjectRaw($repoId, 'asdozzz/unitman', 'Units', 'main', 'http://testcase.su');

        $memoryRunner = new MemoryRunnerService();
        $steps = [[
            'Command' => 'command',
            'Response' => 'response',
            'Success' => true,
            'Unixtime' => 123123123
        ]];
        $memoryRunner->addResponse(MemoryRunnerService::BUILD_PROJECT, 'jobId');
        $memoryRunner->addResponse(MemoryRunnerService::RESULTAT_SBORKI_PROEKTA, new InitProjectResult(true, $steps));
        $steps = [[
            'Command' => 'command',
            'Response' => 'response',
            'Success' => false,
            'Unixtime' => 123123123
        ]];
        $memoryRunner->addResponse(MemoryRunnerService::REMOVE_PROJECT, 'jobId');
        $memoryRunner->addResponse(MemoryRunnerService::RESULTAT_UDALENIYA_PROEKTA, new RemoveProjectResult(false, $steps));
        self::$container->set(RunnerApiInterface::class, $memoryRunner);


        //Кинули в очередь на сборку
        $queueCommand = new PostavitVOcheredNaSborku($projectId);
        $queueUseCase = self::$container->get(PostavitVOcheredNaSborkuUseCase::class);
        $queueUseCase->handle($queueCommand);

        $useCase = self::$container->get(UstanovitResultatSborkiProektaUseCase::class);
        $useCase->handle($projectId);

        //Кинули в очередь
        $queueCommand = new PostavitVOcheredNaUdalenie($projectId);
        $queueUseCase = self::$container->get(PostavitVOcheredNaUdalenieUseCase::class);
        $queueUseCase->handle($queueCommand);

        $useCase = self::$container->get(UstanovitResultatUdaleniyaProektaUseCase::class);
        $useCase->handle($projectId);

        $projectListRepo = self::$container->get(SqlProjectListRepository::class);
        /** @var $projectListRepo SqlProjectListRepository*/
        $project = $projectListRepo->getById($projectId);
        $this->assertEquals($project->state, ProjectListStateType::REMOVE_ERROR);
        $this->assertEquals($project->isActive, false);

        //Ручное удаление сборки юнита
        $command = new ForceRemoveProject($projectId);
        $useCase = self::$container->get(ForceRemoveProjectUseCase::class);
        $useCase->handle($command);

        $row = $projectListRepo->findRowById($projectId);
        $this->assertTrue(empty($row), 'Запись в рид модели "список проектов" не удалена');
    }
}
