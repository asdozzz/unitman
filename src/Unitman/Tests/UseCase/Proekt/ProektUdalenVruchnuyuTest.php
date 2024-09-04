<?php

namespace App\Unitman\Tests\UseCase\Proekt;

use App\Unitman\Business\Command\Project\ForceRemoveProject;
use App\Unitman\Business\Command\Project\PostavitVOcheredNaSborku;
use App\Unitman\Business\Command\Project\PostavitVOcheredNaUdalenie;
use App\Unitman\Business\Model\Project\ProjectDataAboutBuilding;
use App\Unitman\Business\Model\Project\ProjectDataAboutRemoving;
use App\Unitman\Business\Model\Unit\Runner\RunnerJobStep;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\ReadModel\ProjectList\ProjectListStateType;
use App\Unitman\Business\UseCase\Project\ForceRemoveProjectUseCase;
use App\Unitman\Business\UseCase\Project\PostavitVOcheredNaSborkuUseCase;
use App\Unitman\Business\UseCase\Project\PostavitVOcheredNaUdalenieUseCase;
use App\Unitman\Infra\Adapter\MemoryRunnerService;
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
        $adminId = Uuid::uuid7()->toString();
        $projectId = $this->addProject($repoId, 'asdozzz/unitman', 'Units', 'main', 'http://testcase.su', $adminId);

        $memoryRunner = new MemoryRunnerService();
        $steps = [new RunnerJobStep('command', 'response', true, 123123123)];
        $memoryRunner->addResponse(MemoryRunnerService::BUILD_PROJECT, new ProjectDataAboutBuilding('jobId', true, true, $steps));
        $steps = [new RunnerJobStep('command', 'response', false, 123123123)];
        $memoryRunner->addResponse(MemoryRunnerService::REMOVE_PROJECT, new ProjectDataAboutRemoving('jobId', true, false, $steps));
        self::$container->set(RunnerService::class, $memoryRunner);

        //Кинули в очередь на сборку
        $queueCommand = new PostavitVOcheredNaSborku($projectId);
        $queueUseCase = self::$container->get(PostavitVOcheredNaSborkuUseCase::class);
        $queueUseCase->handle($queueCommand);


        //Кинули в очередь
        $queueCommand = new PostavitVOcheredNaUdalenie($projectId);
        $queueUseCase = self::$container->get(PostavitVOcheredNaUdalenieUseCase::class);
        $queueUseCase->handle($queueCommand);

        $projectListRepo = self::$container->get(SqlProjectListRepository::class);
        /** @var $projectListRepo SqlProjectListRepository*/
        $project = $projectListRepo->getById($projectId);
        $this->assertEquals($project->state, ProjectListStateType::REMOVE_ERROR);
        $this->assertEquals($project->removeInfo, $steps);
        $this->assertEquals($project->isActive, false);

        //Ручное удаление сборки юнита
        $command = new ForceRemoveProject($projectId);
        $useCase = self::$container->get(ForceRemoveProjectUseCase::class);
        $useCase->handle($command);

        $row = $projectListRepo->findRowById($projectId);
        $this->assertTrue(empty($row), 'Запись в рид модели "список проектов" не удалена');
    }
}
