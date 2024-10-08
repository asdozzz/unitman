<?php

namespace App\Unitman\Tests\UseCase\Proekt;

use App\Unitman\Business\Command\Project\PostavitVOcheredNaSborku;
use App\Unitman\Business\Model\Project\ProjectDataAboutBuilding;
use App\Unitman\Business\Model\Runner\JobId;
use App\Unitman\Business\Model\Runner\ResultatSborkiProekta;
use App\Unitman\Business\Model\Unit\Runner\RunnerJobStep;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\ReadModel\Project\ProjectRunnerJob;
use App\Unitman\Business\ReadModel\Project\ProjectRunnerJobWithoutSteps;
use App\Unitman\Business\ReadModel\ProjectList\ProjectListStateType;
use App\Unitman\Business\UseCase\Project\PostavitVOcheredNaSborkuUseCase;
use App\Unitman\Business\UseCase\Project\UstanovitResultatSborkiProektaUseCase;
use App\Unitman\Infra\Adapter\MemoryRunnerService;
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
        $projectId = $this->addProject($repoId, 'asdozzz/unitman', 'Units', 'main', 'http://testcase.su', $adminId);

        $memoryRunner = new MemoryRunnerService();
        $steps = [new RunnerJobStep('command', 'response', false, 123123123)];
        $jobId = '123123';
        $memoryRunner->addResponse(MemoryRunnerService::BUILD_PROJECT, new JobId($jobId));
        $memoryRunner->addResponse(MemoryRunnerService::RESULTAT_SBORKI_PROEKTA, new ResultatSborkiProekta(false, $steps));
        self::$container->set(RunnerService::class, $memoryRunner);

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
