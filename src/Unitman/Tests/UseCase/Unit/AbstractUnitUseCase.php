<?php

namespace App\Unitman\Tests\UseCase\Unit;

use App\Unitman\Business\Command\Project\AddProject;
use App\Unitman\Business\Command\Project\AddUserToProject;
use App\Unitman\Business\Command\Project\ObnovitNastroikiHuka;
use App\Unitman\Business\Command\Repo\AddRepo;
use App\Unitman\Business\Command\Unit\SozdatUnit;
use App\Unitman\Business\Model\Account;
use App\Unitman\Business\Model\Project;
use App\Unitman\Business\Model\Repo;
use App\Unitman\Business\Model\Repo\RepoType;
use App\Unitman\Business\Model\Runner\JobId;
use App\Unitman\Business\Model\Unit\Runner\RunnerJobStep;
use App\Unitman\Business\Port\CanGeneateGuid;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Repo\RepoRepository;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Unitman\Business\ReadModel\Unit\SpisokUnitovReadModel;
use App\Unitman\Business\UseCase\Unit\SozdatUnitUseCase;
use App\Unitman\Infra\Adapter\MemoryGuidGenerator;
use App\Unitman\Infra\Adapter\RamseyGuidGenerator;
use App\Unitman\Infra\Repository\Unit\SpisokUnitovRepository;
use App\Unitman\Tests\UseCase\AbstractUnitmanUseCase;
use Ramsey\Uuid\Uuid;

abstract class AbstractUnitUseCase extends AbstractUnitmanUseCase
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
        $securityService->expects($this->any())->method('getEmailOrNicknameByUserId')->willReturn('asd@asd.ru');
        $securityService->expects($this->any())->method('getUserById')->willReturn(new Account($userId, 'asd@asd.ru', false));
        self::$container->set(UnitmanSecurityService::class, $securityService);
        return $securityService;
    }


    /**
     * @param string $projectId
     * @param string $userId
     * @return Project
     */
    public function stubProekta(string $projectId, string $userId, string $projectName = 'uwin', ?string $repoId = null): Project
    {
        //$this->addRepoRaw(RepoType::GITLAB, 'repoName', 'http://repoUrl');
        if (empty($repoId)) {
            $repoId = Uuid::uuid7()->toString();
        }

        $command = new AddProject($repoId, 'projectCode', $projectName, 'master', 'https://testcase.ru', memoryLimit: 500);
        $project = Project::addProject($projectId, $command, $userId);
        $project->postavitVOcheredNaSborku('stub');
        $project->successfullyBuild([new RunnerJobStep('command', 'response', true, 1231231)]);
        $project->enable();
        $project->obnovitNastrokiHuka(new ObnovitNastroikiHuka($projectId, true, true, true, false));
        return $project;
    }

    public function stubRepo(string $repoId, RepoType $repoType, string $repoName, string $repoUrl): Repo
    {
        $repo = Repo::addRepo($repoId, new AddRepo($repoType->value, $repoName, 'tok', $repoUrl), $repoUrl);
        return $repo;
    }


    /**
     * @param string $unitId
     * @return void
     * @throws \Exception
     */
    public function sozdatUnit(string $userId, string $unitId, string $unitName = 'task-123', $projectName = 'uwin', string $projectId = null): void
    {
        $repoId = Uuid::uuid7()->toString();

        $repo = $this->stubRepo($repoId, RepoType::GITLAB, 'repoName', 'http://repoUrl');
        $repo->accessConfirm();

        $repoRepository = self::$container->get(RepoRepository::class);
        /** @var $repoRepository RepoRepository*/
        $repoRepository->save($repo);

        if (empty($projectId)) {
            $projectId = Uuid::uuid7()->toString();
        }

        $project = $this->stubProekta($projectId, $userId, $projectName, $repoId);
        $projectRepository = self::$container->get(ProjectRepository::class);
        /** @var $projectRepository ProjectRepository*/
        $projectRepository->save($project);

        $spisokUnitovRepo = self::$container->get(SpisokUnitovRepository::class);
        /** @var SpisokUnitovRepository $spisokUnitovRepo */

        $useCase2 = self::$container->get(SozdatUnitUseCase::class);
        //new SozdatUnitUseCase($spisokUnitovRepo, $projectRepository, $securityService, $guidGenerator, $unitRepo);
        $useCase2->handle(new SozdatUnit(
            $projectId,
            $unitName,
            'feature/123',
            [],
            3000
        ));

        $spisokUnitovReadModel2 = $spisokUnitovRepo->getById($unitId);
        /** @var SpisokUnitovReadModel $spisokUnitovReadModel2 */

        $this->assertEquals($spisokUnitovReadModel2->name, $unitName);
        $this->assertEquals($spisokUnitovReadModel2->branch, 'feature/123');
        $this->assertEquals($spisokUnitovReadModel2->projectId, $projectId);
        $this->assertEquals($spisokUnitovReadModel2->commands, ['nachatUdalenie']);
    }
}
