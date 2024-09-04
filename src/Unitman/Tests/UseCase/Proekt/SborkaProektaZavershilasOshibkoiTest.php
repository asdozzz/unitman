<?php

namespace App\Unitman\Tests\UseCase\Proekt;

use App\Unitman\Business\Command\Project\PostavitVOcheredNaSborku;
use App\Unitman\Business\Model\Project\ProjectDataAboutBuilding;
use App\Unitman\Business\Model\Unit\Runner\RunnerJobStep;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\ReadModel\ProjectList\ProjectListStateType;
use App\Unitman\Business\UseCase\Project\PostavitVOcheredNaSborkuUseCase;
use App\Unitman\Infra\Adapter\MemoryRunnerService;
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
        $memoryRunner->addResponse(MemoryRunnerService::BUILD_PROJECT, new ProjectDataAboutBuilding('jobId', true, false, $steps));
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
        $this->assertEquals($project->buildInfo, $steps);
    }
}
