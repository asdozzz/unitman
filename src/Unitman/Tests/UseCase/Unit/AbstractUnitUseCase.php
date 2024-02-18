<?php

namespace App\Unitman\Tests\UseCase\Unit;

use App\Unitman\Business\Command\Project\AddProject;
use App\Unitman\Business\Command\Project\AddUserToProject;
use App\Unitman\Business\Command\Unit\SozdatUnit;
use App\Unitman\Business\Model\Project;
use App\Unitman\Business\Port\CanGeneateGuid;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Unitman\Business\ReadModel\Unit\SpisokUnitovReadModel;
use App\Unitman\Business\UseCase\Unit\SozdatUnitUseCase;
use App\Unitman\Infra\Adapter\MemoryGuidGenerator;
use App\Unitman\Infra\Repository\Unit\SpisokUnitovRepository;
use Ramsey\Uuid\Uuid;

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
        self::$container->set(UnitmanSecurityService::class, $securityService);
        return $securityService;
    }


    /**
     * @param string $projectId
     * @param string $userId
     * @return Project
     */
    public function stubProekta(string $projectId, string $userId): Project
    {
        $repoId = Uuid::uuid7()->toString();
        $project = Project::addProject($projectId, new AddProject($repoId, 'projectCode', 'uwin', 'master'));
        $project->postavitVOcheredNaSborku('stub');
        $project->successfullyBuild('success');
        $project->addUser(new AddUserToProject($projectId, $userId));
        $project->enable();
        return $project;
    }


    /**
     * @param string $unitId
     * @return void
     * @throws \Exception
     */
    public function sozdatUnit(string $unitId): void
    {
        self::$container->set(CanGeneateGuid::class, new MemoryGuidGenerator([$unitId]));
        $userId = Uuid::uuid7()->toString();
        $securityService = $this->mokaemUspehSecurity($userId);
        $projectId = Uuid::uuid7()->toString();
        $project = $this->stubProekta($projectId, $userId);
        $projectRepository = $this->getMockBuilder(ProjectRepository::class)->getMock();
        $projectRepository->expects($this->any())->method('getById')->willReturn($project);

        $spisokUnitovRepo = self::$container->get(SpisokUnitovRepository::class);
        /** @var SpisokUnitovRepository $spisokUnitovRepo */

        $unitRepo = self::$container->get(UnitRepository::class);
        $useCase2 = new SozdatUnitUseCase($spisokUnitovRepo, $projectRepository, $securityService, new MemoryGuidGenerator([$unitId]), $unitRepo);
        $useCase2->handle(new SozdatUnit(
            $projectId,
            'task-123',
            'feature/123'
        ));

        $spisokUnitovReadModel2 = $spisokUnitovRepo->getById($unitId);
        /** @var SpisokUnitovReadModel $spisokUnitovReadModel2 */

        $this->assertEquals($spisokUnitovReadModel2->waitResultFromRunner, false);
        $this->assertEquals($spisokUnitovReadModel2->state, 'SOZDAN');
        $this->assertEquals($spisokUnitovReadModel2->name, 'task-123');
        $this->assertEquals($spisokUnitovReadModel2->branch, 'feature/123');
        $this->assertEquals($spisokUnitovReadModel2->projectId, $projectId);
        $this->assertEquals($spisokUnitovReadModel2->commands, json_encode(['nachatSborku','nachatUdalenie']));
    }
}
