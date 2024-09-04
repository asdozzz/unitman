<?php

namespace App\Unitman\Tests\UseCase\Proekt;

use App\Unitman\Business\Command\Project\DisableProject;
use App\Unitman\Business\Command\Project\EnableProject;
use App\Unitman\Business\Command\Project\PostavitVOcheredNaSborku;
use App\Unitman\Business\Model\Project\ProjectDataAboutBuilding;
use App\Unitman\Business\Model\Unit\Runner\RunnerJobStep;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\ReadModel\ProjectList\ProjectListStateType;
use App\Unitman\Business\UseCase\Project\DisableProjectUseCase;
use App\Unitman\Business\UseCase\Project\EnableProjectUseCase;
use App\Unitman\Business\UseCase\Project\PostavitVOcheredNaSborkuUseCase;
use App\Unitman\Infra\Adapter\MemoryRunnerService;
use App\Unitman\Infra\Repository\Project\SqlProjectListRepository;
use Ramsey\Uuid\Uuid;

final class ProektVikluchenTest extends AbstractProjectUseCase
{
    /**
     * @test
     * */
    function proekt_vikluchen()
    {
        $repoId = Uuid::uuid7()->toString();
        $adminId = Uuid::uuid7()->toString();
        $projectId = $this->addProject($repoId, 'asdozzz/unitman', 'Units', 'main', 'http://testcase.su', $adminId);

        $memoryRunner = new MemoryRunnerService();
        $steps = [new RunnerJobStep('command', 'response', true, 123123123)];
        $memoryRunner->addResponse(MemoryRunnerService::BUILD_PROJECT, new ProjectDataAboutBuilding('jobId', true, true, $steps));
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
        $this->assertEquals($project->buildInfo, $steps);

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
}
