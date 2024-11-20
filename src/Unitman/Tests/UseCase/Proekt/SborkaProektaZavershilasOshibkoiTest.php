<?php

namespace App\Unitman\Tests\UseCase\Proekt;

use App\Runner\Api\RunnerApiInterface;
use App\Runner\Business\Model\GolangRunner\Project\InitProjectResult;
use App\Unitman\Business\Command\Project\PostavitVOcheredNaSborku;
use App\Unitman\Business\Model\Project\ProjectDataAboutBuilding;
use App\Unitman\Business\Model\Repo\RepoType;
use App\Unitman\Business\Model\Runner\JobId;
use App\Unitman\Business\Model\Runner\ResultatSborkiProekta;
use App\Unitman\Business\Model\Unit\Runner\RunnerJobStep;
use App\Unitman\Business\Port\CanGeneateGuid;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Unitman\Business\ReadModel\Project\ProjectRunnerJob;
use App\Unitman\Business\ReadModel\Project\ProjectRunnerJobWithoutSteps;
use App\Unitman\Business\ReadModel\ProjectList\ProjectListStateType;
use App\Unitman\Business\UseCase\Project\PostavitVOcheredNaSborkuUseCase;
use App\Unitman\Business\UseCase\Project\UstanovitResultatSborkiProektaUseCase;
use App\Unitman\Infra\Adapter\MemoryGuidGenerator;
use App\Unitman\Acl\MemoryRunnerService;
use App\Unitman\Infra\Repository\Project\ProjectRunnerJobRepository;
use App\Unitman\Infra\Repository\Project\SqlProjectListRepository;
use Ramsey\Uuid\Uuid;

final class SborkaProektaZavershilasOshibkoiTest extends AbstractProjectUseCase
{
    /**
     * @test
     * */
    function sborka_proekta_zavershilas_oshibkoi()
    {
        $repoId = Uuid::uuid7()->toString();
        $adminId = Uuid::uuid7()->toString();
        $projectId = Uuid::uuid7()->toString();

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
            'Success' => false,
            'Unixtime' => 123123123
        ]];
        $jobId = '123123';
        $memoryRunner->addResponse(MemoryRunnerService::BUILD_PROJECT, $jobId);
        $memoryRunner->addResponse(MemoryRunnerService::RESULTAT_SBORKI_PROEKTA, new InitProjectResult(false, $steps));
        self::$container->set(RunnerApiInterface::class, $memoryRunner);

        //Кинули в очередь на сборку
        $queueCommand = new PostavitVOcheredNaSborku($projectId);
        $queueUseCase = self::$container->get(PostavitVOcheredNaSborkuUseCase::class);
        $queueUseCase->handle($queueCommand);

        $useCase = self::$container->get(UstanovitResultatSborkiProektaUseCase::class);
        $useCase->handle($projectId);

        $projectListRepo = self::$container->get(SqlProjectListRepository::class);
        /** @var $projectListRepo SqlProjectListRepository*/
        $project = $projectListRepo->getById($projectId);

        $this->assertEquals($project->isActive, false);
        $this->assertEquals($project->state, ProjectListStateType::BUILD_ERROR);

        $projectRunnerJobs = self::$container->get(ProjectRunnerJobRepository::class);
        /** @var ProjectRunnerJobRepository $projectRunnerJobs*/

        $results = $projectRunnerJobs->findAllRunnerJobsByProjectId($projectId);

        $this->assertEquals(ProjectRunnerJob::SBORKA_PROEKTA, $results[0]->jobType);
    }
}
