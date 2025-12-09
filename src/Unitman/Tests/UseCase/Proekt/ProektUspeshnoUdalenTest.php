<?php

namespace App\Unitman\Tests\UseCase\Proekt;

use App\Runner\Api\RunnerApiInterface;
use App\Unitman\Business\Command\Project\PostavitVOcheredNaUdalenie;
use App\Unitman\Business\Model\Project\ProjectDataAboutRemoving;
use App\Unitman\Business\Model\Unit\Runner\RunnerJobStep;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Unitman\Business\UseCase\Project\PostavitVOcheredNaUdalenieUseCase;
use App\Unitman\Acl\MemoryRunnerService;
use App\Unitman\Infra\Repository\Project\SqlProjectListRepository;
use Ramsey\Uuid\Uuid;

final class ProektUspeshnoUdalenTest extends AbstractProjectUseCase
{
    /**
     * @test
     * */
    function proekt_uspeshno_udalen()
    {
        $repoId = Uuid::uuid7()->toString();
        $adminId = Uuid::uuid7()->toString();

        $securityService = $this->getMockBuilder(UnitmanSecurityService::class)->getMock();
        $securityService->expects($this->any())->method('isAdmin')->willReturn(true);
        $securityService->expects($this->any())->method('getCurrentUserId')->willReturn($adminId);
        self::$container->set(UnitmanSecurityService::class, $securityService);

        $projectId = $this->addProject($repoId, 'asdozzz/unitman', 'Units', 'main', 'http://testcase.su', $adminId);

        $memoryRunner = new MemoryRunnerService();
        $steps = [new RunnerJobStep('command', 'response', true, 123123123)];
        $memoryRunner->addResponse(MemoryRunnerService::REMOVE_PROJECT, new ProjectDataAboutRemoving('jobId', true, true, $steps));
        self::$container->set(RunnerApiInterface::class, $memoryRunner);

        //Кинули в очередь
        $queueCommand = new PostavitVOcheredNaUdalenie($projectId);
        $queueUseCase = self::$container->get(PostavitVOcheredNaUdalenieUseCase::class);
        $queueUseCase->handle($queueCommand);

        $projectListRepo = self::$container->get(SqlProjectListRepository::class);
        /** @var $projectListRepo SqlProjectListRepository*/
        $row = $projectListRepo->findRowById($projectId);
        $this->assertTrue(empty($row), 'Запись в рид модели "список проектов" не удалена');
    }
}
