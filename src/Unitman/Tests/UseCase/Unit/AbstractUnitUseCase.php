<?php

namespace App\Unitman\Tests\UseCase\Unit;

use App\Unitman\Business\Command\Project\AddProject;
use App\Unitman\Business\Command\Project\AddUserToProject;
use App\Unitman\Business\Command\Unit\SozdatUnit;
use App\Unitman\Business\Model\Account;
use App\Unitman\Business\Model\Project;
use App\Unitman\Business\Model\Unit\Runner\RunnerJobStep;
use App\Unitman\Business\Port\CanGeneateGuid;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Unitman\Business\ReadModel\Unit\SpisokUnitovReadModel;
use App\Unitman\Business\UseCase\Unit\SozdatUnitUseCase;
use App\Unitman\Infra\Adapter\MemoryGuidGenerator;
use App\Unitman\Infra\Repository\Unit\SpisokUnitovRepository;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Clock\ClockInterface;

abstract class AbstractUnitUseCase extends \App\Utils\EventSauce\AbstractTestCaseWithTransactionWrapper
{

    /**
     * @param string $userId
     * @return (UnitmanSecurityService&\PHPUnit\Framework\MockObject\MockObject)|\PHPUnit\Framework\MockObject\MockObject
     */
    protected function mokaemUspehSecurity(string $userId): \PHPUnit\Framework\MockObject\MockObject|UnitmanSecurityService
    {
        $securityService = $this->getMockBuilder(UnitmanSecurityService::class)->getMock();
        $securityService->expects($this->any())->method('isAdmin')->willReturn(true);
        $securityService->expects($this->any())->method('getCurrentUserId')->willReturn($userId);
        $securityService->expects($this->any())->method('getEmailByUserId')->willReturn('asd@asd.ru');
        $securityService->expects($this->any())->method('getUserById')->willReturn(new Account($userId, 'asd@asd.ru'));
        self::$container->set(UnitmanSecurityService::class, $securityService);
        return $securityService;
    }


    /**
     * @param string $projectId
     * @param string $userId
     * @return Project
     */
    public function stubProekta(string $projectId, string $userId, string $projectName = 'uwin'): Project
    {
        $repoId = Uuid::uuid7()->toString();
        $project = Project::addProject($projectId, new AddProject($repoId, 'projectCode', $projectName, 'master', 'https://testcase.ru'), $userId);
        $project->postavitVOcheredNaSborku('stub');
        $project->successfullyBuild([new RunnerJobStep('command', 'response', true, 1231231)]);
        $project->enable();
        return $project;
    }


    /**
     * @param string $unitId
     * @return void
     * @throws \Exception
     */
    public function sozdatUnit(string $unitId, string $unitName = 'task-123', $projectName = 'uwin'): void
    {
        self::$container->set(CanGeneateGuid::class, new MemoryGuidGenerator([$unitId]));
        $userId = Uuid::uuid7()->toString();
        $securityService = $this->mokaemUspehSecurity($userId);
        $projectId = Uuid::uuid7()->toString();
        $project = $this->stubProekta($projectId, $userId, $projectName);
        $projectRepository = self::$container->get(ProjectRepository::class);
        /** @var $projectRepository ProjectRepository*/
        $projectRepository->save($project);

        $spisokUnitovRepo = self::$container->get(SpisokUnitovRepository::class);
        /** @var SpisokUnitovRepository $spisokUnitovRepo */

        $unitRepo = self::$container->get(UnitRepository::class);
        $useCase2 = new SozdatUnitUseCase($spisokUnitovRepo, $projectRepository, $securityService, new MemoryGuidGenerator([$unitId]), $unitRepo);
        $useCase2->handle(new SozdatUnit(
            $projectId,
            $unitName,
            'feature/123'
        ));

        $spisokUnitovReadModel2 = $spisokUnitovRepo->getById($unitId);
        /** @var SpisokUnitovReadModel $spisokUnitovReadModel2 */

        $this->assertEquals($spisokUnitovReadModel2->waitResultFromRunner, false);
        $this->assertEquals($spisokUnitovReadModel2->state, 'SOZDAN');
        $this->assertEquals($spisokUnitovReadModel2->name, $unitName);
        $this->assertEquals($spisokUnitovReadModel2->branch, 'feature/123');
        $this->assertEquals($spisokUnitovReadModel2->projectId, $projectId);
        $this->assertEquals($spisokUnitovReadModel2->commands, ['nachatSborku','nachatUdalenie']);
    }
}
