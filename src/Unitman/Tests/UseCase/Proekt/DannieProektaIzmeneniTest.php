<?php

namespace App\Unitman\Tests\UseCase\Proekt;

use App\Unitman\Business\Command\Project\UpdateProjectData;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Unitman\Business\UseCase\Project\UpdateProjectDataUseCase;
use App\Unitman\Infra\Repository\Project\SqlProjectListRepository;
use Ramsey\Uuid\Uuid;

final class DannieProektaIzmeneniTest extends AbstractProjectUseCase
{
    /**
     * @test
     * */
    function dannie_proekta_izmeneni()
    {
        $repoId = Uuid::uuid7()->toString();
        $adminId = Uuid::uuid7()->toString();

        $securityService = $this->getMockBuilder(UnitmanSecurityService::class)->getMock();
        $securityService->expects($this->any())->method('isAdmin')->willReturn(true);
        $securityService->expects($this->any())->method('getCurrentUserId')->willReturn($adminId);
        self::$container->set(UnitmanSecurityService::class, $securityService);

        $projectId = $this->addProject($repoId, 'asdozzz/unitman', 'Units', 'main', 'http://testcase.su', $adminId);

        $command = new UpdateProjectData($projectId, 'Units2', 'https://testcase2.su', memoryLimit: 500);
        $useCase = self::$container->get(UpdateProjectDataUseCase::class);
        $useCase->handle($command);

        $projectListRepo = self::$container->get(SqlProjectListRepository::class);
        /** @var $projectListRepo SqlProjectListRepository*/
        $project = $projectListRepo->getById($projectId);

        $this->assertEquals($project->name, 'Units2');
        $this->assertEquals($project->proxyHost, 'https://testcase2.su');
        $this->assertEquals($project->memoryLimit, 500);
    }
}
