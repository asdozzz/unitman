<?php

namespace App\Unitman\Tests\UseCase\Proekt;

use App\Unitman\Business\Command\Project\DisableProject;
use App\Unitman\Business\Command\Project\EnableProject;
use App\Unitman\Business\Model\Account;
use App\Unitman\Business\Model\Repo\RepoType;
use App\Unitman\Business\Port\CanGenerateGuid;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Unitman\Business\UseCase\Project\DisableProjectUseCase;
use App\Unitman\Business\UseCase\Project\EnableProjectUseCase;
use App\Unitman\Infra\Adapter\MemoryGuidGenerator;
use App\Unitman\Infra\Repository\Project\SqlProjectListRepository;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;

final class ProektVikluchenTest extends AbstractProjectUseCase
{
    #[Test]
    function proekt_vikluchen()
    {
        $repoId = Uuid::uuid7()->toString();
        $adminId = Uuid::uuid7()->toString();
        $projectId = Uuid::uuid7()->toString();

        self::$container->set(CanGenerateGuid::class, new MemoryGuidGenerator([$repoId, $projectId]));

        $securityService = $this->getMockBuilder(UnitmanSecurityService::class)->getMock();
        $securityService->expects($this->any())->method('isAdmin')->willReturn(true);
        $securityService->expects($this->any())->method('getCurrentUserId')->willReturn($adminId);
        // Без этого стаба мок возвращает Account с неинициализированными
        // readonly-свойствами и проекция websocket-событий падает.
        $securityService->expects($this->any())->method('getSystemUser')->willReturn(new Account($adminId, 'asd@asd.ru', false));
        self::$container->set(UnitmanSecurityService::class, $securityService);

        $this->addRepoRaw(RepoType::GITLAB, 'repoName', 'http://repoUrl');
        $this->addProjectRaw($repoId, $this->poluchitUnikalnyiKodProekta(), $this->poluchitUnikalnoeImyaProekta(), 'main');

        $projectListRepo = self::$container->get(SqlProjectListRepository::class);
        /** @var $projectListRepo SqlProjectListRepository*/
        $project = $projectListRepo->getById($projectId);

        // Проект активен сразу после создания.
        $this->assertEquals($project->isActive, true);

        //Выключили проект
        $command = new DisableProject($projectId);
        $useCase = self::$container->get(DisableProjectUseCase::class);
        $useCase->handle($command);

        $project = $projectListRepo->getById($projectId);
        $this->assertEquals($project->isActive, false);

        //Включили проект обратно
        $enableUseCase = self::$container->get(EnableProjectUseCase::class);
        $enableUseCase->handle(new EnableProject($projectId));

        $project = $projectListRepo->getById($projectId);
        $this->assertEquals($project->isActive, true);

    }
}
