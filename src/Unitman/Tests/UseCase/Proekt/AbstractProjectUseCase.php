<?php

namespace App\Unitman\Tests\UseCase\Proekt;

use App\Unitman\Business\Command\Project\AddProject;
use App\Unitman\Business\Command\Repo\AddRepo;
use App\Unitman\Business\Model\Repo\RepoType;
use App\Unitman\Business\Port\CanGeneateGuid;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Unitman\Business\ReadModel\ProjectList\ProjectListStateType;
use App\Unitman\Business\ReadModel\ProjectUsersList;
use App\Unitman\Business\UseCase\Project\AddProjectUseCase;
use App\Unitman\Business\UseCase\Repo\AddRepoUseCase;
use App\Unitman\Infra\Adapter\MemoryGuidGenerator;
use App\Unitman\Infra\Repository\Project\SqlProjectListRepository;
use App\Unitman\Tests\UseCase\AbstractUnitmanUseCase;
use App\Utils\EventSauce\AbstractTestCaseWithTransactionWrapper;
use Ramsey\Uuid\Uuid;

abstract class AbstractProjectUseCase extends AbstractUnitmanUseCase
{
    function addProject(string $repoId,string $projectCode, string $projectName, string $mainBranch, string $proxyHost, string $userId): string
    {
        $securityService = $this->getMockBuilder(UnitmanSecurityService::class)->getMock();
        $securityService->expects($this->any())->method('isAdmin')->willReturn(true);
        $securityService->expects($this->any())->method('getCurrentUserId')->willReturn($userId);
        self::$container->set(UnitmanSecurityService::class, $securityService);

        $projectId = Uuid::uuid7()->toString();
        self::$container->set(CanGeneateGuid::class, new MemoryGuidGenerator([$projectId]));

        $this->addProjectRaw($repoId, $projectCode, $projectName, $mainBranch, $proxyHost);

        $projectListRepo = self::$container->get(SqlProjectListRepository::class);
        /** @var $projectListRepo SqlProjectListRepository*/
        $project = $projectListRepo->getById($projectId);

        $this->assertEquals($project->repoId, $repoId);
        $this->assertEquals($project->id, $projectId);
        $this->assertEquals($project->code, $projectCode);
        $this->assertEquals($project->name, $projectName);
        $this->assertEquals($project->mainBranch, $mainBranch);
        $this->assertEquals($project->isActive, false);
        $this->assertEquals($project->state, ProjectListStateType::NEW);
        $this->assertEquals($project->proxyHost, $proxyHost);
        $this->assertEquals($project->users, [new ProjectUsersList($userId, 'ADMIN')]);

        return $projectId;
    }
}
